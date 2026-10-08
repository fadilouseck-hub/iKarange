package sn.assurplus.app.features.payment

import androidx.compose.runtime.Composable
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.designsystem.Screen

/** Placeholder — being ported from the iOS app. */
@Composable
fun PaymentHistoryScreen(onBack: () -> Unit) {
    Screen(t("Paiements"), onBack = onBack) {}
}

/** Placeholder — being ported from the iOS app. */
@Composable
fun PaymentDetailScreen(paymentId: String, onBack: () -> Unit) {
    Screen(t("Paiement"), onBack = onBack) {}
}

