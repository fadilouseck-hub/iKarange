package sn.assurplus.app.features.claims

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Claim
import sn.assurplus.app.core.model.ClaimDocumentAttach
import sn.assurplus.app.core.model.ClaimStatus
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.Settlement
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*

private val terminalStatuses = setOf(ClaimStatus.paid, ClaimStatus.closed, ClaimStatus.rejected)

class ClaimDetailViewModel(env: AppEnvironment, claimId: String) {
    private val api = env.api
    private val uploader = env.uploader
    val resource = RemoteResource { env.api.claim(claimId) }
    var uploadingRequestId by mutableStateOf<String?>(null)
        private set
    var uploadProgress by mutableStateOf(0.0)
        private set
    var error by mutableStateOf<APIError?>(null)

    /** Answers a "pièces complémentaires" request directly from the claim screen. */
    suspend fun send(document: PickedDocument, requestId: String) {
        val claim = resource.value ?: return
        uploadingRequestId = requestId
        uploadProgress = 0.0
        try {
            val uploadId = uploader.upload(document.file, "claim_document") { uploadProgress = it }
            api.attachClaimDocument(claim.id, ClaimDocumentAttach(uploadId, "additional", requestId))
            resource.load()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            uploadingRequestId = null
        }
    }
}

/** One claim: header, requested documents, settlement, status timeline, receipt details (iOS `ClaimDetailView`). */
@Composable
fun ClaimDetailScreen(claimId: String, onBack: () -> Unit) {
    val env = LocalEnv.current
    val model = remember(claimId) { ClaimDetailViewModel(env, claimId) }
    val scope = rememberCoroutineScope()
    var pickerRequestId by remember { mutableStateOf<String?>(null) }
    var showPicker by remember { mutableStateOf(false) }

    LaunchedEffect(claimId) {
        model.resource.load()
        // Live tracking while the claim is being processed.
        while (true) {
            delay(20_000)
            val status = model.resource.value?.status ?: break
            if (status in terminalStatuses) break
            model.resource.load()
        }
    }

    Screen(
        t("Sinistre"),
        largeTitle = false,
        onBack = onBack,
        onRefresh = { model.resource.refresh() },
        contentPadding = PaddingValues(start = DS.Spacing.l, end = DS.Spacing.l, top = DS.Spacing.l),
    ) {
        LoadableContent(
            model.resource,
            retry = { scope.launch { model.resource.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) { SkeletonCard(lines = 3); SkeletonCard(lines = 5) }
            },
        ) { claim ->
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
                ClaimHeader(claim)
                model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
                claim.rejectionReason?.let { MessageBanner(Message(Message.Level.error, t("Motif du rejet : %@", it))) }
                if (claim.documentRequests.isNotEmpty()) {
                    DocumentRequests(claim, model) { requestId ->
                        pickerRequestId = requestId
                        showPicker = true
                    }
                }
                claim.settlement?.let { SettlementCard(it) }
                ClaimTimeline(claim)
                if (claim.fields.isNotEmpty() || claim.lines.isNotEmpty()) ClaimDetails(claim)
            }
        }
    }

    DocumentPicker(showPicker, { showPicker = false }, t("Ajouter la pièce demandée"), "piece") { document ->
        pickerRequestId?.let { requestId -> scope.launch { model.send(document, requestId) } }
    }
}

@Composable
private fun ClaimHeader(claim: Claim) {
    SpacedCard(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f).padding(end = DS.Spacing.s), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                Text(claim.typeLabel, style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = DS.Palette.textPrimary)
                Text(claim.beneficiaryName, style = DS.Typography.callout, color = DS.Palette.textSecondary)
            }
            StatusBadge(claim.status, Modifier.widthIn(max = 160.dp).testTag("claimDetail.status"))
        }
        claim.number?.let {
            InfoRow(t("N° de sinistre"), it, Modifier.testTag("claimDetail.number"), emphasized = true)
        }
        InfoRow(t("Déclaré le"), DateText.dateTime(claim.submittedAt ?: claim.createdAt))
        claim.fields.firstOrNull { it.key == "total" }?.value?.toLongOrNull()?.let {
            InfoRow(t("Montant déclaré"), Money.format(it))
        }
    }
}

@Composable
private fun DocumentRequests(claim: Claim, model: ClaimDetailViewModel, onAdd: (String) -> Unit) {
    SpacedCard(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        SectionHeader(t("Pièces complémentaires demandées"))
        claim.documentRequests.forEach { request ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                Icon(
                    sym(if (request.fulfilled) "checkmark.circle.fill" else "doc.badge.plus"), null,
                    tint = if (request.fulfilled) DS.Palette.success else DS.Palette.warning,
                    modifier = Modifier.size(22.dp),
                )
                Text(request.label, style = DS.Typography.callout, color = DS.Palette.textPrimary, modifier = Modifier.weight(1f))
                when {
                    model.uploadingRequestId == request.id ->
                        ProgressBar(model.uploadProgress.toFloat(), Modifier.width(60.dp))
                    !request.fulfilled -> ProminentButton(t("Ajouter"), { onAdd(request.id) }, "claimDetail.addDocument.${request.id}")
                    else -> Text(t("Reçue"), style = DS.Typography.caption, color = DS.Palette.success)
                }
            }
        }
    }
}

