package sn.assurplus.app.features.claims

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.DialogProperties
import kotlinx.coroutines.launch
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.DeepLinkTarget
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.OCRResult
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.features.claims.NewClaimViewModel.Step
import java.time.Instant

/** The claim declaration sheet (iOS `NewClaimFlowView`): 7 visible steps, close button, local draft. */
@Composable
fun NewClaimFlow(onDismiss: () -> Unit) {
    val env = LocalEnv.current
    val model = remember { NewClaimViewModel(env) }
    val scope = rememberCoroutineScope()
    var showPicker by remember { mutableStateOf(false) }
    val canGoBack = model.step == Step.type || model.step == Step.capture

    LaunchedEffect(Unit) { if (model.beneficiaries.isEmpty()) model.loadOptions() }
    BackHandler(enabled = canGoBack) { model.back() }

    Screen(
        t("Déclarer un sinistre"),
        largeTitle = false,
        scrollable = false,
        spacing = 0.dp,
        contentPadding = PaddingValues(0.dp),
        leading = if (canGoBack) ({ GlassTextButton(t("Retour"), { model.back() }, tint = DS.Palette.accent) }) else null,
        actions = { GlassTextButton(t("Fermer"), onDismiss, tint = DS.Palette.accent, tag = "claim.close") },
    ) {
        if (model.step != Step.done) {
            StepProgress(
                model.progressIndex, Step.entries.size, model.step.title,
                Modifier.padding(start = DS.Spacing.l, end = DS.Spacing.l, top = DS.Spacing.l, bottom = DS.Spacing.s),
            )
        }
        Column(
            Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(DS.Spacing.l),
            verticalArrangement = Arrangement.spacedBy(DS.Spacing.l),
        ) {
            model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
            when (model.step) {
                Step.beneficiary -> BeneficiaryStep(model)
                Step.type -> TypeStep(model)
                Step.capture -> CaptureStep(model) { showPicker = true }
                Step.upload -> UploadStep(model, onRetry = { scope.launch { model.upload() } }, onLater = { model.saveDraft(); onDismiss() })
                Step.ocr -> OcrStep(model)
                Step.review -> ClaimReviewForm(model) { scope.launch { model.submit() } }
                Step.done -> DoneStep(
                    model,
                    onTrack = {
                        model.submitted?.id?.let { id ->
                            env.router.presentedSheet = null
                            env.router.open(DeepLinkTarget(DeepLinkTarget.Kind.claim, id))
                        }
                    },
                    onClose = onDismiss,
                )
            }
        }
    }

    DocumentPicker(showPicker, { showPicker = false }, t("Ajouter le justificatif"), "facture") { document ->
        scope.launch { model.setReceipt(document) }
    }

    val draft = model.pendingDraft
    if (draft != null && model.step == Step.beneficiary) {
        ResumeDraftAlert(
            draft.updatedAt,
            onResume = { scope.launch { model.resumeDraft() } },
            onRestart = { model.discardDraft() },
        )
    }
}

/** iOS alert with "Reprendre" and a destructive "Recommencer"; it cannot be dismissed by tapping outside. */
@Composable
private fun ResumeDraftAlert(updatedAt: Instant, onResume: () -> Unit, onRestart: () -> Unit) {
    AlertDialog(
        onDismissRequest = {},
        properties = DialogProperties(dismissOnBackPress = false, dismissOnClickOutside = false),
        containerColor = DS.Palette.surface,
        title = { Text(t("Reprendre la déclaration ?"), style = DS.Typography.headline, color = DS.Palette.textPrimary) },
        text = {
            Text(
                t("Une déclaration commencée le %@ n'a pas été envoyée.", DateText.dateTime(updatedAt)),
                style = DS.Typography.callout, color = DS.Palette.textSecondary,
            )
        },
        confirmButton = {
            TextButton(onClick = onResume) {
                Text(t("Reprendre"), style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.accent)
            }
        },
        dismissButton = {
            TextButton(onClick = onRestart) { Text(t("Recommencer"), style = DS.Typography.body, color = DS.Palette.danger) }
        },
    )
}

// MARK: - Steps

