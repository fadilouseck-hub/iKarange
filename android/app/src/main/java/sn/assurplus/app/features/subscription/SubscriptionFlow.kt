package sn.assurplus.app.features.subscription

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.hideFromAccessibility
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppTab
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Gender
import sn.assurplus.app.core.model.HealthQuestionnaire
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.Product
import sn.assurplus.app.core.model.StatusTone
import sn.assurplus.app.designsystem.Card
import sn.assurplus.app.designsystem.DS
import sn.assurplus.app.designsystem.ErrorStateView
import sn.assurplus.app.designsystem.GlassTextButton
import sn.assurplus.app.designsystem.Hairline
import sn.assurplus.app.designsystem.InfoRow
import sn.assurplus.app.designsystem.InitialsAvatar
import sn.assurplus.app.designsystem.InputText
import sn.assurplus.app.designsystem.LabeledField
import sn.assurplus.app.designsystem.MessageBanner
import sn.assurplus.app.designsystem.PrimaryButton
import sn.assurplus.app.designsystem.Screen
import sn.assurplus.app.designsystem.SecondaryButton
import sn.assurplus.app.designsystem.SectionHeader
import sn.assurplus.app.designsystem.SegmentedPicker
import sn.assurplus.app.designsystem.SkeletonBlock
import sn.assurplus.app.designsystem.SkeletonCard
import sn.assurplus.app.designsystem.StatusBadge
import sn.assurplus.app.designsystem.StepProgress
import sn.assurplus.app.designsystem.ToggleRow
import sn.assurplus.app.designsystem.card
import sn.assurplus.app.designsystem.sym
import sn.assurplus.app.features.payment.PaymentStepView
import sn.assurplus.app.tenant.Tenant

private typealias Step = SubscriptionViewModel.Step

/** Subscription flow presented as a sheet (iOS `SubscriptionFlowView`). */
@Composable
fun SubscriptionFlow(onDismiss: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val model = remember { SubscriptionViewModel(env, scope) }
    var showDependentForm by remember { mutableStateOf(false) }

    LaunchedEffect(model) { model.load() }
    // iOS `.onChange(of: quoteRequest)`: recompute the premium while the user adjusts the coverage.
    LaunchedEffect(model) {
        snapshotFlow { model.quoteRequest }.drop(1).collect {
            if (model.step >= Step.guarantees && model.step <= Step.premium) model.scheduleQuote()
        }
    }
    // System back goes to the previous step (data is kept), otherwise closes the flow like "Fermer".
    BackHandler { if (model.canGoBack) model.back() else onDismiss() }

    Screen(
        t("Souscription"),
        largeTitle = false,
        scrollable = false,
        contentPadding = PaddingValues(0.dp),
        spacing = 0.dp,
        leading = {
            if (model.canGoBack) {
                GlassTextButton(t("Retour"), { model.back() }, tint = DS.Palette.accent, tag = "subscription.back")
            }
        },
        actions = { GlassTextButton(t("Fermer"), onDismiss, tint = DS.Palette.accent) },
    ) {
        if (model.step != Step.confirmation) {
            StepProgress(
                model.progressIndex, Step.entries.size, model.step.title,
                Modifier.padding(start = DS.Spacing.l, end = DS.Spacing.l, top = DS.Spacing.l, bottom = DS.Spacing.s),
            )
        }
        val scrollState = remember(model.step) { ScrollState(0) }
        Column(
            Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(scrollState)
                .padding(DS.Spacing.l),
            verticalArrangement = Arrangement.spacedBy(DS.Spacing.l),
        ) {
            model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
            when (model.step) {
                Step.identification -> IdentificationStep(model)
                Step.personal -> PersonalStep(model)
                Step.health -> HealthStep(model)
                Step.guarantees -> GuaranteesStep(model) { showDependentForm = true }
                Step.premium -> PremiumStep(model)
                Step.validation -> ValidationStep(model)
                Step.payment -> model.payment?.let { payment ->
                    PaymentStepView(payment) { scope.launch { model.paymentSucceeded() } }
                }
                Step.confirmation -> ConfirmationStep(model)
            }
        }
        if (model.step == Step.guarantees || model.step == Step.premium) QuoteBar(model)
        if (model.step < Step.payment) {
            Box(Modifier.fillMaxWidth().background(DS.Palette.background).padding(DS.Spacing.l)) {
                PrimaryButton(
                    if (model.step == Step.validation) t("Valider et payer") else t("Continuer"),
                    { scope.launch { model.next() } },
                    enabled = model.canContinue,
                    loading = model.isCreatingPolicy,
                    tag = "subscription.next",
                )
            }
        }
    }

    if (showDependentForm) {
        DependentFormSheet(onDismiss = { showDependentForm = false }) { model.addDependent(it) }
    }
}

