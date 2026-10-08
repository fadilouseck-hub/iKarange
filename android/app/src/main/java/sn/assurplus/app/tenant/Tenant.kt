package sn.assurplus.app.tenant

import android.content.Context
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

/**
 * White-label tenant configuration, read from `assets/tenant.json` (generated from the iOS `Tenant.plist`, same
 * keys). Shared features reference the tenant through this type only (name, wordmark, brand colours, backend,
 * support, legal links, languages).
 */
@Serializable
data class Tenant(
    val id: String,
    val displayName: String,
    val wordmark: String,
    val copyrightHolder: String,
    val apiBaseURL: String,
    val loginIdentifier: LoginIdentifier,
    val features: Features,
    val supportPhone: String,
    val supportEmail: String,
    val privacyPolicyURL: String,
    val termsURL: String,
    val defaultLanguage: String,
    val supportedLanguages: List<String>,
    val colors: Colors,
) {
    @Serializable
    data class Colors(
        /** Deep brand colour: primary buttons, cards, QR modules (hex RRGGBB). */
        val brandDark: String,
        val brandDarkSecondary: String,
        /** Bright brand colour: accents on dark backgrounds, logo mark. */
        val brandAccent: String,
        /** Accent with enough contrast on light backgrounds (links, icons). */
        val accentOnLight: String,
    )

    @Serializable
    data class Features(
        val selfRegistration: Boolean,
        val otpLogin: Boolean,
        val passwordReset: Boolean,
    ) {
        companion object {
            val all = Features(selfRegistration = true, otpLogin = true, passwordReset = true)
        }
    }

    @Serializable
    enum class LoginIdentifier { phone, username }

    val supportPhoneUri: String?
        get() = supportPhone.takeIf { it.isNotEmpty() }?.let { "tel:" + it.filter { c -> c.isDigit() || c == '+' } }
    val supportEmailUri: String? get() = supportEmail.takeIf { it.isNotEmpty() }?.let { "mailto:$it" }
    val privacyUri: String? get() = privacyPolicyURL.takeIf { it.isNotEmpty() }
    val termsUri: String? get() = termsURL.takeIf { it.isNotEmpty() }

    companion object {
        private var loaded: Tenant? = null

        /** Set once by `AssurPlusApp.onCreate`; a missing or invalid file is a build error caught by tests. */
        val current: Tenant get() = loaded ?: error("Tenant not loaded")

        fun load(context: Context): Tenant {
            loaded?.let { return it }
            val text = context.assets.open("tenant.json").bufferedReader().use { it.readText() }
            return Json { ignoreUnknownKeys = true }.decodeFromString(serializer(), text).also { loaded = it }
        }

        fun hex(value: String): Long = value.toLongOrNull(16) ?: 0L
    }
}
