package sn.assurplus.app.features.support

import android.os.Build
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import sn.assurplus.app.BuildConfig
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.AssistantInfo
import sn.assurplus.app.core.model.AssistantMessage
import sn.assurplus.app.core.model.FeedbackCategory
import sn.assurplus.app.core.model.FeedbackReceipt
import sn.assurplus.app.core.model.FeedbackRequest
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.designsystem.*

private fun wrapError(error: Exception): APIError {
    if (error is CancellationException) throw error
    return APIError.wrap(error)
}

val FeedbackCategory.title: String
    get() = when (this) {
        FeedbackCategory.information -> t("Information")
        FeedbackCategory.suggestion -> t("Suggestion")
        FeedbackCategory.bug -> t("Problème technique")
        FeedbackCategory.other -> t("Autre")
    }

val FeedbackCategory.symbol: String
    get() = when (this) {
        FeedbackCategory.information -> "info.circle"
        FeedbackCategory.suggestion -> "lightbulb"
        FeedbackCategory.bug -> "ladybug"
        FeedbackCategory.other -> "ellipsis.bubble"
    }

// MARK: - Feedback

class FeedbackViewModel(private val env: AppEnvironment) {
    var category by mutableStateOf(FeedbackCategory.suggestion)
    var message by mutableStateOf("")
    var isSending by mutableStateOf(false)
        private set
    var receipt by mutableStateOf<FeedbackReceipt?>(null)
        private set
    var error by mutableStateOf<APIError?>(null)
        private set

    val canSend: Boolean get() = !isSending && message.trim().length in MIN_LENGTH..MAX_LENGTH

    val errorText: String? get() = error?.let { it.fieldErrors.values.firstOrNull() ?: it.userMessage }

    suspend fun send() {
        if (!canSend) return
        isSending = true
        try {
            receipt = env.api.sendFeedback(
                FeedbackRequest(
                    category = category, message = message.trim(), platform = "android",
                    appVersion = "${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})",
                    // Hardware model ("Pixel 8"): helps reproduce a bug, identifies no one.
                    osVersion = Build.VERSION.RELEASE.orEmpty(), deviceModel = "${Build.MANUFACTURER.orEmpty()} ${Build.MODEL.orEmpty()}".trim(),
                    locale = env.language.code,
                )
            )
            error = null
        } catch (e: Exception) {
            error = wrapError(e)
        } finally {
            isSending = false
        }
    }

    companion object {
        const val MAX_LENGTH = 4000
        const val MIN_LENGTH = 5
    }
}

@Composable
fun FeedbackScreen(onDismiss: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val model = remember { FeedbackViewModel(env) }

    if (model.receipt != null) {
        Screen(t("Votre avis"), largeTitle = false, leading = { GlassTextButton(t("Fermer"), onDismiss) }) {
            Spacer(Modifier.height(DS.Spacing.xxl))
            EmptyStateView(
                title = t("Merci, message envoyé"),
                message = t("Notre équipe le lira avec attention. Pour une demande urgente sur votre dossier, contactez votre gestionnaire."),
                modifier = Modifier.testTag("feedback.sent"),
                symbol = "checkmark.seal.fill",
                actionTitle = t("Fermer"),
                action = onDismiss,
            )
        }
        return
    }

    Screen(
        t("Votre avis"), largeTitle = false, modifier = Modifier.imePadding(),
        leading = { GlassTextButton(t("Fermer"), onDismiss) },
        actions = {
            if (model.isSending) {
                CircularProgressIndicator(Modifier.size(24.dp), color = DS.Palette.accent, strokeWidth = 2.dp)
            } else {
                GlassTextButton(t("Envoyer"), { scope.launch { model.send() } }, enabled = model.canSend, tint = DS.Palette.accent, tag = "feedback.send")
            }
        },
    ) {
        FormSection(header = t("Type de message")) {
            FeedbackCategory.entries.forEachIndexed { index, category ->
                FormRow(
                    category.title, symbol = category.symbol, iconTint = DS.Palette.accent, divider = index > 0,
                    onClick = { model.category = category }, tag = "feedback.category.${category.name}",
                    trailing = { if (model.category == category) Icon(sym("checkmark"), null, tint = DS.Palette.accent, modifier = Modifier.size(22.dp)) },
                )
            }
        }
        FormSection(
            header = t("Votre message"),
            footer = t("Envoyé avec la version de l'application, le modèle de l'appareil et la langue, pour nous aider à comprendre. N'indiquez pas d'informations médicales."),
        ) {
            Row(Modifier.fillMaxWidth().heightIn(min = 140.dp).padding(horizontal = DS.Spacing.l), verticalAlignment = Alignment.Top) {
                InputText(
                    model.message, { model.message = it }, t("Décrivez votre remarque, votre idée ou le problème rencontré…"),
                    singleLine = false, tag = "feedback.message",
                )
            }
        }
        Text(
            t("%lld / %lld", model.message.length, FeedbackViewModel.MAX_LENGTH), style = DS.Typography.footnote,
            color = if (model.message.length > FeedbackViewModel.MAX_LENGTH) DS.Palette.danger else DS.Palette.textSecondary,
            modifier = Modifier.padding(horizontal = DS.Spacing.l),
        )
        model.errorText?.let { Text(it, style = DS.Typography.body, color = DS.Palette.danger, modifier = Modifier.padding(horizontal = DS.Spacing.l)) }
    }
}