@Composable
private fun BeneficiaryStep(model: NewClaimViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Text(t("Pour qui sont ces soins ?"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        if (model.isLoadingOptions && model.beneficiaries.isEmpty()) SkeletonCard(lines = 2)
        model.beneficiaries.forEach { beneficiary ->
            SelectableRow(
                beneficiary.fullName,
                isSelected = model.beneficiaryId == beneficiary.id,
                onClick = { model.selectBeneficiary(beneficiary.id) },
                subtitle = beneficiary.relationLabel,
                tag = "claim.beneficiary.${beneficiary.id}",
                leading = { InitialsAvatar(beneficiary.fullName, size = 40.dp) },
            )
        }
    }
}

@Composable
private fun TypeStep(model: NewClaimViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Text(t("Quel type de prestation ?"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        // iOS `LazyVGrid(.adaptive(minimum: 150), spacing: 12)`.
        BoxWithConstraints {
            val spacing = DS.Spacing.m
            val columns = maxOf(1, ((maxWidth + spacing) / (150.dp + spacing)).toInt())
            Column(verticalArrangement = Arrangement.spacedBy(spacing)) {
                model.claimTypes.chunked(columns).forEach { row ->
                    Row(Modifier.height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(spacing)) {
                        row.forEach { type ->
                            val selected = model.typeCode == type.code
                            val shape = RoundedCornerShape(DS.Radius.l)
                            Column(
                                Modifier
                                    .weight(1f)
                                    .clip(shape)
                                    .background(DS.Palette.surface, shape)
                                    .then(if (selected) Modifier.border(2.dp, DS.Palette.accent, shape) else Modifier)
                                    .clickable(role = Role.Button) { model.selectType(type.code) }
                                    .padding(DS.Spacing.s)
                                    .heightIn(min = 96.dp)
                                    .testTag("claim.type.${type.code}"),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(DS.Spacing.s, Alignment.CenterVertically),
                            ) {
                                Icon(sym(type.symbol ?: "doc.text"), null, tint = DS.Palette.accent, modifier = Modifier.size(28.dp))
                                Text(
                                    type.label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold),
                                    color = DS.Palette.textPrimary, textAlign = TextAlign.Center,
                                )
                            }
                        }
                        repeat(columns - row.size) { Spacer(Modifier.weight(1f)) }
                    }
                }
            }
        }
    }
}

@Composable
private fun CaptureStep(model: NewClaimViewModel, onAddReceipt: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Text(t("Photographiez la facture"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        val type = model.selectedType
        if (type != null && type.requiredDocuments.isNotEmpty()) {
            SpacedCard {
                Text(t("Documents demandés"), style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
                type.requiredDocuments.forEach { LabelRow(it, "doc.text", DS.Palette.textPrimary) }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            LabelRow(t("Posez le document à plat, bien éclairé"), "sun.max", DS.Palette.textSecondary)
            LabelRow(t("Les 4 coins doivent être visibles"), "viewfinder", DS.Palette.textSecondary)
            LabelRow(t("Plusieurs pages ? Scannez-les à la suite"), "doc.on.doc", DS.Palette.textSecondary)
        }
        PrimaryButton(t("Ajouter le justificatif"), onAddReceipt, symbol = "camera.viewfinder", tag = "claim.addReceipt")
    }
}

/** iOS `Label(text, systemImage:)` in callout. */
@Composable
private fun LabelRow(text: String, symbol: String, color: androidx.compose.ui.graphics.Color) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Icon(sym(symbol), null, tint = if (color == DS.Palette.textPrimary) DS.Palette.accent else color, modifier = Modifier.size(20.dp))
        Text(text, style = DS.Typography.callout, color = color)
    }
}

@Composable
private fun UploadStep(model: NewClaimViewModel, onRetry: () -> Unit, onLater: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l), horizontalAlignment = Alignment.CenterHorizontally) {
        ReceiptPreview(model)
        SpacedCard {
            Text(t("Envoi du justificatif…"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
            ProgressBar(model.uploadProgress.toFloat(), Modifier.testTag("claim.uploadProgress"), color = DS.Palette.accent)
            Text(
                "${(model.uploadProgress * 100).toInt()} %",
                style = DS.Typography.caption.copy(fontFeatureSettings = "tnum"), color = DS.Palette.textSecondary,
            )
            Text(t("L'envoi reprend automatiquement si la connexion est interrompue."), style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
        if (model.error != null && !model.isWorking) {
            PrimaryButton(t("Réessayer l'envoi"), onRetry)
            SecondaryButton(t("Terminer plus tard"), onLater)
        }
    }
}

@Composable
private fun OcrStep(model: NewClaimViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l), horizontalAlignment = Alignment.CenterHorizontally) {
        ReceiptPreview(model)
        SpacedCard(
            Modifier.testTag("claim.ocr"),
            verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            ProgressBar((model.ocr?.progress ?: 0.1).toFloat(), color = DS.Palette.accent)
            Text(
                model.ocr?.message ?: t("Lecture automatique de votre justificatif…"),
                style = DS.Typography.headline, color = DS.Palette.textPrimary, textAlign = TextAlign.Center,
            )
            Text(t("Cela prend généralement moins de 15 secondes."), style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
            TextLink(t("Saisir manuellement"), { model.startManualEntry() })
        }
    }
}

@Composable
private fun ReceiptPreview(model: NewClaimViewModel) {
    val preview = model.receipt?.preview ?: return
    val bitmap = remember(preview) { preview.asImageBitmap() }
    Image(
        bitmap, t("Aperçu du justificatif"),
        contentScale = ContentScale.Fit,
        modifier = Modifier.heightIn(max = 220.dp).clip(RoundedCornerShape(DS.Radius.m)),
    )
}

@Composable
private fun DoneStep(model: NewClaimViewModel, onTrack: () -> Unit, onClose: () -> Unit) {
    Column(
        Modifier.fillMaxWidth().padding(top = DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.l),
    ) {
        Icon(sym("checkmark.seal.fill"), null, tint = DS.Palette.success, modifier = Modifier.size(76.dp))
        Text(t("Déclaration envoyée"), style = DS.Typography.title, color = DS.Palette.textPrimary, textAlign = TextAlign.Center)
        model.submitted?.number?.let { number ->
            // iOS: a centred VStack sized to its content, leading-aligned in the full-width card.
            Box(Modifier.card()) {
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
                    Text(t("Numéro de sinistre"), style = DS.Typography.callout, color = DS.Palette.textSecondary)
                    SelectionContainer {
                        Text(
                            number,
                            style = DS.Typography.title2.copy(fontWeight = FontWeight.Bold, fontFeatureSettings = "tnum"),
                            color = DS.Palette.textPrimary,
                            modifier = Modifier.testTag("claim.number"),
                        )
                    }
                }
            }
        }
        Text(
            t("Vous serez notifié à chaque étape du traitement."),
            style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
        )
        PrimaryButton(t("Suivre mon sinistre"), onTrack, tag = "claim.track")
        SecondaryButton(t("Fermer"), onClose)
    }
}

// MARK: - Review

/** Pre-filled fields with each confidence score; low-confidence values are highlighted for review. */
@Composable
private fun ClaimReviewForm(model: NewClaimViewModel, onSubmit: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Text(t("Vérifiez les informations"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        val lowCount = model.lowConfidenceCount
        if (lowCount > 0) {
            MessageBanner(Message(Message.Level.warning, t("%lld information(s) à vérifier en priorité (surlignées).", lowCount)))
        } else if (model.ocr?.state == OCRResult.State.done) {
            MessageBanner(Message(Message.Level.success, t("Lecture réussie. Corrigez si besoin avant d'envoyer.")))
        }

        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            model.fields.forEachIndexed { index, field ->
                key(field.key) {
                    ConfidenceField(
                        label = field.label,
                        value = field.value,
                        onValueChange = { model.updateField(index, it) },
                        confidence = field.confidence,
                        isLow = model.isLowConfidence(field.confidence),
                        keyboardType = if (field.key == "total") KeyboardType.Number else KeyboardType.Text,
                        tag = "claim.field.${field.key}",
                    )
                }
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            SectionHeader(t("Actes et médicaments"), actionTitle = t("Ajouter"), action = { model.addLine() })
            if (model.lines.isEmpty()) {
                Text(t("Aucune ligne détectée."), style = DS.Typography.callout, color = DS.Palette.textSecondary)
            }
            model.lines.forEachIndexed { index, line ->
                key(line.id) {
                    val low = model.isLowConfidence(line.confidence)
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .background(if (low) DS.Palette.warningSoft else DS.Palette.surface, RoundedCornerShape(DS.Radius.m))
                            .padding(DS.Spacing.m),
                        verticalArrangement = Arrangement.spacedBy(DS.Spacing.s),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                            PlainField(
                                line.label, { value -> model.updateLine(index) { it.copy(label = value) } },
                                placeholder = t("Acte ou médicament"),
                                style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold),
                                modifier = Modifier.weight(1f),
                            )
                            line.confidence?.let { ConfidenceBadge(it, low) }
                            Icon(
                                sym("trash"), t("Supprimer la ligne"), tint = DS.Palette.danger,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(DS.Radius.s))
                                    .clickable(role = Role.Button) { model.removeLine(line.id) }
                                    .padding(DS.Spacing.xxs)
                                    .size(20.dp),
                            )
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                            MiniField(t("Qté"), line.quantity, { value -> model.updateLine(index) { it.copy(quantity = value) } }, Modifier.weight(1f))
                            MiniField(t("Prix unitaire"), line.unitPrice, { value -> model.updateLine(index) { it.copy(unitPrice = value) } }, Modifier.weight(1f))
                            MiniField(t("Montant"), line.amount, { value -> model.updateLine(index) { it.copy(amount = value) } }, Modifier.weight(1f))
                        }
                    }
                }
            }
        }

        Text(
            t("Les montants pris en charge et le reste à charge seront calculés par votre assureur après analyse."),
            style = DS.Typography.caption, color = DS.Palette.textSecondary,
        )

        PrimaryButton(t("Envoyer la déclaration"), onSubmit, enabled = model.canSubmit, loading = model.isWorking, tag = "claim.submit")
    }
}

