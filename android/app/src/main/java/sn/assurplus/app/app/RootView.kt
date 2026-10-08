package sn.assurplus.app.app

import android.os.Build
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveableStateHolder
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Permission
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.features.auth.AuthFlow
import sn.assurplus.app.features.card.CardScreen
import sn.assurplus.app.features.claims.ClaimDetailScreen
import sn.assurplus.app.features.claims.ClaimsListScreen
import sn.assurplus.app.features.claims.NewClaimFlow
import sn.assurplus.app.features.family.FamilyScreen
import sn.assurplus.app.features.home.HomeScreen
import sn.assurplus.app.features.network.NetworkScreen
import sn.assurplus.app.features.notifications.NotificationsScreen
import sn.assurplus.app.features.payment.PaymentDetailScreen
import sn.assurplus.app.features.payment.PaymentHistoryScreen
import sn.assurplus.app.features.policy.PolicyScreen
import sn.assurplus.app.features.profile.ProfileScreen
import sn.assurplus.app.features.subscription.SubscriptionFlow
import sn.assurplus.app.features.vault.VaultScreen
import sn.assurplus.app.tenant.Tenant

@Composable
fun RootView() {
    val env = LocalEnv.current
    LaunchedEffect(Unit) {
        if (env.session.state == AuthSession.State.launching) env.session.bootstrap()
        env.performMockSignInIfRequested()
    }
    LaunchedEffect(env.session.state) {
        if (env.session.state == AuthSession.State.signedOut) env.router.reset()
    }
    AnimatedContent(
        targetState = env.session.state,
        transitionSpec = { fadeIn(tween(250)) togetherWith fadeOut(tween(250)) },
        label = "root",
    ) { state ->
        when (state) {
            AuthSession.State.launching -> LaunchView()
            AuthSession.State.signedOut -> AuthFlow()
            AuthSession.State.locked -> LockView()
            AuthSession.State.signedIn -> MainTabView()
        }
    }
}

@Composable
fun LaunchView() {
    Column(
        Modifier.fillMaxSize().screenBackground(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m, Alignment.CenterVertically),
    ) {
        BrandMark(size = 72.dp)
        CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(22.dp))
    }
}

/** Logo: "+" in a rounded teal square, followed by the tenant wordmark. */
@Composable
fun BrandMark(modifier: Modifier = Modifier, size: Dp = 56.dp) {
    Row(
        modifier.semantics(mergeDescendants = true) { contentDescription = Tenant.current.displayName },
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(size * 0.08f),
    ) {
        Box(Modifier.size(size).background(DS.Palette.teal, RoundedCornerShape(size * 0.28f)), contentAlignment = Alignment.Center) {
            // Heavy "+" (SF Symbol "plus", weight black).
            val mint = DS.Palette.mint
            androidx.compose.foundation.Canvas(Modifier.size(size * 0.5f)) {
                val bar = this.size.width * 0.26f
                val r = androidx.compose.ui.geometry.CornerRadius(bar * 0.3f)
                drawRoundRect(mint, androidx.compose.ui.geometry.Offset((this.size.width - bar) / 2, 0f), androidx.compose.ui.geometry.Size(bar, this.size.height), r)
                drawRoundRect(mint, androidx.compose.ui.geometry.Offset(0f, (this.size.height - bar) / 2), androidx.compose.ui.geometry.Size(this.size.width, bar), r)
            }
        }
        Text(Tenant.current.wordmark, color = DS.Palette.primary, fontWeight = FontWeight.Black, fontSize = (size.value * 0.42f).sp)
    }
}