// MARK: - Assistant

class AssistantViewModel(private val env: AppEnvironment) {
    data class Entry(val kind: Kind, val text: String) {
        enum class Kind { user, assistant, failure }
    }

    var info by mutableStateOf<AssistantInfo?>(null)
        private set
    var loadError by mutableStateOf<APIError?>(null)
        private set
    var entries by mutableStateOf<List<Entry>>(emptyList())
        private set
    var isAnswering by mutableStateOf(false)
        private set
    var draft by mutableStateOf("")

    val canSend: Boolean get() = !isAnswering && draft.isNotBlank()

    suspend fun load() {
        if (info != null) return
        try {
            info = env.api.assistantInfo()
            loadError = null
        } catch (e: Exception) {
            val error = wrapError(e)
            // A backend that does not have the assistant yet answers 404 / 501: say so instead of "cannot load".
            if (error is APIError.Server && error.status in listOf(404, 501)) info = AssistantInfo(available = false) else loadError = error
        }
    }

    suspend fun send() {
        val question = draft.trim().take(MAX_LENGTH)
        draft = ""
        ask(question)
    }

    suspend fun ask(question: String) {
        if (question.isEmpty() || isAnswering) return
        entries = entries + Entry(Entry.Kind.user, question)
        isAnswering = true
        try {
            entries = entries + Entry(Entry.Kind.assistant, env.api.askAssistant(history()).message.content)
        } catch (e: Exception) {
            entries = entries + Entry(Entry.Kind.failure, wrapError(e).userMessage)
        } finally {
            isAnswering = false
        }
    }

    /** Answered exchanges plus the pending question; a question whose answer failed is left out. */
    private fun history(): List<AssistantMessage> {
        val messages = mutableListOf<AssistantMessage>()
        entries.forEachIndexed { index, entry ->
            when (entry.kind) {
                Entry.Kind.user ->
                    if (entries.getOrNull(index + 1)?.kind != Entry.Kind.failure) messages += AssistantMessage(AssistantMessage.Role.user, entry.text)
                Entry.Kind.assistant -> messages += AssistantMessage(AssistantMessage.Role.assistant, entry.text)
                Entry.Kind.failure -> Unit
            }
        }
        return messages.takeLast(HISTORY_LIMIT)
    }

    companion object {
        /** Turns sent back to the server with each question (it keeps no history). */
        const val HISTORY_LIMIT = 12
        const val MAX_LENGTH = 1000
    }
}

@Composable
fun AssistantScreen(onDismiss: () -> Unit, openFeedback: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val model = remember { AssistantViewModel(env) }
    val scroll = rememberScrollState()
    val info = model.info
    LaunchedEffect(Unit) { model.load() }
    LaunchedEffect(model.entries.size, model.isAnswering) { scroll.animateScrollTo(scroll.maxValue) }

    Screen(
        t("Assistant"), largeTitle = false, modifier = Modifier.imePadding(), scrollState = scroll, spacing = DS.Spacing.m,
        leading = { GlassTextButton(t("Fermer"), onDismiss) },
        bottomBar = if (info?.available == true) {
            { AssistantInput(model.draft, { model.draft = it }, model.canSend) { scope.launch { model.send() } } }
        } else null,
    ) {
        val error = model.loadError
        when {
            info == null && error != null -> ErrorStateView(error, retry = { scope.launch { model.load() } })
            info == null -> Box(Modifier.fillMaxWidth().padding(DS.Spacing.xxl), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = DS.Palette.accent)
            }
            !info.available -> EmptyStateView(
                title = t("Assistant indisponible"),
                message = t("L'assistant n'est pas encore activé. Vous pouvez nous écrire, nous vous lirons avec attention."),
                modifier = Modifier.testTag("assistant.unavailable"),
                symbol = "bubble.left.and.exclamationmark.bubble.right",
                actionTitle = t("Envoyer un message"),
                action = openFeedback,
            )
            else -> {
                Card {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                        Icon(sym("sparkles"), null, tint = DS.Palette.accent, modifier = Modifier.size(22.dp))
                        Text(t("Posez une question sur l'application ou votre couverture."), style = DS.Typography.headline, color = DS.Palette.textPrimary)
                    }
                    Spacer(Modifier.height(DS.Spacing.m))
                    Text(info.disclaimer, style = DS.Typography.footnote, color = DS.Palette.textSecondary)
                    if (model.entries.isEmpty()) {
                        info.suggestions.forEachIndexed { index, suggestion ->
                            Spacer(Modifier.height(DS.Spacing.m))
                            Text(
                                suggestion, style = DS.Typography.callout, color = DS.Palette.textPrimary,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(DS.Radius.m))
                                    .background(DS.Palette.accentSoft)
                                    .clickable(role = Role.Button) { scope.launch { model.ask(suggestion) } }
                                    .padding(DS.Spacing.m)
                                    .testTag("assistant.suggestion.$index"),
                            )
                        }
                    }
                }
                model.entries.forEach { Bubble(it) }
                if (model.isAnswering) {
                    Row(
                        Modifier.testTag("assistant.answering"),
                        verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
                    ) {
                        CircularProgressIndicator(Modifier.size(18.dp), color = DS.Palette.accent, strokeWidth = 2.dp)
                        Text(t("L'assistant rédige sa réponse…"), style = DS.Typography.callout, color = DS.Palette.textSecondary)
                    }
                }
            }
        }
    }
}

