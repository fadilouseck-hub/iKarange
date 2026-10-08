package sn.assurplus.app.app

import android.content.Context
import android.os.Bundle
import android.os.LocaleList
import androidx.compose.runtime.staticCompositionLocalOf
import sn.assurplus.app.BuildConfig
import sn.assurplus.app.core.l10n.LanguageSettings
import sn.assurplus.app.core.model.LoginRequest
import sn.assurplus.app.core.network.APIClient
import sn.assurplus.app.core.network.AssurApi
import sn.assurplus.app.core.network.OkHttpTransport
import sn.assurplus.app.core.persist.FileResponseCache
import sn.assurplus.app.core.persist.InMemoryResponseCache
import sn.assurplus.app.core.persist.ResponseCache
import sn.assurplus.app.core.security.AlwaysBiometrics
import sn.assurplus.app.core.security.BiometricAuthenticating
import sn.assurplus.app.core.security.DeviceBiometrics
import sn.assurplus.app.core.security.InMemoryTokenStore
import sn.assurplus.app.core.security.KeystoreTokenStore
import sn.assurplus.app.core.upload.ResumableUploader
import sn.assurplus.app.core.upload.Uploading
import sn.assurplus.app.mock.MockServer
import sn.assurplus.app.tenant.Tenant
import java.util.UUID

/**
 * Dependency container, provided to Compose through [LocalEnv]. Every service is behind an interface (transport,
 * token store, uploader, payment launcher, wallet, biometrics) so it can be faked — same shape as iOS.
 */
class AppEnvironment(
    val api: AssurApi,
    val uploader: Uploading,
    val cache: ResponseCache,
    val settings: AppSettings,
    val biometrics: BiometricAuthenticating,
    val paymentLauncher: PaymentLaunching,
    val wallet: WalletAdding,
    val isMock: Boolean,
    val language: LanguageSettings,
) {
    val router = Router()
    val session = AuthSession(api, api.client.tokens, cache, settings)

    /** Backend capabilities: the tenant's in production, everything in the mock flavor. */
    val features: Tenant.Features get() = if (isMock) Tenant.Features.all else Tenant.current.features
    val loginIdentifier: Tenant.LoginIdentifier get() = if (isMock) Tenant.LoginIdentifier.phone else Tenant.current.loginIdentifier

    /** Mock only: demo account to sign in automatically at launch ("principal" or "dependent"). */
    var mockSignIn: String? = null

    /** Signs in the demo account requested by the `MockSignIn` launch extra. */
    suspend fun performMockSignInIfRequested() {
        val account = mockSignIn ?: return
        if (!isMock || session.state != AuthSession.State.signedOut) return
        mockSignIn = null
        val phone = if (account == "dependent") "+221770000002" else "+221770000001"
        runCatching { api.login(LoginRequest(phone = phone, password = "assur1234")) }.getOrNull()?.let(session::didAuthenticate)
    }

    companion object {
        @Volatile private var instance: AppEnvironment? = null

        val current: AppEnvironment get() = instance ?: error("AppEnvironment not created")

        /** UI tests: the next activity launch builds a fresh environment from its extras. */
        fun resetForTests() {
            instance = null
        }

        /**
         * Launch extras (debug / mock, used by UI tests — the counterpart of the iOS launch arguments):
         * `ResetState` (fresh in-memory session), `MockLatency` (seconds), `MockQRLifetime` (seconds),
         * `MockSignIn` (principal|dependent), `AppLanguage` (fr|en), `APIBaseURL` (debug only: local backend).
         */
        fun obtain(context: Context, extras: Bundle?): AppEnvironment {
            instance?.let { return it }
            val app = context.applicationContext
            val tenant = Tenant.load(app)
            val useMock = BuildConfig.MOCK_API
            val reset = extras?.getBoolean("ResetState") == true

            val tokens = if (reset) InMemoryTokenStore() else KeystoreTokenStore(app, if (useMock) "sn.assurplus.app.mock" else "sn.assurplus.app.tokens")
            val cache = if (reset) InMemoryResponseCache() else FileResponseCache()
            val prefs = app.getSharedPreferences(if (reset) "uitests-${UUID.randomUUID()}" else "settings", Context.MODE_PRIVATE)
            val settings = AppSettings(prefs)
            val preferred = LocaleList.getDefault().let { list -> (0 until list.size()).map { list[it] } }
            val language = LanguageSettings(prefs, tenant, preferred)
            // Debug / UI tests: force a language (iOS `-AppleLanguages`).
            if (BuildConfig.DEBUG) extras?.getString("AppLanguage")?.let(language::select)

            val environment = if (useMock) {
                val latency = extras?.getString("MockLatency")?.toDoubleOrNull() ?: 0.35
                val qrLifetime = extras?.getString("MockQRLifetime")?.toLongOrNull() ?: 60
                val server = MockServer(app, (latency * 1000).toLong(), qrLifetime)
                val api = AssurApi(APIClient(MockServer.BASE_URL, server, tokens))
                AppEnvironment(api, ResumableUploader(api), cache, settings, AlwaysBiometrics(), MockPaymentLauncher(), MockWallet(), true, language)
                    .also { it.mockSignIn = extras?.getString("MockSignIn") }
            } else {
                var baseURL = tenant.apiBaseURL
                if (BuildConfig.DEBUG) extras?.getString("APIBaseURL")?.let { baseURL = it }
                val api = AssurApi(APIClient(baseURL, OkHttpTransport(), tokens))
                AppEnvironment(api, ResumableUploader(api), cache, settings, DeviceBiometrics(app), LivePaymentLauncher(), LiveWallet(app), false, language)
            }
            instance = environment
            return environment
        }
    }
}

val LocalEnv = staticCompositionLocalOf<AppEnvironment> { error("No AppEnvironment") }
