package sn.assurplus.app.app

import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.browser.customtabs.CustomTabsIntent
import androidx.core.content.FileProvider
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.ProcessLifecycleOwner
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Payment
import sn.assurplus.app.core.persist.ProtectedStorage
import java.io.File
import kotlin.coroutines.resume

/** Holds the visible activity for services that must start one (payment pages, share sheets, prompts). */
object CurrentActivity {
    var activity: Activity? = null
}

sealed class PaymentLaunchResult {
    /** The user came back to the app; the server status must now be polled. */
    data object Returned : PaymentLaunchResult()
    data object Cancelled : PaymentLaunchResult()
    data class Failed(val message: String) : PaymentLaunchResult()
}

/**
 * Opens the provider's checkout (Wave / Orange Money app, or a hosted card page in a Custom Tab). The app never
 * trusts the outcome of this step: it always polls `GET /payments/{id}`, which reflects the provider webhook.
 */
interface PaymentLaunching {
    suspend fun launch(payment: Payment): PaymentLaunchResult
}

class LivePaymentLauncher : PaymentLaunching {
    override suspend fun launch(payment: Payment): PaymentLaunchResult {
        val activity = CurrentActivity.activity ?: return PaymentLaunchResult.Failed(t("Impossible d'ouvrir la page de paiement."))
        payment.appURL?.let { link ->
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(link))
            if (intent.resolveActivity(activity.packageManager) != null) {
                return try {
                    activity.startActivity(intent)
                    waitForReturnToForeground()
                    PaymentLaunchResult.Returned
                } catch (e: ActivityNotFoundException) {
                    PaymentLaunchResult.Failed(t("Impossible d'ouvrir l'application de paiement."))
                }
            }
        }
        val checkout = payment.checkoutURL ?: return PaymentLaunchResult.Failed(t("Aucun lien de paiement reçu."))
        return try {
            CustomTabsIntent.Builder().setShowTitle(true).build().launchUrl(activity, Uri.parse(checkout))
            waitForReturnToForeground()
            // Success or failure is decided by the server; return to polling either way.
            PaymentLaunchResult.Returned
        } catch (e: ActivityNotFoundException) {
            PaymentLaunchResult.Failed(t("Impossible d'ouvrir la page de paiement."))
        }
    }

    /** Suspends until the app goes to the background and comes back. */
    private suspend fun waitForReturnToForeground() = withContext(Dispatchers.Main) {
        val lifecycle = ProcessLifecycleOwner.get().lifecycle
        suspendCancellableCoroutine { continuation ->
            var left = false
            val observer = LifecycleEventObserver { _, event ->
                if (event == Lifecycle.Event.ON_STOP) left = true
                if (event == Lifecycle.Event.ON_START && left && continuation.isActive) continuation.resume(Unit)
            }
            lifecycle.addObserver(observer)
            continuation.invokeOnCancellation { lifecycle.removeObserver(observer) }
        }
    }
}

/** Mock flavor: pretends the user validated the payment in the provider app. */
class MockPaymentLauncher : PaymentLaunching {
    override suspend fun launch(payment: Payment): PaymentLaunchResult {
        delay(600)
        return PaymentLaunchResult.Returned
    }
}

enum class WalletResult { added, simulated, cancelled }

/**
 * Google Wallet: opens the signed "Save to Google Wallet" link from the API (`GET /me/card/google-wallet`,
 * `TODO(backend)`). Counterpart of the iOS Apple Wallet `.pkpass` flow.
 */
interface WalletAdding {
    val isAvailable: Boolean
    suspend fun add(saveUrl: String): WalletResult
}

class LiveWallet(private val context: Context) : WalletAdding {
    /**
     * Hidden until the backend issues Google Wallet passes (`GET /me/card/google-wallet`, TODO(backend)): set
     * [backendReady] to true when it is deployed. Then shown only when the Google Wallet app is installed.
     */
    override val isAvailable: Boolean
        get() = backendReady && runCatching { context.packageManager.getPackageInfo("com.google.android.apps.walletnfcrel", 0) }.isSuccess

    private val backendReady = false

    override suspend fun add(saveUrl: String): WalletResult {
        val activity = CurrentActivity.activity ?: return WalletResult.cancelled
        activity.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(saveUrl)))
        return WalletResult.added
    }
}

/** Mock passes are not signed, so the result is simulated. */
class MockWallet : WalletAdding {
    override val isAvailable = true
    override suspend fun add(saveUrl: String): WalletResult = WalletResult.simulated
}

/**
 * Downloaded documents (conditions, network list, vault files): written to protected storage, then shown in a
 * viewer or shared through a `FileProvider` URI (iOS QuickLook + ShareLink).
 */
object Documents {
    fun save(data: ByteArray, fileName: String): File = ProtectedStorage.write(data, fileName)

    private fun uri(context: Context, file: File): Uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file)

    /** Opens the file in a viewer app; falls back to the share sheet when none is installed. */
    fun open(context: Context, file: File, mimeType: String = "application/pdf") {
        val intent = Intent(Intent.ACTION_VIEW).setDataAndType(uri(context, file), mimeType)
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
        try {
            context.startActivity(intent)
        } catch (e: ActivityNotFoundException) {
            share(context, file, mimeType)
        }
    }

    fun share(context: Context, file: File, mimeType: String = "application/pdf") {
        val send = Intent(Intent.ACTION_SEND).setType(mimeType).putExtra(Intent.EXTRA_STREAM, uri(context, file))
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        context.startActivity(Intent.createChooser(send, null).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }

    /** Opens a web page, phone dialer, mail app or maps link. */
    fun openUrl(context: Context, url: String) {
        runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
    }
}
