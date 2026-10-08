package sn.assurplus.app.features.claims

import android.graphics.BitmapFactory
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.ensureActive
import kotlinx.serialization.Serializable
import kotlinx.serialization.builtins.ListSerializer
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.CardBeneficiary
import sn.assurplus.app.core.model.Claim
import sn.assurplus.app.core.model.ClaimDocumentAttach
import sn.assurplus.app.core.model.ClaimType
import sn.assurplus.app.core.model.ClaimUpdate
import sn.assurplus.app.core.model.MemberCard
import sn.assurplus.app.core.model.OCRField
import sn.assurplus.app.core.model.OCRLine
import sn.assurplus.app.core.model.OCRResult
import sn.assurplus.app.core.model.Timestamp
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.ProtectedStorage
import sn.assurplus.app.core.upload.UploadFile
import sn.assurplus.app.designsystem.PickedDocument
import java.io.File
import java.time.Instant
import java.util.UUID
import kotlin.coroutines.coroutineContext
import kotlin.time.Duration
import kotlin.time.Duration.Companion.seconds
import kotlin.time.TimeSource

/** Locally saved progress, so a declaration can be finished later or after a network failure. */
@Serializable
data class ClaimDraft(
    val step: NewClaimViewModel.Step,
    val beneficiaryId: String? = null,
    val typeCode: String? = null,
    val claimId: String? = null,
    val receiptFile: String? = null,
    val receiptMimeType: String? = null,
    val fields: List<OCRField> = emptyList(),
    val lines: List<OCRLine> = emptyList(),
    val updatedAt: Timestamp,
)

/**
 * The 10-step declaration (CDC §10): beneficiary → type → photo → upload → OCR (server) → pre-fill → review →
 * submit → number → tracking. The app never computes amounts or eligibility.
 */
class NewClaimViewModel(env: AppEnvironment) {
    @Serializable
    enum class Step {
        beneficiary, type, capture, upload, ocr, review, done;

        val title: String
            get() = when (this) {
                beneficiary -> t("Bénéficiaire")
                type -> t("Type de prestation")
                capture -> t("Justificatif")
                upload -> t("Envoi")
                ocr -> t("Lecture automatique")
                review -> t("Vérification")
                done -> t("Confirmation")
            }
    }

    private val api = env.api
    private val uploader = env.uploader
    private val cache = env.cache

