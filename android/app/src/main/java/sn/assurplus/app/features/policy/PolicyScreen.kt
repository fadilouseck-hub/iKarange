package sn.assurplus.app.features.policy

import android.content.Context
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.Documents
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*
import java.io.File

private class PolicyModel(private val env: AppEnvironment) {
    val resource = RemoteResource(env.cache, CacheKey.policy, PolicyDetail.serializer()) {
        val policyId = env.cache.load(Dashboard.serializer(), CacheKey.dashboard)?.policy?.id
            ?: env.api.dashboard().policy?.id
            ?: throw APIError.Server(404, "no_policy", t("Aucun contrat actif."), emptyMap())
        env.api.policy(policyId)
    }
    var pdfFile by mutableStateOf<File?>(null)
        private set
    var isDownloading by mutableStateOf(false)
        private set
    var terminationStatus by mutableStateOf<ServerStatus?>(null)
        private set
    var error by mutableStateOf<APIError?>(null)

    /** Downloads the Conditions Particulières into protected storage, then opens the viewer. */
    suspend fun openConditions(context: Context) {
        val policy = resource.value ?: return
        pdfFile?.takeIf { it.exists() }?.let { Documents.open(context, it); return }
        isDownloading = true
        try {
            val data = env.api.conditionsPDF(policy.summary.id)
            val file = Documents.save(data, "Conditions-particulieres-${policy.summary.number}.pdf")
            pdfFile = file
            Documents.open(context, file)
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isDownloading = false
        }
    }

    suspend fun requestTermination(reason: String) {
        val policy = resource.value ?: return
        try {
            terminationStatus = env.api.requestTermination(policy.summary.id, reason)
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        }
    }
}

@Composable
fun PolicyScreen(onBack: () -> Unit) {
    val env = LocalEnv.current
    val context = LocalContext.current
    val model = remember { PolicyModel(env) }
    val scope = rememberCoroutineScope()
    var showTermination by remember { mutableStateOf(false) }
    var terminationReason by remember { mutableStateOf("") }
    LaunchedEffect(Unit) { model.resource.load() }

    Screen(title = t("Mon contrat"), largeTitle = false, onBack = onBack, onRefresh = { model.resource.refresh() }) {
        LoadableContent(
            resource = model.resource,
            retry = { scope.launch { model.resource.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) { SkeletonCard(lines = 4); SkeletonCard(lines = 5) }
            },
        ) { policy ->
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
                Summary(policy)
                if (policy.conditionsVersion != null) {
                    Conditions(
                        policy, model.isDownloading, model.pdfFile,
                        onOpen = { scope.launch { model.openConditions(context) } },
                        onShare = { file -> Documents.share(context, file) },
                    )
                }
                Members(policy)
                Guarantees(policy)
                policy.renewal?.let { renewal ->
                    RenewalSection(
                        renewal, status = model.terminationStatus ?: policy.pendingTermination,
                        canTerminate = renewal.canRequestTermination && env.session.can(Permission.policyManage),
                        onTerminate = { terminationReason = ""; showTermination = true },
                    )
                }
                Rules(policy)
            }
        }
    }

    model.error?.let { error ->
        DSAlert(
            title = t("Erreur"), message = error.userMessage, confirmTitle = t("OK"),
            onConfirm = { model.error = null }, onDismiss = { model.error = null }, dismissTitle = null,
        )
    }
    if (showTermination) {
        TextFieldAlert(
            title = t("Demande de résiliation"),
            message = t("Votre demande sera traitée par votre gestionnaire. Le contrat reste actif jusqu'à son échéance."),
            placeholder = t("Motif"),
            value = terminationReason,
            onValueChange = { terminationReason = it },
            confirmTitle = t("Envoyer"),
            destructive = true,
            onConfirm = { scope.launch { model.requestTermination(terminationReason) } },
            onDismiss = { showTermination = false },
        )
    }
}

