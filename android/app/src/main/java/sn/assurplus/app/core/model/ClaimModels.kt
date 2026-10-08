package sn.assurplus.app.core.model

import kotlinx.serialization.KSerializer
import kotlinx.serialization.Serializable
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import sn.assurplus.app.core.l10n.t

/** The nine claim statuses defined by the specification (CDC §10). Unknown values decode to [unknown]. */
@Serializable(with = ClaimStatusSerializer::class)
enum class ClaimStatus(val raw: String) {
    draft("draft"),
    submitted("submitted"),
    inReview("in_review"),
    documentsRequested("documents_requested"),
    preValidated("pre_validated"),
    validated("validated"),
    rejected("rejected"),
    paid("paid"),
    closed("closed"),
    unknown("unknown");

    val label: String
        get() = when (this) {
            draft -> t("Brouillon")
            submitted -> t("Soumis")
            inReview -> t("En analyse")
            documentsRequested -> t("Pièces complémentaires demandées")
            preValidated -> t("Pré-validé")
            validated -> t("Validé")
            rejected -> t("Rejeté")
            paid -> t("Payé")
            closed -> t("Clôturé")
            unknown -> t("En cours")
        }

    val tone: StatusTone
        get() = when (this) {
            draft, unknown -> StatusTone.neutral
            submitted, inReview, preValidated -> StatusTone.info
            documentsRequested -> StatusTone.warning
            validated, paid, closed -> StatusTone.success
            rejected -> StatusTone.danger
        }

    /** SF Symbol name, mapped to a Material icon by `Sym`. */
    val symbol: String
        get() = when (this) {
            draft -> "square.and.pencil"
            submitted -> "paperplane"
            inReview -> "magnifyingglass"
            documentsRequested -> "doc.badge.plus"
            preValidated -> "checkmark.circle"
            validated -> "checkmark.seal"
            rejected -> "xmark.octagon"
            paid -> "banknote"
            closed -> "archivebox"
            unknown -> "clock"
        }
}

object ClaimStatusSerializer : KSerializer<ClaimStatus> {
    override val descriptor = PrimitiveSerialDescriptor("ClaimStatus", PrimitiveKind.STRING)
    override fun serialize(encoder: Encoder, value: ClaimStatus) = encoder.encodeString(value.raw)
    override fun deserialize(decoder: Decoder): ClaimStatus {
        val raw = decoder.decodeString()
        return ClaimStatus.entries.firstOrNull { it.raw == raw } ?: ClaimStatus.unknown
    }
}

@Serializable
data class ClaimType(
    val code: String,
    val label: String,
    val symbol: String? = null,
    val requiredDocuments: List<String> = emptyList(),
) {
    val id: String get() = code
}

@Serializable
data class ClaimSummary(
    val id: String,
    val number: String? = null,
    val status: ClaimStatus,
    val typeLabel: String,
    val beneficiaryName: String,
    val providerName: String? = null,
    val amount: Long? = null,
    val createdAt: Timestamp,
    val updatedAt: Timestamp,
)

@Serializable
data class ClaimCreateRequest(val beneficiaryId: String, val typeCode: String)

@Serializable
data class ClaimDocument(val id: String, val kind: String, val fileName: String, val createdAt: Timestamp)

@Serializable
data class ClaimDocumentAttach(
    val uploadId: String,
    /** "receipt" or "additional" (answering a document request). */
    val kind: String,
    val requestId: String? = null,
)

/** One extracted value. `confidence` is 0…1 and set by the server's OCR. */
@Serializable
data class OCRField(val key: String, val label: String, val value: String, val confidence: Double? = null) {
    val id: String get() = key
}

@Serializable
data class OCRLine(
    val id: String,
    val label: String,
    val quantity: String,
    val unitPrice: String,
    val amount: String,
    val confidence: Double? = null,
)

@Serializable
data class OCRResult(
    val state: State,
    /** 0…1 while processing, if the server reports it. */
    val progress: Double? = null,
    val fields: List<OCRField> = emptyList(),
    val lines: List<OCRLine> = emptyList(),
    /** Fields under this confidence must be checked by the insured (server-defined threshold). */
    val reviewThreshold: Double,
    val message: String? = null,
) {
    @Serializable
    enum class State { pending, processing, done, failed }
}

@Serializable
data class ClaimUpdate(val fields: Map<String, String>, val lines: List<OCRLine>)

@Serializable
data class ClaimEvent(val status: ClaimStatus, val date: Timestamp, val message: String? = null) {
    val id: String get() = "${status.raw}-${date.epochSecond}"
}

@Serializable
data class DocumentRequest(val id: String, val label: String, val fulfilled: Boolean)

/** Computed by the server's guarantee engine once the claim is validated. */
@Serializable
data class Settlement(
    val billedAmount: Long,
    val coveredAmount: Long? = null,
    val reimbursementRate: Int,
    val deductible: Long? = null,
    val insurerAmount: Long,
    val remainingAmount: Long,
)

@Serializable
data class Claim(
    val id: String,
    val number: String? = null,
    val status: ClaimStatus,
    val typeCode: String,
    val typeLabel: String,
    val beneficiaryId: String,
    val beneficiaryName: String,
    val createdAt: Timestamp,
    val submittedAt: Timestamp? = null,
    val fields: List<OCRField> = emptyList(),
    val lines: List<OCRLine> = emptyList(),
    val documents: List<ClaimDocument> = emptyList(),
    val timeline: List<ClaimEvent> = emptyList(),
    val documentRequests: List<DocumentRequest> = emptyList(),
    val settlement: Settlement? = null,
    val rejectionReason: String? = null,
)
