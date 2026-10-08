package sn.assurplus.app.features.home

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.Layout
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Constraints
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppTab
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.Route
import sn.assurplus.app.app.Sheet
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Dashboard
import sn.assurplus.app.core.model.DependentSummary
import sn.assurplus.app.core.model.Limits
import sn.assurplus.app.core.model.Permission
import sn.assurplus.app.core.model.PolicySummary
import sn.assurplus.app.core.model.StatusTone
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*

@Composable
fun HomeScreen() {
    val env = LocalEnv.current
    val dashboard = remember { RemoteResource(env.cache, CacheKey.dashboard, Dashboard.serializer()) { env.api.dashboard() } }
    val scope = rememberCoroutineScope()
    LaunchedEffect(Unit) { dashboard.load() }
    // Back from a flow that changed data (claim, subscription): refresh.
    var previousSheet by remember { mutableStateOf(env.router.presentedSheet) }
    LaunchedEffect(env.router.presentedSheet) {
        if (previousSheet != null && env.router.presentedSheet == null) dashboard.load()
        previousSheet = env.router.presentedSheet
    }

    val name = env.session.user?.firstName ?: dashboard.value?.fullName.orEmpty()
    val unread = dashboard.value?.unreadNotifications ?: 0
    Screen(
        title = t("Bonjour %@", name),
        onRefresh = { dashboard.refresh() },
        actions = {
            GlassButton(
                "bell", { env.router.homePath.add(Route.Notifications) },
                description = t("Notifications, %lld non lues", unread), badge = unread, tag = "home.notifications",
            )
        },
    ) {
        LoadableContent(
            dashboard,
            retry = { scope.launch { dashboard.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    SkeletonCard(lines = 2)
                    SkeletonCard(lines = 4)
                    SkeletonCard(lines = 3)
                }
            },
        ) { data -> DashboardSections(data) }
    }
}

@Composable
private fun DashboardSections(data: Dashboard) {
    val env = LocalEnv.current
    val router = env.router
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
        data.alerts.forEach { MessageBanner(it) }

        if (data.policy != null) PolicyCard(data.policy, data.memberNumber)
        else if (env.session.can(Permission.subscriptionCreate)) SubscribeCard()

        data.limits?.let { LimitsCard(it) }

        QuickActions(hasPolicy = data.policy != null)

        if (data.dependents.isNotEmpty() && env.session.can(Permission.familyView)) {
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                SectionHeader(t("Ma famille"), actionTitle = t("Gérer")) { router.homePath.add(Route.Family) }
                Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    data.dependents.forEach { DependentChip(it) }
                }
            }
        }

        if (env.session.can(Permission.claimsView)) {
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                SectionHeader(
                    t("Derniers sinistres"),
                    actionTitle = if (data.recentClaims.isEmpty()) null else t("Tout voir"),
                ) { router.selectedTab = AppTab.claims }
                if (data.recentClaims.isEmpty()) {
                    Text(t("Aucun sinistre déclaré pour le moment."), style = DS.Typography.callout, color = DS.Palette.textSecondary, modifier = Modifier.card())
                } else {
                    Column(Modifier.card(padding = DS.Spacing.m)) {
                        data.recentClaims.forEachIndexed { index, claim ->
                            ClaimRow(
                                claim,
                                Modifier.clickable(role = Role.Button) {
                                    router.selectedTab = AppTab.claims
                                    router.claimsPath.add(Route.Claim(claim.id))
                                },
                            )
                            if (index < data.recentClaims.lastIndex) Hairline()
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun PolicyCard(policy: PolicySummary, memberNumber: String?) {
    val env = LocalEnv.current
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(DS.Radius.l))
            .background(Brush.linearGradient(listOf(DS.Palette.teal, DS.Palette.tealMid)))
            .clickable(role = Role.Button) { env.router.homePath.add(Route.Policy) }
            .padding(DS.Spacing.l)
            .semantics { contentDescription = t("Ouvre le détail du contrat") }
            .testTag("home.policyCard"),
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                Text(t("Formule %@", policy.formulaName), style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = Color.White)
                Text(policy.productName, style = DS.Typography.callout, color = Color.White.copy(alpha = 0.85f))
            }
            Text(
                policy.status.label, style = DS.Typography.caption.copy(fontWeight = FontWeight.SemiBold), color = Color.White,
                modifier = Modifier.background(Color.White.copy(alpha = 0.18f), CircleShape).padding(horizontal = DS.Spacing.s, vertical = DS.Spacing.xs),
            )
        }
        Hairline(color = Color.White.copy(alpha = 0.3f))
        Row {
            LabelValue(t("Contrat"), policy.number)
            Spacer(Modifier.weight(1f))
            LabelValue(t("Couverture"), Percent.format(policy.coverageRate))
        }
        Row {
            LabelValue(t("Début"), DateText.day(policy.startDate))
            Spacer(Modifier.weight(1f))
            LabelValue(t("Fin"), DateText.day(policy.endDate))
        }
        memberNumber?.let { LabelValue(t("N° d'assuré"), it) }
    }
}

@Composable
private fun LabelValue(label: String, value: String) {
    Column(Modifier.semantics(mergeDescendants = true) {}, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
        Text(label, style = DS.Typography.caption, color = Color.White.copy(alpha = 0.75f))
        Text(value, style = DS.Typography.callout.copy(fontWeight = FontWeight.SemiBold), color = Color.White)
    }
}

