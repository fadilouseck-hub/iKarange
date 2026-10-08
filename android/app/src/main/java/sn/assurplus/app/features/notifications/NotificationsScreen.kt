package sn.assurplus.app.features.notifications

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.AppNotification
import sn.assurplus.app.core.model.DeepLinkTarget
import sn.assurplus.app.core.model.NotificationsResponse
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*

@Composable
fun NotificationsScreen(onBack: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val resource = remember { RemoteResource(env.cache, CacheKey.notifications, NotificationsResponse.serializer()) { env.api.notifications() } }
    LaunchedEffect(Unit) { resource.load() }

    /** Marks it read, then opens the related screen. */
    fun open(notification: AppNotification) {
        val value = resource.value
        if (!notification.read && value != null) {
            val updated = value.notifications.map { if (it.id == notification.id) it.copy(read = true) else it }
            resource.update(NotificationsResponse(updated, maxOf(value.unreadCount - 1, 0)))
            scope.launch { runCatching { env.api.markNotificationRead(notification.id) } }
        }
        val target = notification.target
        if (target != null && target.kind != DeepLinkTarget.Kind.notifications) env.router.open(target)
    }

    fun markAllRead() {
        val value = resource.value ?: return
        resource.update(NotificationsResponse(value.notifications.map { it.copy(read = true) }, 0))
        scope.launch { runCatching { env.api.markAllNotificationsRead() } }
    }

    Screen(
        t("Notifications"),
        onBack = onBack,
        onRefresh = { resource.refresh() },
        actions = {
            if ((resource.value?.unreadCount ?: 0) > 0) GlassTextButton(t("Tout lire"), ::markAllRead, tint = DS.Palette.accent)
        },
    ) {
        LoadableContent(
            resource,
            retry = { scope.launch { resource.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    SkeletonCard(lines = 2)
                    SkeletonCard(lines = 2)
                }
            },
        ) { response ->
            if (response.notifications.isEmpty()) {
                EmptyStateView(
                    title = t("Aucune notification"),
                    message = t("Vous serez informé ici de l'avancement de vos sinistres, paiements et contrat."),
                    symbol = "bell",
                )
            } else {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    response.notifications.forEach { notification ->
                        NotificationRow(notification, onClick = { open(notification) })
                    }
                }
            }
        }
    }
}

@Composable
private fun NotificationRow(notification: AppNotification, onClick: () -> Unit) {
    val unreadLabel = t("Non lue")
    Row(
        Modifier
            .clip(RoundedCornerShape(DS.Radius.l))
            .clickable(role = Role.Button, onClick = onClick)
            .card(padding = DS.Spacing.m)
            .semantics(mergeDescendants = true) {}
            .testTag("notification.${notification.id}"),
        verticalAlignment = Alignment.Top,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Box(Modifier.size(36.dp).background(DS.Palette.accentSoft, CircleShape), contentAlignment = Alignment.Center) {
            Icon(sym(symbol(notification.category)), null, tint = DS.Palette.accent, modifier = Modifier.size(20.dp))
        }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    notification.title,
                    style = DS.Typography.subheadline.copy(fontWeight = if (notification.read) FontWeight.Normal else FontWeight.Bold),
                    color = DS.Palette.textPrimary,
                    modifier = Modifier.weight(1f),
                )
                if (!notification.read) {
                    Box(Modifier.size(8.dp).background(DS.Palette.accent, CircleShape).semantics { contentDescription = unreadLabel })
                }
            }
            Text(notification.body, style = DS.Typography.callout, color = DS.Palette.textSecondary)
            Text(DateText.relative(notification.createdAt), style = DS.Typography.caption2, color = DS.Palette.textSecondary)
        }
    }
}

private fun symbol(category: String): String = when (category) {
    "claim" -> "doc.text.magnifyingglass"
    "reimbursement" -> "banknote"
    "payment" -> "creditcard"
    "contract" -> "doc.badge.clock"
    "welcome", "subscription" -> "sparkles"
    else -> "bell"
}
