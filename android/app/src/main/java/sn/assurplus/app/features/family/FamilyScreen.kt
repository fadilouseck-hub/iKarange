package sn.assurplus.app.features.family

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.Route
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.network.AssurApi
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.core.persist.ResponseCache
import sn.assurplus.app.core.upload.Uploading
import sn.assurplus.app.designsystem.*
import java.time.LocalDate

/** Dependants: consumption per person, pending requests, addition / removal requests (validated by the back office). */
private class FamilyModel(private val api: AssurApi, private val uploader: Uploading, cache: ResponseCache) {
    val resource = RemoteResource(cache, CacheKey.dependents, DependentsResponse.serializer()) { api.dependents() }
    var isSending by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)
    var confirmation by mutableStateOf<Message?>(null)

    /** Creates an addition request (validated by the back office), with optional supporting documents. */
    suspend fun requestAddition(member: QuoteMember, documents: List<PickedDocument>): Boolean {
        isSending = true
        try {
            val uploadIds = documents.map { uploader.upload(it.file, "dependent_document") {} }
            val request = api.requestDependentAddition(
                DependentAddRequest(member.firstName, member.lastName, member.relation, member.birthDate, member.gender, uploadIds)
            )
            confirmation = Message(Message.Level.success, t("Demande d'ajout de %@ envoyée. %@.", request.fullName, request.status.label))
            resource.load()
            return true
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
            return false
        } finally {
            isSending = false
        }
    }

    suspend fun requestRemoval(dependent: Dependent, reason: String) {
        isSending = true
        try {
            val request = api.requestDependentRemoval(dependent.id, reason)
            confirmation = Message(Message.Level.success, t("Demande de retrait de %@ envoyée.", request.fullName))
            resource.load()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isSending = false
        }
    }
}

@Composable
fun FamilyScreen(onBack: () -> Unit) {
    val env = LocalEnv.current
    val model = remember { FamilyModel(env.api, env.uploader, env.cache) }
    val scope = rememberCoroutineScope()
    var showAddForm by remember { mutableStateOf(false) }
    var removal by remember { mutableStateOf<Dependent?>(null) }
    var removalReason by remember { mutableStateOf("") }
    LaunchedEffect(Unit) { model.resource.load() }

    val canManage = env.session.can(Permission.familyManage) && model.resource.value?.canRequestChanges == true

    Screen(
        title = t("Ma famille"),
        onBack = onBack,
        onRefresh = { model.resource.refresh() },
        actions = {
            if (canManage) GlassButton("person.badge.plus", { showAddForm = true }, description = t("Ajouter"), tag = "family.add")
        },
    ) {
        model.confirmation?.let { MessageBanner(it) }
        model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
        LoadableContent(
            resource = model.resource,
            retry = { scope.launch { model.resource.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) { SkeletonCard(lines = 4); SkeletonCard(lines = 4) }
            },
        ) { response ->
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
                response.info?.let { Text(it, style = DS.Typography.footnote, color = DS.Palette.textSecondary) }
                if (response.dependents.isEmpty()) {
                    EmptyStateView(t("Aucun ayant droit"), t("Ajoutez votre conjoint(e) ou vos enfants à votre contrat."), symbol = "person.3")
                }
                response.dependents.forEach { dependent ->
                    DependentCard(dependent, canRemove = canManage && dependent.pendingRequest == null) {
                        removalReason = ""
                        removal = dependent
                    }
                }
                if (response.requests.isNotEmpty()) {
                    Card {
                        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                            SectionHeader(t("Demandes en cours"))
                            response.requests.forEach { request -> RequestRow(request) }
                        }
                    }
                }
                Row(
                    Modifier
                        .card()
                        .clickable(role = Role.Button) { env.router.push(Route.Policy) },
                ) {
                    IconLabel(
                        t("Renouvellement et résiliation du contrat"), "arrow.triangle.2.circlepath",
                        style = DS.Typography.callout.copy(fontWeight = FontWeight.SemiBold),
                    )
                }
            }
        }
    }

    AddDependentRequestSheet(showAddForm, model, onDismiss = { showAddForm = false })

    removal?.let { dependent ->
        TextFieldAlert(
            title = t("Retirer %@", dependent.fullName),
            message = t("Le retrait sera effectif après validation par votre gestionnaire."),
            placeholder = t("Motif du retrait"),
            value = removalReason,
            onValueChange = { removalReason = it },
            confirmTitle = t("Envoyer la demande"),
            destructive = true,
            onConfirm = { scope.launch { model.requestRemoval(dependent, removalReason) } },
            onDismiss = { removal = null },
        )
    }
}