    var step by mutableStateOf(Step.beneficiary)
        private set
    var beneficiaries by mutableStateOf<List<CardBeneficiary>>(emptyList())
        private set
    var claimTypes by mutableStateOf<List<ClaimType>>(emptyList())
        private set
    var beneficiaryId by mutableStateOf<String?>(null)
        private set
    var typeCode by mutableStateOf<String?>(null)
        private set
    var receipt by mutableStateOf<PickedDocument?>(null)
        private set
    var claim by mutableStateOf<Claim?>(null)
        private set
    var uploadProgress by mutableStateOf(0.0)
        private set
    var ocr by mutableStateOf<OCRResult?>(null)
        private set
    val fields = mutableStateListOf<OCRField>()
    val lines = mutableStateListOf<OCRLine>()
    var submitted by mutableStateOf<Claim?>(null)
        private set
    var isWorking by mutableStateOf(false)
        private set
    var isLoadingOptions by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)
    var pendingDraft by mutableStateOf(cache.load(ClaimDraft.serializer(), CacheKey.claimDraft))
        private set

    /** How long to wait for the server OCR before offering manual entry (target < 15 s). */
    var ocrTimeout: Duration = 30.seconds
    var ocrPollInterval: Duration = 1.seconds

    val selectedType: ClaimType? get() = claimTypes.firstOrNull { it.code == typeCode }
    val reviewThreshold: Double get() = ocr?.reviewThreshold ?: 0.85
    val progressIndex: Int get() = step.ordinal + 1

    fun isLowConfidence(confidence: Double?): Boolean = confidence != null && confidence < reviewThreshold

    val lowConfidenceCount: Int
        get() = fields.count { isLowConfidence(it.confidence) } + lines.count { isLowConfidence(it.confidence) }

    val canSubmit: Boolean
        get() = !isWorking && fields.firstOrNull { it.key == "total" }?.value?.isNotBlank() == true

    // MARK: Options

    suspend fun loadOptions() {
        isLoadingOptions = true
        try {
            coroutineScope {
                val card = async { api.card() }
                val types = async { api.claimTypes() }
                beneficiaries = card.await().beneficiaries
                claimTypes = types.await()
            }
            if (beneficiaries.size == 1 && beneficiaryId == null) beneficiaryId = beneficiaries.first().id
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            // Offline: fall back to the cached card so the user can still prepare a draft.
            beneficiaries = cache.load(MemberCard.serializer(), CacheKey.card)?.beneficiaries ?: emptyList()
            claimTypes = cache.load(ListSerializer(ClaimType.serializer()), CacheKey.claimTypes) ?: emptyList()
            error = APIError.wrap(e)
        } finally {
            isLoadingOptions = false
        }
        if (claimTypes.isNotEmpty()) cache.store(ListSerializer(ClaimType.serializer()), claimTypes, CacheKey.claimTypes)
    }

    fun selectBeneficiary(id: String) {
        beneficiaryId = id
        step = Step.type
        saveDraft()
    }

    fun selectType(code: String) {
        typeCode = code
        step = Step.capture
        saveDraft()
    }

    fun back() {
        when {
            step == Step.type -> step = Step.beneficiary
            step == Step.capture -> step = Step.type
            step == Step.review && claim == null -> step = Step.capture
        }
    }

    // MARK: Capture & upload

    suspend fun setReceipt(document: PickedDocument) {
        receipt = document
        step = Step.upload
        saveDraft()
        upload()
    }

    /** Creates the server draft if needed, uploads the receipt (resumable) and attaches it. */
    suspend fun upload() {
        val receipt = receipt ?: return
        val beneficiaryId = beneficiaryId ?: return
        val typeCode = typeCode ?: return
        isWorking = true
        error = null
        try {
            if (claim == null) {
                claim = api.createClaim(beneficiaryId, typeCode)
                saveDraft()
            }
            val claimId = claim?.id ?: return
            uploadProgress = 0.0
            val uploadId = uploader.upload(receipt.file, "claim_document") { progress -> uploadProgress = progress }
            api.attachClaimDocument(claimId, ClaimDocumentAttach(uploadId, "receipt", null))
            uploadProgress = 1.0
            step = Step.ocr
            saveDraft()
            waitForOCR()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isWorking = false
        }
    }

    /** Polls the server-side OCR, then pre-fills the form. Falls back to manual entry on failure/timeout. */
    suspend fun waitForOCR() {
        val claimId = claim?.id ?: return
        val deadline = TimeSource.Monotonic.markNow() + ocrTimeout
        while (deadline.hasNotPassedNow()) {
            coroutineContext.ensureActive()
            try {
                val result = api.ocr(claimId)
                ocr = result
                when (result.state) {
                    OCRResult.State.done -> {
                        fields.setAll(result.fields)
                        lines.setAll(result.lines)
                        step = Step.review
                        saveDraft()
                        return
                    }
                    OCRResult.State.failed -> {
                        startManualEntry()
                        return
                    }
                    OCRResult.State.pending, OCRResult.State.processing -> delay(ocrPollInterval)
                }
            } catch (e: CancellationException) {
                throw e
            } catch (e: Throwable) {
                val apiError = APIError.wrap(e)
                if (apiError == APIError.Cancelled) return
                if (!apiError.isRetryable) {
                    error = apiError
                    startManualEntry()
                    return
                }
                delay(ocrPollInterval)
            }
        }
        startManualEntry()
    }

    /** Empty form when OCR is unavailable; the claim can still be submitted and checked by a human. */
    fun startManualEntry() {
        if (fields.isEmpty()) {
            fields.addAll(
                listOf(
                    "invoiceNumber" to t("N° de facture"), "date" to t("Date"),
                    "provider" to t("Prestataire"), "patient" to t("Patient"),
                    "act" to t("Acte"), "total" to t("Montant total"),
                ).map { OCRField(it.first, it.second, "", null) }
            )
        }
        step = Step.review
        saveDraft()
    }

    fun updateField(index: Int, value: String) {
        fields.getOrNull(index)?.let { fields[index] = it.copy(value = value) }
    }

    fun updateLine(index: Int, transform: (OCRLine) -> OCRLine) {
        lines.getOrNull(index)?.let { lines[index] = transform(it) }
    }

    fun addLine() {
        lines.add(OCRLine(UUID.randomUUID().toString(), "", "1", "", "", null))
    }

    fun removeLine(id: String) {
        lines.removeAll { it.id == id }
    }

    // MARK: Submit

    suspend fun submit() {
        val claimId = claim?.id ?: return
        isWorking = true
        error = null
        try {
            val values = fields.associate { it.key to it.value.trim() }
            api.updateClaim(claimId, ClaimUpdate(values, lines.toList()))
            submitted = api.submitClaim(claimId)
            step = Step.done
            discardDraft()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
            saveDraft()
        } finally {
            isWorking = false
        }
    }

    // MARK: Drafts

    fun saveDraft() {
        if (step == Step.done) return
        val receiptFile = receipt?.let { runCatching { ProtectedStorage.write(it.file.data, RECEIPT_FILE).name }.getOrNull() }
        val draft = ClaimDraft(
            step, beneficiaryId, typeCode, claim?.id, receiptFile, receipt?.file?.mimeType,
            fields.toList(), lines.toList(), Instant.now(),
        )
        cache.store(ClaimDraft.serializer(), draft, CacheKey.claimDraft)
    }

    suspend fun resumeDraft() {
        val draft = pendingDraft ?: return
        pendingDraft = null
        beneficiaryId = draft.beneficiaryId
        typeCode = draft.typeCode
        fields.setAll(draft.fields)
        lines.setAll(draft.lines)
        draft.receiptFile?.let { name ->
            val data = runCatching { File(ProtectedStorage.directory("Files"), name).readBytes() }.getOrNull() ?: return@let
            val mime = draft.receiptMimeType ?: "image/jpeg"
            val fileName = if (mime == "application/pdf") "justificatif.pdf" else "justificatif.jpg"
            receipt = PickedDocument(UploadFile(data, fileName, mime), BitmapFactory.decodeByteArray(data, 0, data.size))
        }
        draft.claimId?.let { id ->
            claim = try {
                api.claim(id)
            } catch (e: CancellationException) {
                throw e
            } catch (e: Throwable) {
                null
            }
        }
        when {
            draft.step == Step.upload && receipt != null -> {
                step = Step.upload
                upload()
            }
            draft.step == Step.ocr && claim != null -> {
                step = Step.ocr
                waitForOCR()
            }
            draft.step == Step.done -> step = Step.beneficiary
            else -> step = if (draft.step == Step.upload || draft.step == Step.ocr) Step.capture else draft.step
        }
    }

    fun discardDraft() {
        pendingDraft = null
        cache.remove(CacheKey.claimDraft)
        runCatching { File(ProtectedStorage.directory("Files"), RECEIPT_FILE).delete() }
    }

    private fun <T> MutableList<T>.setAll(items: List<T>) {
        clear()
        addAll(items)
    }

    private companion object {
        const val RECEIPT_FILE = "claim-draft-receipt"
    }
}