/** iOS `.borderedProminent` capsule button tinted with the primary colour. */
@Composable
private fun ProminentButton(title: String, onClick: () -> Unit, tag: String) {
    Box(
        Modifier
            .clip(CircleShape)
            .background(DS.Palette.primary)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = DS.Spacing.m + 2.dp, vertical = 7.dp)
            .testTag(tag),
    ) {
        Text(title, style = DS.Typography.body, color = DS.Palette.onPrimary)
    }
}

@Composable
private fun ClaimTimeline(claim: Claim) {
    val events = claim.timeline.reversed()
    SpacedCard(Modifier.testTag("claimDetail.timeline"), verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        SectionHeader(t("Suivi"))
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            events.forEachIndexed { index, event ->
                val hasConnector = index < claim.timeline.size - 1
                Row(
                    Modifier.height(IntrinsicSize.Min).semantics(mergeDescendants = true) {},
                    horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
                ) {
                    Column(Modifier.fillMaxHeight(), horizontalAlignment = Alignment.CenterHorizontally) {
                        Box(
                            Modifier
                                .size(30.dp)
                                .background(if (index == 0) event.status.tone.foreground else event.status.tone.background, CircleShape),
                            contentAlignment = Alignment.Center,
                        ) {
                            Icon(
                                sym(event.status.symbol), null,
                                tint = if (index == 0) Color.White else event.status.tone.foreground,
                                modifier = Modifier.size(15.dp),
                            )
                        }
                        if (hasConnector) Box(Modifier.width(2.dp).weight(1f).background(DS.Palette.border))
                    }
                    Column(
                        Modifier
                            .weight(1f)
                            .heightIn(min = if (hasConnector) 54.dp else 0.dp)
                            .padding(bottom = DS.Spacing.s),
                        verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs),
                    ) {
                        Text(event.status.label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
                        Text(DateText.dateTime(event.date), style = DS.Typography.caption, color = DS.Palette.textSecondary)
                        event.message?.let { Text(it, style = DS.Typography.callout, color = DS.Palette.textPrimary) }
                    }
                }
            }
        }
    }
}

@Composable
private fun ClaimDetails(claim: Claim) {
    SpacedCard(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        SectionHeader(t("Informations du justificatif"))
        claim.fields.forEach { field ->
            val value = if (field.key == "total") field.value.toLongOrNull()?.let(Money::format) ?: field.value else field.value
            InfoRow(field.label, value)
        }
        if (claim.lines.isNotEmpty()) {
            Hairline()
            claim.lines.forEach { line ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(t("%@ × %@", line.quantity, line.label), style = DS.Typography.callout, color = DS.Palette.textPrimary, modifier = Modifier.weight(1f))
                    Text(
                        line.amount.toLongOrNull()?.let(Money::format) ?: line.amount,
                        style = DS.Typography.callout.copy(fontFeatureSettings = "tnum"), color = DS.Palette.textPrimary,
                    )
                }
            }
        }
    }
}

/** Settlement computed by the server's guarantee engine. */
@Composable
fun SettlementCard(settlement: Settlement, modifier: Modifier = Modifier) {
    SpacedCard(modifier.testTag("claimDetail.settlement"), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        SectionHeader(t("Calcul de la prise en charge"))
        InfoRow(t("Montant facturé"), Money.format(settlement.billedAmount))
        settlement.coveredAmount?.let { InfoRow(t("Montant couvert"), Money.format(it)) }
        InfoRow(t("Taux de remboursement"), Percent.format(settlement.reimbursementRate))
        settlement.deductible?.let { InfoRow(t("Franchise"), Money.format(it)) }
        Hairline()
        InfoRow(t("Payé par l'assureur"), Money.format(settlement.insurerAmount), emphasized = true)
        InfoRow(t("Reste à charge"), Money.format(settlement.remainingAmount), emphasized = true)
    }
}

/** `.card()` around a VStack with the given spacing. */
@Composable
internal fun SpacedCard(
    modifier: Modifier = Modifier,
    verticalArrangement: Arrangement.Vertical = Arrangement.spacedBy(DS.Spacing.s),
    horizontalAlignment: Alignment.Horizontal = Alignment.Start,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(modifier.card(), verticalArrangement = verticalArrangement, horizontalAlignment = horizontalAlignment, content = content)
}