// MARK: - Steps

@Composable
private fun IdentificationStep(model: SubscriptionViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Text(t("Souscrivez en moins de 3 minutes"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        model.user?.let { user ->
            Card {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    InfoRow(t("Souscripteur"), user.fullName, emphasized = true)
                    InfoRow(t("Téléphone vérifié"), PhoneNumber.display(user.phone))
                }
            }
        }
        Text(
            t("Vous allez renseigner vos informations, répondre au questionnaire de santé, choisir vos garanties puis payer en ligne."),
            style = DS.Typography.callout, color = DS.Palette.textSecondary,
        )
    }
}

@Composable
private fun PersonalStep(model: SubscriptionViewModel) {
    var showDatePicker by remember { mutableStateOf(false) }
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        LabeledField(t("Date de naissance")) {
            Text(
                DateText.day(model.birthDate), style = DS.Typography.body, color = DS.Palette.textPrimary,
                modifier = Modifier
                    .weight(1f)
                    .clickable(role = Role.Button) { showDatePicker = true }
                    .padding(vertical = DS.Spacing.m),
            )
        }
        SegmentedPicker(Gender.entries, model.gender, { it.label }, { model.gender = it })
        LabeledField(t("E-mail (facultatif)")) {
            InputText(model.email, { model.email = it }, "nom@exemple.com", keyboardType = KeyboardType.Email)
        }
        LabeledField(t("Ville")) {
            InputText(model.city, { model.city = it }, "Dakar")
        }
    }
    if (showDatePicker) {
        BirthDatePickerDialog(model.birthDate, onDismiss = { showDatePicker = false }) { model.birthDate = it }
    }
}

@Composable
private fun HealthStep(model: SubscriptionViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Text(
            t("Vos réponses sont confidentielles et transmises uniquement à l'assureur."),
            style = DS.Typography.callout, color = DS.Palette.textSecondary,
        )
        if (model.questionnaire == null) SkeletonCard(lines = 4)
        model.visibleQuestions.forEach { question ->
            QuestionView(question, model.answers[question.id] ?: "") { model.answers[question.id] = it }
        }
    }
}

@Composable
private fun QuestionView(question: HealthQuestionnaire.Question, answer: String, onAnswer: (String) -> Unit) {
    Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Text(question.label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
        question.help?.let { Text(it, style = DS.Typography.caption, color = DS.Palette.textSecondary) }
        when (question.kind) {
            HealthQuestionnaire.Question.Kind.boolean -> SegmentedPicker(
                listOf("true", "false"), answer, { if (it == "true") t("Oui") else t("Non") }, onAnswer,
                tag = "question.${question.id}",
            )
            HealthQuestionnaire.Question.Kind.singleChoice -> {
                val options = listOf("") + question.options.orEmpty().map { it.code }
                MenuPicker(
                    options, answer,
                    { code -> if (code.isEmpty()) t("Choisir…") else question.options.orEmpty().firstOrNull { it.code == code }?.label ?: code },
                    onAnswer,
                )
            }
            HealthQuestionnaire.Question.Kind.number -> RoundedBorderField(answer, onAnswer, keyboardType = KeyboardType.Number)
            HealthQuestionnaire.Question.Kind.text -> RoundedBorderField(answer, onAnswer, singleLine = false)
        }
    }
}