@Composable
fun LockView() {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    var failed by remember { mutableStateOf(false) }
    suspend fun unlock() {
        if (env.biometrics.authenticate(t("Déverrouiller %@", Tenant.current.displayName))) env.session.unlock() else failed = true
    }
    LaunchedEffect(Unit) { unlock() }
    Column(
        Modifier.fillMaxSize().screenBackground().systemBarsPadding().padding(DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.xl),
    ) {
        Spacer(Modifier.weight(1f))
        BrandMark(size = 64.dp)
        Text(t("Application verrouillée"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        if (failed) {
            Text(
                t("Authentification échouée. Réessayez ou reconnectez-vous."), style = DS.Typography.callout,
                color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
            )
        }
        Spacer(Modifier.weight(1f))
        val kind = env.biometrics.availableKind
        PrimaryButton(t("Déverrouiller avec %@", kind.label), { scope.launch { unlock() } }, symbol = kind.symbol)
        SecondaryButton(t("Se déconnecter"), { scope.launch { env.session.logout() } })
    }
}

// MARK: - Tabs

@Composable
fun MainTabView() {
    val env = LocalEnv.current
    val router = env.router
    val session = env.session
    val tabs = AppTab.entries.filter { tab ->
        when (tab) {
            AppTab.card -> session.can(Permission.cardView)
            AppTab.claims -> session.can(Permission.claimsView)
            else -> true
        }
    }
    if (router.selectedTab !in tabs) router.selectedTab = AppTab.home
    val stateHolder = rememberSaveableStateHolder()
    val navBarInset = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    // Back from a flow that changed data (claim, subscription): refresh the profile (permissions, policy).
    var previousSheet by remember { mutableStateOf(router.presentedSheet) }
    LaunchedEffect(router.presentedSheet) {
        if (previousSheet != null && router.presentedSheet == null) session.refreshUser()
        previousSheet = router.presentedSheet
    }

    Box(Modifier.fillMaxSize().screenBackground()) {
        CompositionLocalProvider(LocalBottomInset provides 72.dp + navBarInset + DS.Spacing.s) {
            stateHolder.SaveableStateProvider(router.selectedTab.name) {
                when (router.selectedTab) {
                    AppTab.home -> TabStack(router.homePath) { HomeScreen() }
                    AppTab.card -> CardScreen()
                    AppTab.claims -> TabStack(router.claimsPath) { ClaimsListScreen() }
                    AppTab.network -> NetworkScreen()
                    AppTab.profile -> TabStack(router.profilePath) { ProfileScreen() }
                }
            }
        }
        FloatingTabBar(
            tabs = tabs,
            selection = router.selectedTab,
            onSelect = { tab ->
                // Re-selecting the current tab pops to its root (iOS behaviour).
                if (tab == router.selectedTab) router.path(tab)?.clear() else router.selectedTab = tab
            },
            userName = session.user?.fullName.orEmpty(),
            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = navBarInset),
        )
        AnimatedVisibility(
            router.presentedSheet != null,
            enter = slideInVertically(spring(stiffness = Spring.StiffnessMediumLow)) { it } + fadeIn(),
            exit = slideOutVertically(tween(250)) { it } + fadeOut(),
        ) {
            val sheet = remember { mutableStateOf(router.presentedSheet) }
            if (router.presentedSheet != null) sheet.value = router.presentedSheet
            val dismiss = { router.presentedSheet = null }
            SheetContainer(onDismiss = dismiss) {
                when (sheet.value) {
                    Sheet.subscription -> SubscriptionFlow(onDismiss = dismiss)
                    Sheet.newClaim -> NewClaimFlow(onDismiss = dismiss)
                    null -> Unit
                }
            }
        }
    }
}

/** A tab's navigation stack: root screen plus pushed routes, with the iOS push / pop slide. */
@Composable
private fun TabStack(path: List<Route>, root: @Composable () -> Unit) {
    val env = LocalEnv.current
    val holder = rememberSaveableStateHolder()
    AnimatedContent(
        targetState = path.toList(),
        transitionSpec = {
            val forward = targetState.size >= initialState.size
            if (forward) slideInHorizontally(tween(320)) { it } togetherWith slideOutHorizontally(tween(320)) { -it / 3 }
            else slideInHorizontally(tween(320)) { -it / 3 } togetherWith slideOutHorizontally(tween(320)) { it }
        },
        label = "stack",
    ) { stack ->
        val top = stack.lastOrNull()
        holder.SaveableStateProvider("${stack.size}-$top") {
            if (top == null) root() else RouteDestination(top, onBack = { env.router.pop() })
        }
    }
}

@Composable
fun RouteDestination(route: Route, onBack: () -> Unit) {
    when (route) {
        Route.Policy -> PolicyScreen(onBack)
        Route.Family -> FamilyScreen(onBack)
        Route.Payments -> PaymentHistoryScreen(onBack)
        Route.Vault -> VaultScreen(onBack)
        Route.Notifications -> NotificationsScreen(onBack)
        is Route.Claim -> ClaimDetailScreen(route.id, onBack)
        is Route.Payment -> PaymentDetailScreen(route.id, onBack)
    }
}

/**
 * Icon-only capsule floating above the content: deep brand colour, glowing accent on the selected tab, initials
 * avatar for Profile (iOS `FloatingTabBar`).
 */
@Composable
fun FloatingTabBar(tabs: List<AppTab>, selection: AppTab, onSelect: (AppTab) -> Unit, userName: String, modifier: Modifier = Modifier) {
    val shape = CircleShape
    Row(
        modifier
            .padding(horizontal = DS.Spacing.xl)
            .padding(bottom = DS.Spacing.s)
            .fillMaxWidth()
            .shadow(18.dp, shape, ambientColor = Color.Black.copy(alpha = 0.28f), spotColor = Color.Black.copy(alpha = 0.28f))
            .background(Brush.verticalGradient(listOf(DS.Palette.tealMid.copy(alpha = 0.96f), DS.Palette.teal.copy(alpha = 0.98f))), shape)
            .border(1.dp, Color.White.copy(alpha = 0.12f), shape)
            .padding(horizontal = DS.Spacing.s, vertical = DS.Spacing.xs),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        tabs.forEach { tab ->
            val selected = tab == selection
            Box(
                Modifier
                    .weight(1f)
                    .heightIn(min = 56.dp)
                    .clickable(remember { MutableInteractionSource() }, null, role = Role.Tab) { onSelect(tab) }
                    .semantics { contentDescription = tab.title; this.selected = selected }
                    .testTag("tab.${tab.name}"),
                contentAlignment = Alignment.Center,
            ) {
                androidx.compose.animation.AnimatedVisibility(selected, enter = fadeIn(), exit = fadeOut()) {
                    Box(Modifier.size(48.dp).background(DS.Palette.mint.copy(alpha = 0.18f), CircleShape))
                }
                if (tab == AppTab.profile && userName.isNotEmpty()) {
                    Box(
                        Modifier
                            .size(32.dp)
                            .background(DS.Palette.mint, CircleShape)
                            .border(2.dp, Color.White.copy(alpha = if (selected) 0.9f else 0f), CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(initials(userName), color = DS.Palette.teal, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    }
                } else {
                    if (selected && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                        Icon(sym(tab.symbol), null, tint = DS.Palette.mint.copy(alpha = 0.7f), modifier = Modifier.size(26.dp).blur(8.dp, androidx.compose.ui.draw.BlurredEdgeTreatment.Unbounded))
                    }
                    Icon(sym(tab.symbol), null, tint = if (selected) DS.Palette.mint else Color.White.copy(alpha = 0.62f), modifier = Modifier.size(26.dp))
                }
            }
        }
    }
}