@Composable
private fun RequestRow(request: DependentRequest) {
    Row(Modifier.fillMaxWidth().semantics(mergeDescendants = true) {}.testTag("family.request.${request.id}"), verticalAlignment = Alignment.Top) {
        Icon(
            sym(if (request.kind == DependentRequest.Kind.add) "person.badge.plus" else "person.badge.minus"), null,
            tint = DS.Palette.accent, modifier = Modifier.size(22.dp),
        )
        Spacer(Modifier.width(DS.Spacing.s))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(
                if (request.kind == DependentRequest.Kind.add) t("Ajout de %@", request.fullName) else t("Retrait de %@", request.fullName),
                style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary,
            )
            Text(DateText.dateTime(request.createdAt), style = DS.Typography.caption, color = DS.Palette.textSecondary)
            request.message?.let { Text(it, style = DS.Typography.caption, color = DS.Palette.textPrimary) }
        }
        Spacer(Modifier.width(DS.Spacing.s))
        StatusBadge(request.status)
    }
}

@Composable
private fun DependentCard(dependent: Dependent, canRemove: Boolean, onRemove: () -> Unit) {
    Card {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                InitialsAvatar(dependent.fullName)
                Spacer(Modifier.width(DS.Spacing.m))
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                    Text(dependent.fullName, style = DS.Typography.headline, color = DS.Palette.textPrimary)
                    Text(
                        t("%@ · né(e) le %@", dependent.relationLabel, DateText.day(dependent.birthDate)),
                        style = DS.Typography.caption, color = DS.Palette.textSecondary,
                    )
                }
                Spacer(Modifier.width(DS.Spacing.s))
                StatusBadge(dependent.status)
            }
            dependent.limits?.let { limits ->
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    Row {
                        AmountTile(t("Consommé"), limits.consumed, Modifier.weight(1f))
                        AmountTile(t("Plafond disponible"), limits.remaining, Modifier.weight(1f), tone = StatusTone.success)
                    }
                    val fraction = if (limits.annualLimit > 0) minOf(limits.consumed.toFloat() / limits.annualLimit, 1f) else 0f
                    ProgressBar(fraction, Modifier.semantics { contentDescription = t("Plafond consommé") })
                }
            }
            if (dependent.guarantees.isNotEmpty()) {
                Text(dependent.guarantees.joinToString(" · "), style = DS.Typography.caption, color = DS.Palette.textSecondary)
            }
            val pending = dependent.pendingRequest
            if (pending != null) {
                StatusBadge(pending.status)
            } else if (canRemove) {
                TextLink(
                    t("Demander le retrait"), onRemove,
                    style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.danger,
                    tag = "family.remove.${dependent.id}",
                )
            }
        }
    }
}

@Composable
private fun AddDependentRequestSheet(visible: Boolean, model: FamilyModel, onDismiss: () -> Unit) {
    if (!visible) return
    val scope = rememberCoroutineScope()
    var firstName by remember { mutableStateOf("") }
    var lastName by remember { mutableStateOf("") }
    var relation by remember { mutableStateOf(Relation.child) }
    var gender by remember { mutableStateOf(Gender.female) }
    var birthDate by remember { mutableStateOf(LocalDay.of(LocalDate.now().minusYears(5))) }
    val documents = remember { mutableStateListOf<PickedDocument>() }
    var showPicker by remember { mutableStateOf(false) }
    val isValid = firstName.isNotBlank() && lastName.isNotBlank()

    FormSheet(
        visible = true,
        title = t("Ajouter un ayant droit"),
        onDismiss = onDismiss,
        confirmTitle = t("Envoyer"),
        confirmEnabled = isValid,
        confirmLoading = model.isSending,
        confirmTag = "family.send",
        onConfirm = {
            scope.launch {
                val member = QuoteMember(firstName.trim(), lastName.trim(), relation, birthDate, gender)
                if (model.requestAddition(member, documents.toList())) onDismiss()
            }
        },
    ) {
        FormSection(header = t("Bénéficiaire")) {
            FormPickerRow(t("Lien"), Relation.entries, relation, { it.label }, { relation = it }, divider = false)
            FormTextFieldRow(firstName, { firstName = it }, t("Prénom"), tag = "family.firstName", capitalization = KeyboardCapitalization.Words)
            FormTextFieldRow(lastName, { lastName = it }, t("Nom"), tag = "family.lastName", capitalization = KeyboardCapitalization.Words)
            FormPickerRow(t("Sexe"), Gender.entries, gender, { it.label }, { gender = it })
            FormDateRow(t("Date de naissance"), birthDate, { birthDate = it })
        }
        FormSection(
            header = t("Justificatifs"),
            footer = t("Acte de naissance, certificat de mariage… La demande est validée par votre gestionnaire."),
        ) {
            documents.forEachIndexed { index, document ->
                FormRow(document.file.fileName, divider = index > 0, symbol = "doc", iconTint = DS.Palette.accent)
            }
            FormRow(t("Ajouter un justificatif"), divider = documents.isNotEmpty(), titleColor = DS.Palette.accent, onClick = { showPicker = true })
        }
        model.error?.let { error ->
            FormSection {
                FormRow(error.userMessage, divider = false, titleColor = DS.Palette.danger)
            }
        }
    }

    DocumentPicker(
        visible = showPicker,
        onDismiss = { showPicker = false },
        title = t("Ajouter un justificatif"),
        baseName = "justificatif-${documents.size + 1}",
    ) { documents.add(it) }
}