@Composable
private fun SubscribeCard() {
    val env = LocalEnv.current
    Card(Modifier, DS.Spacing.l) {
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            Icon(sym("shield.lefthalf.filled.badge.checkmark"), null, tint = DS.Palette.accent, modifier = Modifier.size(36.dp))
            Text(t("Protégez-vous et votre famille"), style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = DS.Palette.textPrimary)
            Text(
                t("Choisissez une formule, obtenez votre devis et payez par Wave, Orange Money ou carte. Moins de 3 minutes."),
                style = DS.Typography.callout, color = DS.Palette.textSecondary,
            )
            PrimaryButton(t("Souscrire maintenant"), { env.router.presentedSheet = Sheet.subscription }, tag = "home.subscribe")
        }
    }
}

@Composable
fun LimitsCard(limits: Limits) {
    val consumedRatio = if (limits.annualLimit > 0) (limits.consumed.toDouble() / limits.annualLimit).coerceIn(0.0, 1.0).toFloat() else 0f
    Column(Modifier.card(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Text(t("Plafond disponible"), style = DS.Typography.subheadline, color = DS.Palette.textSecondary)
        Text(Money.format(limits.remaining), style = DS.Typography.amount, color = DS.Palette.textPrimary, modifier = Modifier.testTag("home.remainingLimit"))
        ProgressBar(
            consumedRatio,
            color = if (consumedRatio > 0.85f) DS.Palette.warning else DS.Palette.accent,
            modifier = Modifier.semantics { contentDescription = t("Plafond consommé") + " ${(consumedRatio * 100).toInt()} %" },
        )
        Text(t("sur un plafond annuel de %@", Money.format(limits.annualLimit)), style = DS.Typography.caption, color = DS.Palette.textSecondary)
        Row(horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            AmountTile(t("Consommé"), limits.consumed, Modifier.weight(1f))
            AmountTile(t("Remboursé"), limits.reimbursed, Modifier.weight(1f), tone = StatusTone.success)
        }
    }
}

private data class QuickAction(val id: String, val title: String, val symbol: String, val perform: () -> Unit)

@Composable
private fun QuickActions(hasPolicy: Boolean) {
    val env = LocalEnv.current
    val router = env.router
    val session = env.session
    val actions = buildList {
        if (hasPolicy && session.can(Permission.cardView)) add(QuickAction("card", t("Ma carte"), "qrcode") { router.selectedTab = AppTab.card })
        if (hasPolicy && session.can(Permission.claimsCreate)) add(QuickAction("claim", t("Déclarer"), "camera.viewfinder") { router.presentedSheet = Sheet.newClaim })
        if (hasPolicy && session.can(Permission.familyView)) add(QuickAction("family", t("Famille"), "person.3") { router.homePath.add(Route.Family) })
        if (hasPolicy && session.can(Permission.policyView)) add(QuickAction("policy", t("Contrat"), "doc.text") { router.homePath.add(Route.Policy) })
        if (session.can(Permission.paymentsView)) add(QuickAction("payments", t("Paiements"), "creditcard") { router.homePath.add(Route.Payments) })
        if (session.can(Permission.vaultView)) add(QuickAction("vault", t("Coffre santé"), "lock.doc") { router.homePath.add(Route.Vault) })
    }
    AdaptiveGrid(minCellWidth = 100.dp, spacing = DS.Spacing.m) {
        actions.forEach { action ->
            Column(
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 96.dp)
                    .clip(RoundedCornerShape(DS.Radius.l))
                    .background(DS.Palette.surface)
                    .clickable(role = Role.Button, onClick = action.perform)
                    .padding(vertical = DS.Spacing.m)
                    .testTag("home.action.${action.id}"),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(DS.Spacing.s, Alignment.CenterVertically),
            ) {
                IconTile(action.symbol, iconSize = 26.dp)
                Text(
                    action.title, style = DS.Typography.footnote.copy(fontWeight = FontWeight.Medium), color = DS.Palette.textPrimary,
                    textAlign = TextAlign.Center, maxLines = 2,
                )
            }
        }
    }
}

/** iOS `LazyVGrid(columns: [GridItem(.adaptive(minimum:))])`: as many equal columns as fit. */
@Composable
fun AdaptiveGrid(minCellWidth: androidx.compose.ui.unit.Dp, spacing: androidx.compose.ui.unit.Dp, content: @Composable () -> Unit) {
    Layout(content) { measurables, constraints ->
        val gap = spacing.roundToPx()
        val columns = maxOf(1, (constraints.maxWidth + gap) / (minCellWidth.roundToPx() + gap))
        val cellWidth = (constraints.maxWidth - gap * (columns - 1)) / columns
        val placeables = measurables.map { it.measure(Constraints.fixedWidth(cellWidth)) }
        val rows = placeables.chunked(columns)
        val rowHeights = rows.map { row -> row.maxOf { it.height } }
        val height = rowHeights.sum() + gap * maxOf(rows.size - 1, 0)
        layout(constraints.maxWidth, height) {
            var y = 0
            rows.forEachIndexed { r, row ->
                row.forEachIndexed { c, placeable -> placeable.place(c * (cellWidth + gap), y) }
                y += rowHeights[r] + gap
            }
        }
    }
}

@Composable
private fun DependentChip(dependent: DependentSummary) {
    Row(
        Modifier
            .clip(RoundedCornerShape(DS.Radius.l))
            .background(DS.Palette.surface)
            .padding(DS.Spacing.m)
            .semantics(mergeDescendants = true) {},
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        InitialsAvatar(dependent.fullName, size = 36.dp)
        Column {
            Text(dependent.fullName, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary, maxLines = 1)
            Text(dependent.relationLabel, style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
    }
}
