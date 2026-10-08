package sn.assurplus.app.designsystem

import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import sn.assurplus.app.core.l10n.t

/** Bottom space reserved by the floating tab bar, so content scrolls clear of it. */
val LocalBottomInset = compositionLocalOf { 0.dp }

/** iOS 26 bar button: icon in a floating round "glass" button with a soft shadow. */
@Composable
fun GlassButton(
    symbol: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    description: String? = null,
    tint: Color = DS.Palette.accent,
    badge: Int = 0,
    tag: String? = null,
) {
    Box(modifier.size(48.dp)) {
        Box(
            Modifier
                .size(44.dp)
                .align(Alignment.Center)
                .shadow(10.dp, CircleShape, ambientColor = Color.Black.copy(alpha = 0.18f), spotColor = Color.Black.copy(alpha = 0.18f))
                .background(DS.Palette.surface, CircleShape)
                .clip(CircleShape)
                .clickable(role = Role.Button, onClick = onClick)
                .semantics { if (description != null) contentDescription = description }
                .then(if (tag != null) Modifier.testTag(tag) else Modifier),
            contentAlignment = Alignment.Center,
        ) {
            Icon(sym(symbol), null, tint = tint, modifier = Modifier.size(24.dp))
        }
        if (badge > 0) {
            Box(
                Modifier.align(Alignment.TopEnd).size(18.dp).background(DS.Palette.danger, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Text("${minOf(badge, 9)}", style = DS.Typography.caption2.copy(fontWeight = FontWeight.Bold), color = Color.White)
            }
        }
    }
}

/** Text bar button in a glass capsule (e.g. "Annuler", "Tout lire"). */
@Composable
fun GlassTextButton(title: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true, tint: Color = DS.Palette.textPrimary, tag: String? = null) {
    Box(
        modifier
            .height(44.dp)
            .alpha(if (enabled) 1f else 0.4f)
            .shadow(10.dp, CircleShape, ambientColor = Color.Black.copy(alpha = 0.18f), spotColor = Color.Black.copy(alpha = 0.18f))
            .background(DS.Palette.surface, CircleShape)
            .clip(CircleShape)
            .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
            .padding(horizontal = DS.Spacing.l)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        contentAlignment = Alignment.Center,
    ) {
        Text(title, style = DS.Typography.body.copy(fontWeight = FontWeight.Medium), color = tint)
    }
}

/**
 * A pushed or root screen with the iOS navigation bar: optional back button and trailing actions in glass
 * buttons, a large title that scrolls with the content (or an inline centred title), the screen background, an
 * optional pull-to-refresh, and bottom space for the floating tab bar.
 */
@Composable
fun Screen(
    title: String,
    modifier: Modifier = Modifier,
    largeTitle: Boolean = true,
    onBack: (() -> Unit)? = null,
    leading: (@Composable RowScope.() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
    onRefresh: (suspend () -> Unit)? = null,
    scrollState: ScrollState = rememberScrollState(),
    contentPadding: PaddingValues = PaddingValues(horizontal = DS.Spacing.l),
    spacing: Dp = DS.Spacing.l,
    scrollable: Boolean = true,
    bottomBar: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    if (onBack != null) BackHandler(onBack = onBack)
    val density = LocalDensity.current
    val collapsed by remember { derivedStateOf { !largeTitle || scrollState.value > with(density) { 44.dp.toPx() } } }
    val bottomInset = LocalBottomInset.current

    Box(modifier.fillMaxSize().screenBackground()) {
        val body: @Composable () -> Unit = {
            Column(
                Modifier
                    .fillMaxSize()
                    .then(if (scrollable) Modifier.verticalScroll(scrollState) else Modifier)
                    .padding(contentPadding)
                    .padding(top = barHeight())
                    .padding(bottom = if (bottomBar == null) bottomInset + DS.Spacing.l else DS.Spacing.l),
                verticalArrangement = Arrangement.spacedBy(spacing),
            ) {
                if (largeTitle) {
                    Text(
                        title, style = DS.Typography.largeTitle, color = DS.Palette.textPrimary,
                        modifier = Modifier.padding(top = DS.Spacing.xs, bottom = DS.Spacing.xs).semantics { heading() },
                    )
                }
                content()
            }
        }
        Column(Modifier.fillMaxSize()) {
            Box(Modifier.weight(1f)) {
                if (onRefresh != null) {
                    val scope = rememberCoroutineScope()
                    var refreshing by remember { mutableStateOf(false) }
                    PullToRefreshBox(
                        isRefreshing = refreshing,
                        onRefresh = { scope.launch { refreshing = true; onRefresh(); refreshing = false } },
                        modifier = Modifier.fillMaxSize(),
                    ) { body() }
                } else {
                    body()
                }
            }
            if (bottomBar != null) {
                Box(Modifier.background(DS.Palette.background).padding(bottom = bottomInset)) { bottomBar() }
            }
        }
        NavigationBar(title, collapsed, onBack, leading, actions)
    }
}

@Composable
private fun barHeight(): Dp =
    (if (LocalInSheet.current) 0.dp else WindowInsets.statusBars.asPaddingValues().calculateTopPadding()) + 56.dp

@Composable
private fun NavigationBar(
    title: String,
    showInlineTitle: Boolean,
    onBack: (() -> Unit)?,
    leading: (@Composable RowScope.() -> Unit)?,
    actions: @Composable RowScope.() -> Unit,
) {
    Box(
        Modifier
            .fillMaxWidth()
            .background(
                androidx.compose.ui.graphics.Brush.verticalGradient(
                    0f to DS.Palette.background, 0.75f to DS.Palette.background.copy(alpha = if (showInlineTitle) 0.94f else 0f),
                    1f to DS.Palette.background.copy(alpha = 0f),
                )
            )
            .then(if (LocalInSheet.current) Modifier.padding(top = DS.Spacing.s) else Modifier.statusBarsPadding())
            .height(56.dp)
            .padding(horizontal = DS.Spacing.m),
    ) {
        Row(Modifier.align(Alignment.CenterStart), verticalAlignment = Alignment.CenterVertically) {
            if (onBack != null) GlassButton("chevron.left", onBack, description = t("Retour"), tint = DS.Palette.textPrimary, tag = "nav.back")
            leading?.invoke(this)
        }
        AnimatedVisibility(showInlineTitle, Modifier.align(Alignment.Center).padding(horizontal = 64.dp), enter = fadeIn(), exit = fadeOut()) {
            Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis, textAlign = TextAlign.Center)
        }
        Row(Modifier.align(Alignment.CenterEnd), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s), content = actions)
    }
}

