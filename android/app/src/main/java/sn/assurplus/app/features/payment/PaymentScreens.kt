package sn.assurplus.app.features.payment

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.hideFromAccessibility
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextRange
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.TextFieldValue
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import kotlinx.serialization.builtins.ListSerializer
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.Route
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.Payment
import sn.assurplus.app.core.model.PaymentMethod
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.DS
import sn.assurplus.app.designsystem.EmptyStateView
import sn.assurplus.app.designsystem.InfoRow
import sn.assurplus.app.designsystem.LabeledField
import sn.assurplus.app.designsystem.LoadableContent
import sn.assurplus.app.designsystem.MessageBanner
import sn.assurplus.app.designsystem.PrimaryButton
import sn.assurplus.app.designsystem.Screen
import sn.assurplus.app.designsystem.SecondaryButton
import sn.assurplus.app.designsystem.SkeletonCard
import sn.assurplus.app.designsystem.StatusBadge
import sn.assurplus.app.designsystem.card
import sn.assurplus.app.designsystem.sym
import sn.assurplus.app.tenant.Tenant

// MARK: - Payment step (subscription flow)

/** Method choice, provider hand-off and server confirmation. Used by the subscription flow. */
@Composable
fun PaymentStepView(model: PaymentViewModel, onSuccess: () -> Unit) {
    LaunchedEffect(model) { if (model.methods.isEmpty()) model.loadMethods() }
    // Owned by the whole step: the payment keeps running when the chooser is replaced by the waiting view.
    val scope = rememberCoroutineScope()
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
            Text(t("Montant à payer"), style = DS.Typography.callout, color = DS.Palette.textSecondary)
            Text(Money.format(model.amount), style = DS.Typography.amount, color = DS.Palette.textPrimary, modifier = Modifier.testTag("payment.amount"))
        }

        model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }

        when (model.phase) {
            PaymentViewModel.Phase.choosing -> PaymentChooser(model, scope)
            PaymentViewModel.Phase.launching, PaymentViewModel.Phase.confirming -> PaymentWaiting(model)
            PaymentViewModel.Phase.finished -> PaymentResult(model, onSuccess)
        }
    }
}

@Composable
private fun PaymentChooser(model: PaymentViewModel, scope: kotlinx.coroutines.CoroutineScope) {
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Text(t("Moyen de paiement"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
        if (model.isLoadingMethods && model.methods.isEmpty()) SkeletonCard(lines = 2)
        model.methods.forEach { method ->
            SelectableRow(
                title = method.label,
                subtitle = method.help,
                leading = { PaymentMethodIcon(method.kind) },
                isSelected = model.selectedMethod == method.code,
                enabled = method.enabled,
                tag = "payment.method.${method.code}",
            ) { model.selectedMethod = method.code }
        }
        if (model.method?.requiresPhone == true) {
            PhoneField(model.phone, { model.phone = it }, error = model.error?.fieldErrors?.get("phone"))
        }
        PrimaryButton(
            t("Payer %@", Money.format(model.amount)), { scope.launch { model.pay() } },
            enabled = model.canPay, tag = "payment.pay",
        )
        Row(horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Icon(sym("lock.shield"), null, tint = DS.Palette.textSecondary, modifier = Modifier.size(16.dp))
            Text(
                t("Paiement sécurisé. Le statut est confirmé par l'opérateur avant activation."),
                style = DS.Typography.caption, color = DS.Palette.textSecondary,
            )
        }
    }
}

@Composable
private fun PaymentWaiting(model: PaymentViewModel) {
    Column(
        Modifier.card().testTag("payment.waiting"),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 3.dp, modifier = Modifier.size(36.dp))
        Text(
            if (model.phase == PaymentViewModel.Phase.launching) t("Ouverture de %@…", model.method?.label ?: t("l'opérateur"))
            else t("Confirmation du paiement en cours…"),
            style = DS.Typography.headline, color = DS.Palette.textPrimary, textAlign = TextAlign.Center,
        )
        Text(
            t("Validez le paiement dans l'application de l'opérateur, puis revenez ici. Ne fermez pas %@.", Tenant.current.displayName),
            style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
        )
        model.payment?.reference?.let { reference ->
            Text(
                t("Référence %@", reference), style = DS.Typography.caption.copy(fontFamily = FontFamily.Monospace),
                color = DS.Palette.textSecondary,
            )
        }
    }
}

@Composable
private fun PaymentResult(model: PaymentViewModel, onSuccess: () -> Unit) {
    val payment = model.payment ?: return
    val scope = rememberCoroutineScope()
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        PaymentSummary(payment)
        when {
            payment.isSuccessful -> PrimaryButton(t("Continuer"), onSuccess, tag = "payment.continue")
            payment.isFinal -> PrimaryButton(t("Réessayer avec un autre moyen"), { model.retry() })
            else -> {
                MessageBanner(Message(Message.Level.info, t("Paiement toujours en attente de confirmation. Vous serez notifié dès sa validation.")))
                SecondaryButton(t("Vérifier à nouveau"), { scope.launch { model.pollUntilFinal() } })
            }
        }
    }
}

@Composable
fun PaymentMethodIcon(kind: PaymentMethod.Kind) {
    val (symbol, color) = when (kind) {
        PaymentMethod.Kind.wave -> "water.waves" to Color(0xFF1DC8FF)
        PaymentMethod.Kind.orangeMoney -> "iphone.gen3" to Color(0xFFFF7900)
        PaymentMethod.Kind.card -> "creditcard" to DS.Palette.teal
        PaymentMethod.Kind.other -> "banknote" to DS.Palette.textSecondary
    }
    Box(
        Modifier.size(40.dp).background(color, RoundedCornerShape(DS.Radius.s)).semantics { hideFromAccessibility() },
        contentAlignment = Alignment.Center,
    ) {
        Icon(sym(symbol), null, tint = Color.White, modifier = Modifier.size(22.dp))
    }
}

