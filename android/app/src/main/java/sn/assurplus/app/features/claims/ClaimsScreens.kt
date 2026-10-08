package sn.assurplus.app.features.claims

import androidx.compose.runtime.Composable
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.designsystem.Screen

/** Placeholder — being ported from the iOS app. */
@Composable
fun ClaimsListScreen() {
    Screen(t("Mes sinistres"), onBack = null) {}
}

/** Placeholder — being ported from the iOS app. */
@Composable
fun ClaimDetailScreen(claimId: String, onBack: () -> Unit) {
    Screen(t("Sinistre"), onBack = onBack) {}
}

/** Placeholder — being ported from the iOS app. */
@Composable
fun NewClaimFlow(onDismiss: () -> Unit) {
    Screen(t("Déclarer un sinistre"), onBack = onDismiss) {}
}

