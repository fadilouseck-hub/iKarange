package sn.assurplus.app.features.subscription

import androidx.compose.runtime.Composable
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.designsystem.Screen

/** Placeholder — being ported from the iOS app. */
@Composable
fun SubscriptionFlow(onDismiss: () -> Unit) {
    Screen(t("Souscription"), onBack = onDismiss) {}
}