@Composable
private fun Bubble(entry: AssistantViewModel.Entry) {
    val isUser = entry.kind == AssistantViewModel.Entry.Kind.user
    val (foreground, background) = when (entry.kind) {
        AssistantViewModel.Entry.Kind.user -> DS.Palette.onPrimary to DS.Palette.primary
        AssistantViewModel.Entry.Kind.assistant -> DS.Palette.textPrimary to DS.Palette.surface
        AssistantViewModel.Entry.Kind.failure -> DS.Palette.danger to DS.Palette.dangerSoft
    }
    Row(Modifier.fillMaxWidth(), horizontalArrangement = if (isUser) Arrangement.End else Arrangement.Start) {
        Text(
            entry.text, style = DS.Typography.callout, color = foreground,
            modifier = Modifier
                .padding(start = if (isUser) DS.Spacing.xxl else 0.dp, end = if (isUser) 0.dp else DS.Spacing.xxl)
                .clip(RoundedCornerShape(DS.Radius.l))
                .background(background)
                .padding(DS.Spacing.m)
                .testTag("assistant.message.${entry.kind.name}"),
        )
    }
}

@Composable
private fun AssistantInput(value: String, onValueChange: (String) -> Unit, canSend: Boolean, onSend: () -> Unit) {
    val sendDescription = t("Envoyer")
    Row(
        Modifier.fillMaxWidth().padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.s),
        verticalAlignment = Alignment.Bottom, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        BasicTextField(
            value = value, onValueChange = onValueChange, maxLines = 4,
            textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
            cursorBrush = SolidColor(DS.Palette.accent),
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(DS.Radius.l))
                .background(DS.Palette.surface)
                .border(1.dp, DS.Palette.border, RoundedCornerShape(DS.Radius.l))
                .padding(horizontal = DS.Spacing.m, vertical = DS.Spacing.m)
                .testTag("assistant.input"),
            decorationBox = { inner ->
                Box {
                    if (value.isEmpty()) Text(t("Votre question"), style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                    inner()
                }
            },
        )
        Box(
            Modifier
                .size(44.dp)
                .alpha(if (canSend) 1f else 0.4f)
                .background(DS.Palette.accent, CircleShape)
                .clip(CircleShape)
                .clickable(enabled = canSend, role = Role.Button, onClick = onSend)
                .semantics { contentDescription = sendDescription }
                .testTag("assistant.send"),
            contentAlignment = Alignment.Center,
        ) {
            Icon(sym("arrow.up"), null, tint = DS.Palette.surface, modifier = Modifier.size(22.dp))
        }
    }
}

/** Donut-shaped assistant button: a brand-coloured ring that keeps circling around a dark centre (iOS `AssistantOrb`). */
@Composable
fun AssistantOrb(onClick: () -> Unit, modifier: Modifier = Modifier) {
    val description = t("Assistant")
    val angle by rememberInfiniteTransition(label = "orb").animateFloat(
        0f, 360f, infiniteRepeatable(tween(4000, easing = LinearEasing), RepeatMode.Restart), label = "angle",
    )
    Box(
        modifier
            .size(54.dp)
            .shadow(12.dp, CircleShape, ambientColor = Color.Black.copy(alpha = 0.28f), spotColor = Color.Black.copy(alpha = 0.28f))
            .background(DS.Palette.teal.copy(alpha = 0.96f), CircleShape)
            .clip(CircleShape)
            .clickable(remember { MutableInteractionSource() }, null, role = Role.Button, onClick = onClick)
            .semantics { contentDescription = description }
            .testTag("assistant.orb"),
        contentAlignment = Alignment.Center,
    ) {
        Box(
            Modifier
                .fillMaxSize()
                .rotate(angle)
                .border(
                    9.dp,
                    Brush.sweepGradient(listOf(DS.Palette.mint, DS.Palette.mint.copy(alpha = 0.15f), DS.Palette.tealMid, DS.Palette.mint)),
                    CircleShape,
                )
        )
        Icon(sym("sparkles"), null, tint = DS.Palette.mint, modifier = Modifier.size(18.dp))
    }
}