// MARK: - Grouped lists (iOS inset grouped `List` / `Form`)

@Composable
fun FormSection(
    modifier: Modifier = Modifier,
    header: String? = null,
    footer: String? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        if (header != null) {
            Text(
                header, style = DS.Typography.headline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textSecondary.copy(alpha = 0.75f),
                modifier = Modifier.padding(start = DS.Spacing.l, top = DS.Spacing.s).semantics { heading() },
            )
        }
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(26.dp)).background(DS.Palette.surface),
            content = content,
        )
        if (footer != null) {
            Text(footer, style = DS.Typography.footnote, color = DS.Palette.textSecondary, modifier = Modifier.padding(horizontal = DS.Spacing.l))
        }
    }
}

/**
 * One row of a [FormSection]: optional leading icon, title (and subtitle), trailing value / custom content, a
 * chevron when it navigates. Rows after the first draw an inset hairline on top.
 */
@Composable
fun FormRow(
    title: String,
    modifier: Modifier = Modifier,
    symbol: String? = null,
    subtitle: String? = null,
    value: String? = null,
    chevron: Boolean = false,
    divider: Boolean = true,
    titleColor: Color = DS.Palette.textPrimary,
    iconTint: Color = DS.Palette.textPrimary,
    onClick: (() -> Unit)? = null,
    tag: String? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Column(modifier.fillMaxWidth()) {
        if (divider) Hairline(Modifier.padding(start = if (symbol != null) 64.dp else DS.Spacing.l, end = DS.Spacing.l))
        Row(
            Modifier
                .fillMaxWidth()
                .heightIn(min = 60.dp)
                .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
                .padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m)
                .then(if (tag != null) Modifier.testTag(tag) else Modifier),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (symbol != null) {
                Icon(sym(symbol), null, tint = iconTint, modifier = Modifier.size(26.dp))
                Spacer(Modifier.width(DS.Spacing.l + 4.dp))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                Text(title, style = DS.Typography.body, color = titleColor)
                if (subtitle != null) Text(subtitle, style = DS.Typography.footnote, color = DS.Palette.textSecondary)
            }
            if (value != null) Text(value, style = DS.Typography.body, color = DS.Palette.textSecondary, modifier = Modifier.padding(start = DS.Spacing.s))
            trailing?.invoke()
            if (chevron) Icon(sym("chevron.right"), null, tint = DS.Palette.textSecondary.copy(alpha = 0.6f), modifier = Modifier.padding(start = DS.Spacing.xs).size(22.dp))
        }
    }
}

// MARK: - Sheets & alerts

/**
 * iOS page sheet: slides over the tabs with rounded top corners and a small gap at the top, a close (X) glass
 * button and an inline title. Use inside `Router.presentedSheet` handling.
 */
@Composable
fun SheetContainer(onDismiss: () -> Unit, content: @Composable () -> Unit) {
    BackHandler(onBack = onDismiss)
    Box(Modifier.fillMaxSize().background(Color.Black.copy(alpha = 0.35f))) {
        Box(
            Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .padding(top = DS.Spacing.s)
                .clip(RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp))
                .background(DS.Palette.background)
        ) {
            CompositionLocalProvider(LocalInSheet provides true, LocalBottomInset provides 0.dp) { content() }
        }
    }
}

/** True inside a [SheetContainer]: screens skip the status bar inset. */
val LocalInSheet = compositionLocalOf { false }

/** Confirmation / information dialog in the design-system colours. */
@Composable
fun DSAlert(
    title: String,
    message: String? = null,
    confirmTitle: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    destructive: Boolean = false,
    dismissTitle: String? = t("Annuler"),
    confirmTag: String? = null,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = DS.Palette.surface,
        title = { Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary) },
        text = message?.let { { Text(it, style = DS.Typography.callout, color = DS.Palette.textSecondary) } },
        confirmButton = {
            TextButton(onClick = onConfirm, modifier = if (confirmTag != null) Modifier.testTag(confirmTag) else Modifier) {
                Text(confirmTitle, style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = if (destructive) DS.Palette.destructive else DS.Palette.accent)
            }
        },
        dismissButton = dismissTitle?.let {
            { TextButton(onClick = onDismiss) { Text(it, style = DS.Typography.body, color = DS.Palette.accent) } }
        },
    )
}

/** Thin bordered container used for "dashed" upload zones and outlined boxes. */
@Composable
fun Modifier.outlined(radius: Dp = DS.Radius.m, color: Color = DS.Palette.border): Modifier =
    border(1.dp, color, RoundedCornerShape(radius))
