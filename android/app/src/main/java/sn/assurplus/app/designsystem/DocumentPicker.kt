package sn.assurplus.app.designsystem

import android.app.Activity
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.IntentSenderRequest
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions
import com.google.mlkit.vision.documentscanner.GmsDocumentScanning
import com.google.mlkit.vision.documentscanner.GmsDocumentScanningResult
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.upload.ImageCompressor
import sn.assurplus.app.core.upload.UploadFile
import sn.assurplus.app.mock.MockDocuments

// MARK: - Action sheet (iOS confirmationDialog)

data class SheetAction(val title: String, val destructive: Boolean = false, val tag: String? = null, val onClick: () -> Unit)

/**
 * iOS action sheet: a floating rounded group of actions with a title, and a separate "Annuler" button below.
 */
@Composable
fun ActionSheet(visible: Boolean, title: String?, actions: List<SheetAction>, onDismiss: () -> Unit, message: String? = null) {
    if (!visible) return
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        BackHandler(onBack = onDismiss)
        var shown by remember { mutableStateOf(false) }
        LaunchedEffect(Unit) { shown = true }
        Box(
            Modifier.fillMaxSize().clickable(remember { MutableInteractionSource() }, null, onClick = onDismiss),
            contentAlignment = Alignment.BottomCenter,
        ) {
            AnimatedVisibility(shown, enter = slideInVertically { it } + fadeIn(), exit = slideOutVertically { it } + fadeOut()) {
                Column(
                    Modifier.navigationBarsPadding().padding(horizontal = DS.Spacing.s).padding(bottom = DS.Spacing.s),
                    verticalArrangement = Arrangement.spacedBy(DS.Spacing.s),
                ) {
                    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(DS.Palette.surface.copy(alpha = 0.98f))) {
                        if (title != null || message != null) {
                            Column(Modifier.fillMaxWidth().padding(DS.Spacing.l), horizontalAlignment = Alignment.CenterHorizontally) {
                                if (title != null) Text(title, style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
                                if (message != null) Text(message, style = DS.Typography.footnote, color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
                            }
                        }
                        actions.forEachIndexed { index, action ->
                            if (index > 0 || title != null || message != null) Hairline()
                            SheetButton(action.title, if (action.destructive) DS.Palette.destructive else DS.Palette.accent, FontWeight.Normal, action.tag) {
                                onDismiss(); action.onClick()
                            }
                        }
                    }
                    Box(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(DS.Palette.surface)) {
                        SheetButton(t("Annuler"), DS.Palette.accent, FontWeight.SemiBold, null, onDismiss)
                    }
                }
            }
        }
    }
}

@Composable
private fun SheetButton(title: String, color: Color, weight: FontWeight, tag: String?, onClick: () -> Unit) {
    Box(
        Modifier
            .fillMaxWidth()
            .heightIn(min = 57.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        contentAlignment = Alignment.Center,
    ) {
        Text(title, style = DS.Typography.title3.copy(fontWeight = weight), color = color)
    }
}

// MARK: - Document picker

/** A picked document, already compressed for upload, with an optional preview image. */
class PickedDocument(val file: UploadFile, val preview: Bitmap?)

object DocumentSource {
    /** Builds an upload from images (scanner pages, photo): merged, JPEG, ≤1600 px. */
    fun fromImages(images: List<Bitmap>, baseName: String): PickedDocument? {
        val merged = ImageCompressor.merge(images) ?: return null
        val data = ImageCompressor.jpeg(merged)
        return PickedDocument(UploadFile(data, "$baseName.jpg", "image/jpeg"), BitmapFactory.decodeByteArray(data, 0, data.size))
    }

    fun fromUri(context: Context, uri: Uri, baseName: String): PickedDocument? {
        val resolver = context.contentResolver
        val data = runCatching { resolver.openInputStream(uri)?.use { it.readBytes() } }.getOrNull() ?: return null
        if (resolver.getType(uri) == "application/pdf") return PickedDocument(UploadFile(data, "$baseName.pdf", "application/pdf"), null)
        val image = BitmapFactory.decodeByteArray(data, 0, data.size) ?: return null
        return fromImages(listOf(image), baseName)
    }
}

/**
 * Offers: document scanner (ML Kit, edge detection, multi-page), photo library (Photo Picker, no permission),
 * Files — and, in the mock flavor, a sample invoice so the flow works on the emulator and in UI tests.
 */
@Composable
fun DocumentPicker(
    visible: Boolean,
    onDismiss: () -> Unit,
    title: String,
    baseName: String,
    allowsPDF: Boolean = true,
    onPick: (PickedDocument) -> Unit,
) {
    val context = LocalContext.current
    val env = LocalEnv.current

    val scanner = rememberLauncherForActivityResult(ActivityResultContracts.StartIntentSenderForResult()) { result ->
        if (result.resultCode != Activity.RESULT_OK) return@rememberLauncherForActivityResult
        val pages = GmsDocumentScanningResult.fromActivityResultIntent(result.data)?.pages.orEmpty()
        val bitmaps = pages.mapNotNull { page -> context.contentResolver.openInputStream(page.imageUri)?.use { BitmapFactory.decodeStream(it) } }
        DocumentSource.fromImages(bitmaps, baseName)?.let(onPick)
    }
    val photos = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        uri?.let { DocumentSource.fromUri(context, it, baseName) }?.let(onPick)
    }
    val files = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        uri?.let { DocumentSource.fromUri(context, it, baseName) }?.let(onPick)
    }

    val actions = buildList {
        add(SheetAction(t("Scanner le document"), tag = "picker.scan") {
            val activity = context as? Activity ?: return@SheetAction
            val options = GmsDocumentScannerOptions.Builder()
                .setGalleryImportAllowed(false)
                .setResultFormats(GmsDocumentScannerOptions.RESULT_FORMAT_JPEG)
                .setScannerMode(GmsDocumentScannerOptions.SCANNER_MODE_FULL)
                .build()
            GmsDocumentScanning.getClient(options).getStartScanIntent(activity)
                .addOnSuccessListener { scanner.launch(IntentSenderRequest.Builder(it).build()) }
        })
        add(SheetAction(t("Choisir une photo"), tag = "picker.photo") {
            photos.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
        })
        add(SheetAction(t("Importer un fichier"), tag = "picker.file") {
            files.launch(if (allowsPDF) arrayOf("image/*", "application/pdf") else arrayOf("image/*"))
        })
        if (env.isMock) {
            add(SheetAction(t("Utiliser une facture d'exemple"), tag = "picker.sample") {
                DocumentSource.fromImages(listOf(MockDocuments.sampleInvoice()), baseName)?.let(onPick)
            })
        }
    }
    ActionSheet(visible, title, actions, onDismiss)
}
