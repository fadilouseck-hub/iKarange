package sn.assurplus.app.features.vault

import android.content.Context
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.SwipeToDismissBox
import androidx.compose.material3.SwipeToDismissBoxValue
import androidx.compose.material3.Text
import androidx.compose.material3.rememberSwipeToDismissBoxState
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.Documents
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Permission
import sn.assurplus.app.core.model.VaultCategory
import sn.assurplus.app.core.model.VaultDocument
import sn.assurplus.app.core.model.VaultDocumentCreate
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.ProtectedStorage
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*
import kotlinx.serialization.builtins.ListSerializer
import java.io.File
import kotlin.math.roundToLong

/**
 * Health vault. Files are encrypted at rest on the server; local copies opened for viewing live in protected
 * storage (never backed up) and are wiped at logout.
 */
private class VaultModel(private val env: AppEnvironment) {
    val resource = RemoteResource(env.cache, CacheKey.vault, ListSerializer(VaultDocument.serializer())) { env.api.vaultDocuments(null) }
    var category by mutableStateOf<VaultCategory?>(null)
    var openingId by mutableStateOf<String?>(null)
        private set
    var uploadProgress by mutableStateOf<Double?>(null)
        private set
    var error by mutableStateOf<APIError?>(null)

    val documents: List<VaultDocument>
        get() {
            val all = resource.value.orEmpty()
            val selected = category ?: return all
            return all.filter { it.category == selected }
        }

    suspend fun open(document: VaultDocument, context: Context) {
        openingId = document.id
        try {
            val data = env.api.vaultFile(document.id)
            val declaredExt = File(document.fileName).extension.ifEmpty { "jpg" }
            val ext = if (document.mimeType == "application/pdf") "pdf" else declaredExt
            // The mock serves every file as PDF; trust the bytes over the declared type.
            val isPDF = data.size >= 4 && String(data, 0, 4, Charsets.US_ASCII) == "%PDF"
            val file = Documents.save(data, "vault-${document.id}.${if (isPDF) "pdf" else ext}")
            Documents.open(context, file, if (isPDF) "application/pdf" else document.mimeType)
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            openingId = null
        }
    }

    suspend fun upload(picked: PickedDocument, title: String, category: VaultCategory): Boolean {
        uploadProgress = 0.0
        try {
            val uploadId = env.uploader.upload(picked.file, "vault_document") { uploadProgress = it }
            val document = env.api.createVaultDocument(VaultDocumentCreate(uploadId, category, title, null))
            resource.update(listOf(document) + resource.value.orEmpty())
            return true
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
            return false
        } finally {
            uploadProgress = null
        }
    }

    suspend fun delete(document: VaultDocument) {
        try {
            env.api.deleteVaultDocument(document.id)
            resource.update(resource.value.orEmpty().filter { it.id != document.id })
            File(ProtectedStorage.directory("Files"), "vault-${document.id}.pdf").delete()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        }
    }
}

@Composable
fun VaultScreen(onBack: () -> Unit) {
    val env = LocalEnv.current
    val context = LocalContext.current
    val model = remember { VaultModel(env) }
    val scope = rememberCoroutineScope()
    var showPicker by remember { mutableStateOf(false) }
    var pending by remember { mutableStateOf<PickedDocument?>(null) }
    var toDelete by remember { mutableStateOf<VaultDocument?>(null) }
    val canManage = env.session.can(Permission.vaultManage)
    LaunchedEffect(Unit) { model.resource.load() }

    Screen(
        title = t("Coffre santé"),
        onBack = onBack,
        onRefresh = { model.resource.refresh() },
        spacing = DS.Spacing.xl,
        actions = { if (canManage) GlassButton("plus", { showPicker = true }, description = t("Ajouter"), tag = "vault.add") },
    ) {
        // First section: filters, privacy note, upload progress, error.
        Column {
            Row(
                Modifier
                    .padding(horizontal = DS.Spacing.l)
                    .horizontalScroll(rememberScrollState())
                    .padding(vertical = DS.Spacing.xs),
                horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
            ) {
                CategoryChip(model, null, t("Tous"), "tray.full")
                VaultCategory.entries.forEach { CategoryChip(model, it, it.label, it.symbol) }
            }
            Spacer(Modifier.height(DS.Spacing.m))
            Hairline(Modifier.padding(horizontal = DS.Spacing.l))
            IconLabel(
                t("Documents chiffrés, visibles uniquement par vous."), "lock.shield",
                Modifier.padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m),
                style = DS.Typography.footnote, color = DS.Palette.textSecondary,
            )
            val progress = model.uploadProgress
            val error = model.error
            if (progress != null || error != null) {
                FormSection {
                    if (progress != null) {
                        Column(Modifier.padding(DS.Spacing.l), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                            Text(t("Envoi du document…"), style = DS.Typography.body, color = DS.Palette.textPrimary)
                            ProgressBar(progress.toFloat())
                        }
                    }
                    if (error != null) {
                        if (progress != null) Hairline(Modifier.padding(start = DS.Spacing.l))
                        Text(error.userMessage, style = DS.Typography.callout, color = DS.Palette.danger, modifier = Modifier.padding(DS.Spacing.l))
                    }
                }
            }
        }

        // Second section: documents.
        val documents = model.documents
        if (model.resource.value == null && model.resource.isLoading) {
            FormSection {
                repeat(3) { index ->
                    if (index > 0) Hairline(Modifier.padding(start = DS.Spacing.l))
                    SkeletonBlock(Modifier.padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m), height = 44.dp)
                }
            }
        } else if (documents.isEmpty()) {
            EmptyStateView(
                t("Aucun document"), t("Ajoutez vos ordonnances, analyses, radios et carnets de vaccination."), symbol = "lock.doc",
            )
        }
        if (documents.isNotEmpty()) {
            FormSection {
                documents.forEachIndexed { index, document ->
                    key(document.id) {
                        if (index > 0) Hairline(Modifier.padding(start = 68.dp, end = DS.Spacing.l))
                        DocumentRow(
                            document, isOpening = model.openingId == document.id, canDelete = canManage,
                            onOpen = { scope.launch { model.open(document, context) } },
                            onDelete = { toDelete = document },
                        )
                    }
                }
            }
        }
    }

    DocumentPicker(visible = showPicker, onDismiss = { showPicker = false }, title = t("Ajouter un document"), baseName = "document") {
        pending = it
    }
    pending?.let { picked ->
        VaultDocumentForm(picked, initialCategory = model.category ?: VaultCategory.prescription, onDismiss = { pending = null }) { title, category ->
            scope.launch { model.upload(picked, title, category) }
        }
    }
    val deleting = toDelete
    ActionSheet(
        visible = deleting != null,
        title = t("Supprimer « %@ » ?", deleting?.title ?: ""),
        actions = listOf(SheetAction(t("Supprimer définitivement"), destructive = true) {
            deleting?.let { scope.launch { model.delete(it) } }
        }),
        onDismiss = { toDelete = null },
    )
}