@Composable
private fun ConfidenceField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    confidence: Double?,
    isLow: Boolean,
    keyboardType: KeyboardType,
    tag: String,
) {
    val shape = RoundedCornerShape(DS.Radius.m)
    val hint = if (isLow) t("Confiance faible, à vérifier") else null
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.Medium), color = DS.Palette.textSecondary, modifier = Modifier.weight(1f))
            confidence?.let { ConfidenceBadge(it, isLow) }
        }
        Box(
            Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .background(if (isLow) DS.Palette.warningSoft else DS.Palette.surface, shape)
                .border(if (isLow) 1.5.dp else 1.dp, if (isLow) DS.Palette.warning else DS.Palette.border, shape)
                .padding(horizontal = DS.Spacing.m),
            contentAlignment = Alignment.CenterStart,
        ) {
            PlainField(
                value, onValueChange, placeholder = label, style = DS.Typography.body, keyboardType = keyboardType,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = DS.Spacing.m)
                    .semantics { if (hint != null) stateDescription = hint }
                    .testTag(tag),
            )
        }
    }
}

@Composable
private fun ConfidenceBadge(confidence: Double, isLow: Boolean) {
    val color = if (isLow) DS.Palette.warning else DS.Palette.success
    val text = Percent.confidence(confidence)
    Row(
        Modifier.semantics(mergeDescendants = true) { contentDescription = t("Confiance %@", text) },
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs),
    ) {
        Icon(sym(if (isLow) "exclamationmark.triangle.fill" else "checkmark.circle"), null, tint = color, modifier = Modifier.size(14.dp))
        Text(text, style = DS.Typography.caption2.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum"), color = color)
    }
}

@Composable
private fun MiniField(label: String, text: String, onValueChange: (String) -> Unit, modifier: Modifier = Modifier) {
    Column(modifier, verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(label, style = DS.Typography.caption2, color = DS.Palette.textSecondary)
        PlainField(
            text, onValueChange, placeholder = label,
            style = DS.Typography.callout.copy(fontFeatureSettings = "tnum"),
            keyboardType = KeyboardType.Number,
            modifier = Modifier
                .fillMaxWidth()
                .background(DS.Palette.surfaceMuted, RoundedCornerShape(DS.Radius.s))
                .padding(DS.Spacing.s),
        )
    }
}

/** Unstyled single-line text field with a grey placeholder (iOS plain `TextField`). */
@Composable
private fun PlainField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    style: TextStyle,
    modifier: Modifier = Modifier,
    keyboardType: KeyboardType = KeyboardType.Text,
) {
    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        singleLine = true,
        textStyle = style.copy(color = DS.Palette.textPrimary),
        cursorBrush = SolidColor(DS.Palette.accent),
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        modifier = modifier,
        decorationBox = { inner ->
            Box {
                if (value.isEmpty()) Text(placeholder, style = style, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                inner()
            }
        },
    )
}