@Composable
fun PaymentSummary(payment: Payment) {
    Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(payment.purposeLabel, style = DS.Typography.headline, color = DS.Palette.textPrimary, modifier = Modifier.weight(1f))
            StatusBadge(payment.status, Modifier.testTag("payment.status"))
        }
        InfoRow(t("Montant"), Money.format(payment.amount), emphasized = true)
        InfoRow(t("Moyen"), payment.methodLabel)
        InfoRow(t("Référence"), payment.reference)
        InfoRow(t("Date"), DateText.dateTime(payment.createdAt))
        payment.policyNumber?.let { InfoRow(t("Contrat"), it) }
    }
}

/** Selectable card row (iOS `SelectableRow`): leading view, title / subtitle, check mark or chevron. */
@Composable
internal fun SelectableRow(
    title: String,
    subtitle: String? = null,
    leading: (@Composable () -> Unit)? = null,
    isSelected: Boolean,
    enabled: Boolean = true,
    tag: String? = null,
    onClick: () -> Unit,
) {
    val shape = RoundedCornerShape(DS.Radius.l)
    Row(
        Modifier
            .fillMaxWidth()
            .alpha(if (enabled) 1f else 0.4f)
            .clip(shape)
            .background(DS.Palette.surface, shape)
            .border(if (isSelected) 2.dp else 1.dp, if (isSelected) DS.Palette.accent else DS.Palette.border.copy(alpha = 0.6f), shape)
            .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
            .semantics { selected = isSelected }
            .padding(DS.Spacing.m)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        leading?.invoke()
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary)
            if (subtitle != null) Text(subtitle, style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
        Icon(
            sym(if (isSelected) "checkmark.circle.fill" else "chevron.right"), null,
            tint = if (isSelected) DS.Palette.accent else DS.Palette.textSecondary, modifier = Modifier.size(22.dp),
        )
    }
}

/** `+221` prefix and formatted national number (same behaviour as the iOS `PhoneField`). */
@Composable
internal fun PhoneField(phone: String, onPhoneChange: (String) -> Unit, error: String? = null) {
    var text by remember { mutableStateOf(TextFieldValue(phone, TextRange(phone.length))) }
    val description = t("Numéro de téléphone, indicatif plus 221")
    LabeledField(t("Numéro de téléphone"), error = error) {
        Text("🇸🇳 +221", style = DS.Typography.body, color = DS.Palette.textSecondary, modifier = Modifier.semantics { hideFromAccessibility() })
        Box(Modifier.width(DS.Spacing.s))
        BasicTextField(
            value = text,
            onValueChange = { newValue ->
                val formatted = PhoneNumber.formatInput(newValue.text)
                text = if (formatted == newValue.text) newValue else TextFieldValue(formatted, TextRange(formatted.length))
                onPhoneChange(formatted)
            },
            singleLine = true,
            textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
            cursorBrush = androidx.compose.ui.graphics.SolidColor(DS.Palette.accent),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            modifier = Modifier
                .weight(1f)
                .padding(vertical = DS.Spacing.m)
                .semantics { contentDescription = description }
                .testTag("auth.phone"),
            decorationBox = { inner ->
                Box {
                    if (text.text.isEmpty()) Text("77 123 45 67", style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                    inner()
                }
            },
        )
    }
}

// MARK: - History & detail

@Composable
fun PaymentHistoryScreen(onBack: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val payments = remember { RemoteResource(env.cache, CacheKey.payments, ListSerializer(Payment.serializer())) { env.api.payments() } }
    LaunchedEffect(Unit) { payments.load() }
    Screen(t("Paiements"), onBack = onBack, onRefresh = { payments.refresh() }) {
        LoadableContent(
            payments, retry = { scope.launch { payments.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) { SkeletonCard(lines = 2); SkeletonCard(lines = 2) }
            },
        ) { list ->
            if (list.isEmpty()) {
                EmptyStateView(t("Aucun paiement"), t("Vos paiements de cotisation apparaîtront ici."), symbol = "creditcard")
            } else {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    list.forEach { payment -> PaymentHistoryRow(payment) { env.router.push(Route.Payment(payment.id)) } }
                }
            }
        }
    }
}

@Composable
private fun PaymentHistoryRow(payment: Payment, onClick: () -> Unit) {
    Row(
        Modifier
            .clip(RoundedCornerShape(DS.Radius.l))
            .clickable(role = Role.Button, onClick = onClick)
            .card(padding = DS.Spacing.m),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(payment.purposeLabel, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
            Text("${payment.methodLabel} · ${DateText.short(payment.createdAt)}", style = DS.Typography.caption, color = DS.Palette.textSecondary)
            Text(payment.reference, style = DS.Typography.caption2.copy(fontFamily = FontFamily.Monospace), color = DS.Palette.textSecondary)
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
            Text(
                Money.format(payment.amount),
                style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum"),
                color = DS.Palette.textPrimary, maxLines = 1, softWrap = false,
            )
            StatusBadge(payment.status)
        }
    }
}

@Composable
fun PaymentDetailScreen(paymentId: String, onBack: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val payment = remember(paymentId) { RemoteResource { env.api.payment(paymentId) } }
    LaunchedEffect(paymentId) { payment.load() }
    Screen(t("Paiement"), largeTitle = false, onBack = onBack, onRefresh = { payment.refresh() }) {
        LoadableContent(payment, retry = { scope.launch { payment.load() } }, placeholder = { SkeletonCard(lines = 5) }) { value ->
            PaymentSummary(value)
        }
    }
}