@Composable
private fun CategoryChip(model: VaultModel, category: VaultCategory?, label: String, symbol: String) {
    val selected = model.category == category
    Box(Modifier.semantics { this.selected = selected }) {
        FilterCapsule(label, symbol, selected, onClick = { model.category = category })
    }
}

@Composable
private fun DocumentRow(document: VaultDocument, isOpening: Boolean, canDelete: Boolean, onOpen: () -> Unit, onDelete: () -> Unit) {
    val state = rememberSwipeToDismissBoxState()
    val scope = rememberCoroutineScope()
    SwipeToDismissBox(
        state = state,
        enableDismissFromStartToEnd = false,
        enableDismissFromEndToStart = canDelete,
        gesturesEnabled = canDelete,
        onDismiss = { value ->
            if (value == SwipeToDismissBoxValue.EndToStart) onDelete()
            scope.launch { state.reset() }
        },
        backgroundContent = {
            Box(Modifier.fillMaxSize().background(DS.Palette.destructive).padding(horizontal = DS.Spacing.xl), contentAlignment = Alignment.CenterEnd) {
                Text(t("Supprimer"), style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = androidx.compose.ui.graphics.Color.White)
            }
        },
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .background(DS.Palette.surface)
                .clickable(role = Role.Button, onClick = onOpen)
                .semantics(mergeDescendants = true) {}
                .testTag("vault.document.${document.id}")
                .padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconTile(document.category.symbol, size = 40.dp, iconSize = 22.dp)
            Spacer(Modifier.width(DS.Spacing.m))
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                Text(document.title, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
                Text(
                    listOfNotNull(document.category.label, document.beneficiaryName, DateText.short(document.createdAt)).joinToString(" · "),
                    style = DS.Typography.caption, color = DS.Palette.textSecondary,
                )
            }
            Spacer(Modifier.width(DS.Spacing.s))
            if (isOpening) {
                CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            } else {
                Text(fileSize(document.size), style = DS.Typography.caption2, color = DS.Palette.textSecondary)
            }
        }
    }
}

/** iOS `ByteCountFormatter` (file style: decimal units, "184 ko" / "1,2 Mo"). */
private fun fileSize(bytes: Long): String {
    val fr = L10n.code == "fr"
    val units = if (fr) listOf("octets", "ko", "Mo", "Go") else listOf("bytes", "KB", "MB", "GB")
    if (bytes < 1000) return if (bytes == 0L) (if (fr) "Zéro octet" else "Zero KB") else "$bytes ${units[0]}"
    var value = bytes / 1000.0
    var unit = 1
    while (value >= 1000 && unit < units.lastIndex) { value /= 1000; unit++ }
    val text = if (unit == 1) value.roundToLong().toString() else String.format(L10n.locale, "%.1f", value)
    return "$text ${units[unit]}"
}

@Composable
private fun VaultDocumentForm(
    document: PickedDocument,
    initialCategory: VaultCategory,
    onDismiss: () -> Unit,
    onSave: (String, VaultCategory) -> Unit,
) {
    var title by remember { mutableStateOf("") }
    var category by remember { mutableStateOf(initialCategory) }
    FormSheet(
        visible = true,
        title = t("Nouveau document"),
        onDismiss = onDismiss,
        confirmTitle = t("Enregistrer"),
        confirmEnabled = title.isNotBlank(),
        confirmTag = "vault.save",
        onConfirm = { onSave(title.trim(), category); onDismiss() },
    ) {
        FormSection {
            document.preview?.let { preview ->
                Image(
                    preview.asImageBitmap(), contentDescription = t("Aperçu du document"),
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.fillMaxWidth().heightIn(max = 180.dp).padding(DS.Spacing.l),
                )
            }
            FormTextFieldRow(title, { title = it }, t("Titre (ex. Ordonnance Dr Ndiaye)"), divider = document.preview != null, tag = "vault.title")
            FormPickerRow(t("Catégorie"), VaultCategory.entries, category, { it.label }, { category = it }, symbol = { it.symbol })
        }
    }
}
