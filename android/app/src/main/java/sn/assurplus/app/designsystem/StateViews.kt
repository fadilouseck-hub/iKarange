package sn.assurplus.app.designsystem

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.RemoteResource

@Composable
fun EmptyStateView(
    title: String,
    message: String,
    modifier: Modifier = Modifier,
    symbol: String = "tray",
    actionTitle: String? = null,
    action: (() -> Unit)? = null,
) {
    Column(
        modifier.fillMaxWidth().padding(DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Icon(sym(symbol), null, tint = DS.Palette.accent, modifier = Modifier.size(44.dp))
        Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary, textAlign = TextAlign.Center)
        Text(message, style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
        if (actionTitle != null && action != null) SecondaryButton(actionTitle, action, Modifier.widthIn(max = 260.dp))
    }
}

@Composable
fun ErrorStateView(error: APIError, retry: () -> Unit, modifier: Modifier = Modifier) {
    Column(
        modifier.fillMaxWidth().padding(DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Icon(sym(if (error == APIError.Offline) "wifi.slash" else "exclamationmark.triangle"), null, tint = DS.Palette.warning, modifier = Modifier.size(44.dp))
        Text(if (error == APIError.Offline) t("Vous êtes hors ligne") else t("Impossible de charger"), style = DS.Typography.headline, color = DS.Palette.textPrimary)
        Text(error.userMessage, style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
        SecondaryButton(t("Réessayer"), retry, Modifier.widthIn(max = 220.dp))
    }
}

/** Non-blocking strip shown above cached content when a refresh failed. */
@Composable
fun StaleDataBanner(error: APIError, retry: () -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier
            .fillMaxWidth()
            .background(DS.Palette.warningSoft, RoundedCornerShape(DS.Radius.m))
            .padding(horizontal = DS.Spacing.m, vertical = DS.Spacing.s),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        Icon(sym(if (error == APIError.Offline) "wifi.slash" else "exclamationmark.arrow.circlepath"), null, tint = DS.Palette.warning, modifier = Modifier.size(18.dp))
        Text(
            if (error == APIError.Offline) t("Hors ligne — données enregistrées") else t("Mise à jour impossible"),
            style = DS.Typography.footnote.copy(fontWeight = FontWeight.Medium), color = DS.Palette.warning, modifier = Modifier.weight(1f),
        )
        TextLink(t("Réessayer"), retry, style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.warning)
    }
}

/** Grey placeholder blocks with a gentle pulse, used instead of blocking spinners. */
@Composable
fun SkeletonBlock(modifier: Modifier = Modifier, height: Dp = 16.dp, width: Dp? = null) {
    val transition = rememberInfiniteTransition(label = "skeleton")
    val alpha by transition.animateFloat(1f, 0.5f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "alpha")
    Box(
        modifier
            .then(if (width != null) Modifier.width(width) else Modifier.fillMaxWidth())
            .height(height)
            .alpha(alpha)
            .background(DS.Palette.surfaceMuted, RoundedCornerShape(DS.Radius.s))
    )
}

@Composable
fun SkeletonCard(modifier: Modifier = Modifier, lines: Int = 3) {
    Column(
        modifier.card().semantics { contentDescription = t("Chargement") },
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        SkeletonBlock(height = 20.dp, width = 160.dp)
        repeat(lines) { index -> SkeletonBlock(height = 14.dp, width = if (index == lines - 1) 120.dp else null) }
    }
}

@Composable
fun SkeletonList(count: Int = 3, lines: Int = 3) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) { repeat(count) { SkeletonCard(lines = lines) } }
}

/**
 * Standard handling of "cached value + refresh state": skeleton on first load, full-screen error only when
 * nothing is cached, otherwise content with a stale banner.
 */
@Composable
fun <T> LoadableContent(
    value: T?,
    isLoading: Boolean,
    error: APIError?,
    retry: () -> Unit,
    placeholder: @Composable () -> Unit = { SkeletonList() },
    content: @Composable (T) -> Unit,
) {
    when {
        value != null -> Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            if (error != null && !isLoading) StaleDataBanner(error, retry)
            content(value)
        }
        error != null && !isLoading -> ErrorStateView(error, retry)
        else -> placeholder()
    }
}

@Composable
fun <T> LoadableContent(
    resource: RemoteResource<T>,
    retry: () -> Unit,
    placeholder: @Composable () -> Unit = { SkeletonList() },
    content: @Composable (T) -> Unit,
) = LoadableContent(resource.value, resource.isLoading, resource.error, retry, placeholder, content)