@Composable
private fun GuaranteesStep(model: SubscriptionViewModel, onAddDependent: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Text(t("Formule"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
        model.products.forEach { product ->
            ProductCard(product, model.productId == product.id, Modifier.testTag("subscription.product.${product.id}")) { model.select(product) }
        }
        model.product?.let { product ->
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                Text(t("Taux de couverture"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
                SegmentedPicker(
                    product.coverageRates, model.coverageRate ?: 0, { Percent.format(it) }, { model.coverageRate = it },
                    tag = "subscription.rate",
                )
            }
            if (product.territorialities.size > 1) {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    Text(t("Territorialité"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
                    SegmentedPicker(
                        product.territorialities.map { it.code }, model.territoriality ?: "",
                        { code -> product.territorialities.firstOrNull { it.code == code }?.label ?: code },
                        { model.territoriality = it },
                    )
                }
            }
            Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                SectionHeader(
                    t("Ayants droit"),
                    actionTitle = if (model.canAddDependent) t("Ajouter") else null,
                    action = onAddDependent,
                )
                if (model.dependents.isEmpty()) {
                    Text(t("Ajoutez votre conjoint(e) ou vos enfants pour les couvrir."), style = DS.Typography.callout, color = DS.Palette.textSecondary)
                }
                model.dependents.forEach { member ->
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                        InitialsAvatar("${member.firstName} ${member.lastName}", size = 36.dp)
                        Column(Modifier.weight(1f)) {
                            Text(
                                "${member.firstName} ${member.lastName}",
                                style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary,
                            )
                            Text(
                                "${member.relation.label} · ${DateText.day(member.birthDate)}",
                                style = DS.Typography.caption, color = DS.Palette.textSecondary,
                            )
                        }
                        val description = t("Retirer %@", member.firstName)
                        Box(
                            Modifier
                                .size(44.dp)
                                .clip(RoundedCornerShape(DS.Radius.s))
                                .clickable(role = Role.Button) { model.removeDependent(member.id) }
                                .semantics { contentDescription = description },
                            contentAlignment = Alignment.Center,
                        ) {
                            Icon(sym("minus.circle"), null, tint = DS.Palette.danger, modifier = Modifier.size(22.dp))
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ProductCard(product: Product, isSelected: Boolean, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val shape = RoundedCornerShape(DS.Radius.l)
    Column(
        modifier
            .fillMaxWidth()
            .clip(shape)
            .background(DS.Palette.surface, shape)
            .border(if (isSelected) 2.dp else 1.dp, if (isSelected) DS.Palette.accent else DS.Palette.border, shape)
            .clickable(role = Role.Button, onClick = onClick)
            .semantics { selected = isSelected }
            .padding(DS.Spacing.l),
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Text(product.name, style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = DS.Palette.textPrimary)
            if (product.highlight == true) StatusBadge(t("Recommandée"), StatusTone.success)
            Box(Modifier.weight(1f))
            Icon(
                sym(if (isSelected) "checkmark.circle.fill" else "circle"), null,
                tint = if (isSelected) DS.Palette.accent else DS.Palette.border, modifier = Modifier.size(28.dp),
            )
        }
        product.tagline?.let { Text(it, style = DS.Typography.callout, color = DS.Palette.textSecondary) }
        product.premiumFrom?.let {
            Text(t("À partir de %@ / an", Money.format(it)), style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
        }
        product.guarantees.forEach { guarantee ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                Icon(sym("checkmark"), null, tint = DS.Palette.success, modifier = Modifier.size(20.dp))
                Text(
                    guarantee.label + (guarantee.limitLabel?.let { " — $it" } ?: ""),
                    style = DS.Typography.caption, color = DS.Palette.textPrimary,
                )
            }
        }
    }
}

@Composable
private fun PremiumStep(model: SubscriptionViewModel) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        val quote = model.quote
        val quoteError = model.quoteError
        when {
            quote != null -> {
                quote.messages.forEach { MessageBanner(it) }
                Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    Text(t("Détail de la prime"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
                    quote.perMember.forEach { InfoRow(it.label, Money.format(it.amount)) }
                    quote.surcharges.forEach { InfoRow(it.label, "+ " + Money.format(it.amount)) }
                    quote.fees.forEach { InfoRow(it.label, Money.format(it.amount)) }
                    Hairline()
                    InfoRow(t("Total %@", quote.periodLabel), Money.format(quote.totalPremium), emphasized = true)
                    Text(t("Devis valable jusqu'au %@", DateText.day(quote.validUntil)), style = DS.Typography.caption, color = DS.Palette.textSecondary)
                }
            }
            quoteError != null -> ErrorStateView(quoteError, { model.scheduleQuote() })
            else -> SkeletonCard(lines = 4)
        }
    }
}

@Composable
private fun ValidationStep(model: SubscriptionViewModel) {
    val product = model.product ?: return
    val quote = model.quote ?: return
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Text(t("Récapitulatif"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
            InfoRow(t("Formule"), product.name)
            InfoRow(t("Couverture"), Percent.format(model.coverageRate ?: 0))
            InfoRow(t("Bénéficiaires"), "${model.dependents.size + 1}")
            InfoRow(t("Prime %@", quote.periodLabel), Money.format(quote.totalPremium), emphasized = true)
        }
        Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Text(t("Conditions particulières"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
            quote.specialConditions.forEach { Text(t("• %@", it), style = DS.Typography.callout, color = DS.Palette.textPrimary) }
            Text(t("Version %@", quote.conditionsVersion), style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
        ToggleRow(
            t("J'ai lu et j'accepte les conditions particulières"), model.acceptedConditions, { model.acceptedConditions = it },
            tag = "subscription.acceptConditions",
        )
    }
}

@Composable
private fun ConfirmationStep(model: SubscriptionViewModel) {
    val env = LocalEnv.current
    Column(
        Modifier.fillMaxWidth().padding(top = DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.l),
    ) {
        Icon(sym("checkmark.seal.fill"), null, tint = DS.Palette.success, modifier = Modifier.size(80.dp).semantics { hideFromAccessibility() })
        Text(
            t("Bienvenue chez %@ !", Tenant.current.displayName), style = DS.Typography.title, color = DS.Palette.textPrimary,
            textAlign = TextAlign.Center,
        )
        model.createdPolicy?.let { policy ->
            Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                InfoRow(t("N° de contrat"), policy.number, Modifier.testTag("subscription.policyNumber"), emphasized = true)
                InfoRow(t("Début de couverture"), DateText.day(policy.startDate))
            }
        }
        Text(
            t("Votre contrat est émis et votre carte tiers-payant est disponible. Les conditions particulières sont consultables dans « Mon contrat »."),
            style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
        )
        PrimaryButton(t("Voir ma carte"), {
            env.router.presentedSheet = null
            env.router.selectedTab = AppTab.card
        }, tag = "subscription.showCard")
        SecondaryButton(t("Aller à l'accueil"), { env.router.presentedSheet = null })
    }
}

/** Live premium while the user adjusts the coverage. */
@Composable
private fun QuoteBar(model: SubscriptionViewModel) {
    Column(Modifier.fillMaxWidth().background(DS.Palette.surface)) {
        Hairline()
        Row(
            Modifier.fillMaxWidth().padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(t("Prime estimée"), style = DS.Typography.caption, color = DS.Palette.textSecondary)
                val quote = model.quote
                if (quote != null) {
                    Text(
                        Money.format(quote.totalPremium), style = DS.Typography.amountSmall, color = DS.Palette.textPrimary,
                        modifier = Modifier.testTag("subscription.total"),
                    )
                    Text(quote.periodLabel, style = DS.Typography.caption2, color = DS.Palette.textSecondary)
                } else {
                    SkeletonBlock(height = 20.dp, width = 120.dp)
                }
            }
            val quote = model.quote
            if (model.isQuoting) {
                CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            } else if (quote != null && !quote.eligible) {
                StatusBadge(t("Non éligible"), StatusTone.danger)
            }
        }
    }
}
