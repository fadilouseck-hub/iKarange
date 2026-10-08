package sn.assurplus.app.core.security

import android.content.Context
import android.content.SharedPreferences
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_STRONG
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_WEAK
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import kotlinx.coroutines.suspendCancellableCoroutine
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.AuthTokens
import sn.assurplus.app.core.model.JsonCoding
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import kotlin.coroutines.resume

interface TokenStore {
    fun load(): AuthTokens?
    fun save(tokens: AuthTokens)
    fun clear()
}

/**
 * Stores tokens encrypted with an AES-GCM key held in the Android Keystore (non-exportable, this device only) —
 * the counterpart of the iOS Keychain `AfterFirstUnlockThisDeviceOnly` item. The preferences file is excluded
 * from backups (`res/xml/data_extraction_rules.xml`).
 */
class KeystoreTokenStore(context: Context, private val alias: String) : TokenStore {
    private val prefs: SharedPreferences = context.getSharedPreferences("$alias.prefs", Context.MODE_PRIVATE)
    @Volatile private var memory: AuthTokens? = null

    override fun load(): AuthTokens? {
        memory?.let { return it }
        val stored = prefs.getString(KEY, null) ?: return null
        return runCatching {
            val bytes = Base64.decode(stored, Base64.NO_WRAP)
            val cipher = Cipher.getInstance(TRANSFORMATION)
            cipher.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, bytes, 0, IV_SIZE))
            val plain = cipher.doFinal(bytes, IV_SIZE, bytes.size - IV_SIZE)
            JsonCoding.json.decodeFromString<AuthTokens>(plain.decodeToString())
        }.getOrNull()?.also { memory = it }
    }

    override fun save(tokens: AuthTokens) {
        memory = tokens
        runCatching {
            val cipher = Cipher.getInstance(TRANSFORMATION)
            cipher.init(Cipher.ENCRYPT_MODE, key())
            val sealed = cipher.iv + cipher.doFinal(JsonCoding.json.encodeToString(tokens).toByteArray())
            prefs.edit().putString(KEY, Base64.encodeToString(sealed, Base64.NO_WRAP)).apply()
        }
    }

    override fun clear() {
        memory = null
        prefs.edit().remove(KEY).apply()
    }

    private fun key(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keyStore.getKey(alias, null) as? SecretKey)?.let { return it }
        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
        generator.init(
            KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setKeySize(256)
                .build()
        )
        return generator.generateKey()
    }

    private companion object {
        const val KEY = "session"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
        const val IV_SIZE = 12
    }
}

/** Used by tests and `ResetState` launches (fresh state each run). */
class InMemoryTokenStore(@Volatile private var tokens: AuthTokens? = null) : TokenStore {
    override fun load() = tokens
    override fun save(tokens: AuthTokens) { this.tokens = tokens }
    override fun clear() { tokens = null }
}

enum class BiometricKind {
    none, fingerprint, face;

    val label: String
        get() = when (this) {
            none -> t("Verrouillage biométrique")
            fingerprint -> t("Empreinte digitale")
            face -> t("Reconnaissance faciale")
        }

    /** SF Symbol name, mapped to a Material icon by `Sym`. */
    val symbol: String
        get() = when (this) {
            none -> "lock.shield"
            fingerprint -> "touchid"
            face -> "opticid"
        }
}

interface BiometricAuthenticating {
    val availableKind: BiometricKind
    suspend fun authenticate(reason: String): Boolean
}

/** BiometricPrompt (fingerprint / face), falling back to the device credential like iOS LocalAuthentication. */
class DeviceBiometrics(private val context: Context) : BiometricAuthenticating {
    var activity: FragmentActivity? = null

    override val availableKind: BiometricKind
        get() {
            val manager = BiometricManager.from(context)
            if (manager.canAuthenticate(BIOMETRIC_STRONG or BIOMETRIC_WEAK) != BiometricManager.BIOMETRIC_SUCCESS) return BiometricKind.none
            val pm = context.packageManager
            return if (pm.hasSystemFeature("android.hardware.fingerprint")) BiometricKind.fingerprint
            else if (pm.hasSystemFeature("android.hardware.biometrics.face")) BiometricKind.face
            else BiometricKind.fingerprint
        }

    override suspend fun authenticate(reason: String): Boolean {
        val host = activity ?: return false
        return suspendCancellableCoroutine { continuation ->
            val prompt = BiometricPrompt(host, ContextCompat.getMainExecutor(host), object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    if (continuation.isActive) continuation.resume(true)
                }
                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    if (continuation.isActive) continuation.resume(false)
                }
            })
            val info = BiometricPrompt.PromptInfo.Builder()
                .setTitle(reason)
                .setAllowedAuthenticators(BIOMETRIC_WEAK or BiometricManager.Authenticators.DEVICE_CREDENTIAL)
                .build()
            prompt.authenticate(info)
            continuation.invokeOnCancellation { prompt.cancelAuthentication() }
        }
    }
}

/** Mock flavor: always succeeds (emulators and UI tests). */
class AlwaysBiometrics : BiometricAuthenticating {
    override val availableKind = BiometricKind.face
    override suspend fun authenticate(reason: String) = true
}