@Composable
private fun Summary(policy: PolicyDetail) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    t("Formule %@", policy.summary.formulaName), style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold),
                    color = DS.Palette.textPrimary, modifier = Modifier.weight(1f),
                )
                StatusBadge(policy.summary.status)
            }
            InfoRow(t("N° de contrat"), policy.summary.number, emphasized = true)
            InfoRow(t("Assureur"), policy.insurerName)
            InfoRow(t("Produit"), policy.summary.productName)
            InfoRow(t("Taux de couverture"), Percent.format(policy.summary.coverageRate))
            policy.summary.territoriality?.let { InfoRow(t("Territorialité"), it) }
            InfoRow(t("Période"), "${DateText.day(policy.summary.startDate)} → ${DateText.day(policy.summary.endDate)}")
            policy.premiumLabel?.let { InfoRow(t("Prime"), it) }
        }
    }
}

@Composable
private fun Conditions(policy: PolicyDetail, isDownloading: Boolean, pdf: File?, onOpen: () -> Unit, onShare: (File) -> Unit) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            SectionHeader(t("Conditions particulières"))
            policy.conditionsVersion?.let { Text(t("Version %@", it), style = DS.Typography.caption, color = DS.Palette.textSecondary) }
            Row(horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                PrimaryButton(
                    t("Consulter"), onOpen, Modifier.weight(1f), loading = isDownloading,
                    symbol = if (isDownloading) null else "doc.richtext", tag = "policy.openConditions",
                )
                if (pdf != null) SecondaryButton(t("Partager"), { onShare(pdf) }, Modifier.weight(1f), symbol = "square.and.arrow.up")
            }
        }
    }
}

@Composable
private fun Members(policy: PolicyDetail) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            SectionHeader(t("Bénéficiaires"))
            policy.members.forEach { member ->
                Row(Modifier.fillMaxWidth().semantics(mergeDescendants = true) {}, verticalAlignment = Alignment.CenterVertically) {
                    InitialsAvatar(member.fullName, size = 36.dp)
                    Spacer(Modifier.width(DS.Spacing.m))
                    Column(Modifier.weight(1f)) {
                        Text(member.fullName, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
                        Text(member.relationLabel, style = DS.Typography.caption, color = DS.Palette.textSecondary)
                    }
                    member.birthDate?.let { Text(DateText.day(it), style = DS.Typography.caption, color = DS.Palette.textSecondary) }
                }
            }
        }
    }
}

@Composable
private fun Guarantees(policy: PolicyDetail) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            SectionHeader(t("Garanties"))
            policy.guarantees.forEachIndexed { index, guarantee ->
                Column(Modifier.fillMaxWidth().semantics(mergeDescendants = true) {}, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            guarantee.label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold),
                            color = DS.Palette.textPrimary, modifier = Modifier.weight(1f),
                        )
                        guarantee.rateLabel?.let {
                            Text(it, style = DS.Typography.subheadline.copy(fontFeatureSettings = "tnum"), color = DS.Palette.textPrimary)
                        }
                    }
                    guarantee.limitLabel?.let { Text(it, style = DS.Typography.caption, color = DS.Palette.textSecondary) }
                    guarantee.description?.let { Text(it, style = DS.Typography.caption, color = DS.Palette.textSecondary) }
                }
                if (index < policy.guarantees.lastIndex) Hairline()
            }
        }
    }
}

@Composable
private fun RenewalSection(renewal: RenewalInfo, status: ServerStatus?, canTerminate: Boolean, onTerminate: () -> Unit) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            SectionHeader(t("Renouvellement"))
            InfoRow(t("Échéance"), DateText.day(renewal.renewalDate))
            InfoRow(t("Tacite reconduction"), if (renewal.tacitRenewal) t("Oui") else t("Non"))
            renewal.terminationDeadline?.let { InfoRow(t("Résiliation possible jusqu'au"), DateText.day(it)) }
            renewal.info?.let { Text(it, style = DS.Typography.caption, color = DS.Palette.textSecondary) }
            if (status != null) {
                StatusBadge(status)
            } else if (canTerminate) {
                TextLink(
                    t("Demander la résiliation"), onTerminate, Modifier.padding(top = DS.Spacing.s),
                    style = DS.Typography.callout.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.destructive,
                )
            }
        }
    }
}

@Composable
private fun Rules(policy: PolicyDetail) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            SectionHeader(t("Exclusions et carences"))
            (policy.exclusions + policy.waitingPeriods).distinct().forEach { rule ->
                IconLabel(rule, "minus.circle", style = DS.Typography.callout)
            }
            policy.deductibleLabel?.let { InfoRow(t("Franchise"), it) }
        }
    }
}
