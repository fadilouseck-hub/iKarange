package sn.assurplus.app.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import sn.assurplus.app.core.l10n.t

/** Rights returned by the server at login. The UI hides anything not granted; the server enforces them. */
enum class Permission(val raw: String) {
    policyView("policy.view"),
    policyManage("policy.manage"),
    cardView("card.view"),
    claimsView("claims.view"),
    claimsCreate("claims.create"),
    familyView("family.view"),
    familyManage("family.manage"),
    vaultView("vault.view"),
    vaultManage("vault.manage"),
    paymentsView("payments.view"),
    paymentsCreate("payments.create"),
    subscriptionCreate("subscription.create"),
    profileEdit("profile.edit");

    companion object {
        fun from(raw: String): Permission? = entries.firstOrNull { it.raw == raw }
    }
}

@Serializable
enum class AccountRole { principal, dependent }

@Serializable
enum class Gender {
    @SerialName("F") female,
    @SerialName("M") male;

    val label: String
        get() = when (this) {
            female -> t("Femme")
            male -> t("Homme")
        }
}

@Serializable
data class Me(
    val id: String,
    val firstName: String,
    val lastName: String,
    val phone: String,
    val email: String? = null,
    val birthDate: LocalDay? = null,
    val gender: Gender? = null,
    val address: String? = null,
    val city: String? = null,
    val photoURL: String? = null,
    val role: AccountRole,
    /** Raw permission strings; unknown values from newer servers are ignored. */
    val permissions: List<String> = emptyList(),
    val memberNumber: String? = null,
    val hasActivePolicy: Boolean = false,
    val preferredLanguage: String? = null,
) {
    val fullName: String get() = "$firstName $lastName"
    val grantedPermissions: Set<Permission> get() = permissions.mapNotNull(Permission::from).toSet()
}

@Serializable
data class AuthTokens(
    val accessToken: String,
    val refreshToken: String,
    /** Seconds until the access token expires. */
    val expiresIn: Int,
)

@Serializable
data class AuthResponse(val tokens: AuthTokens, val user: Me)

@Serializable
enum class OTPPurpose {
    register, login,
    @SerialName("reset_password") resetPassword,
}

@Serializable
data class OTPSendRequest(val phone: String, val purpose: OTPPurpose)

@Serializable
data class OTPChallenge(
    val otpRequestId: String,
    val expiresIn: Int,
    val resendAfter: Int,
    /** Masked destination, e.g. "+221 77 *** ** 01". */
    val maskedPhone: String,
)

@Serializable
data class OTPVerifyRequest(val otpRequestId: String, val code: String)

/** Short-lived proof that the phone number was verified; used by register, OTP login and reset. */
@Serializable
data class OTPVerification(val verificationToken: String)

@Serializable
data class LoginRequest(
    /** E.164 phone number (phone-login tenants). */
    val phone: String? = null,
    /** Username / member login (username-login tenants). */
    val identifier: String? = null,
    val password: String? = null,
    val verificationToken: String? = null,
)

@Serializable
data class RegisterRequest(
    val verificationToken: String,
    val firstName: String,
    val lastName: String,
    val birthDate: LocalDay,
    val gender: Gender,
    val email: String? = null,
    val address: String? = null,
    val city: String? = null,
    val password: String? = null,
    val cguVersion: String,
)

@Serializable
data class PasswordResetRequest(val verificationToken: String, val newPassword: String)

@Serializable
data class PasswordChangeRequest(val currentPassword: String, val newPassword: String)

@Serializable
data class LegalDocument(val id: String, val title: String, val version: String, val url: String)

@Serializable
data class MeUpdate(
    val email: String? = null,
    val address: String? = null,
    val city: String? = null,
    val preferredLanguage: String? = null,
)

@Serializable
data class NotificationPreferences(val categories: List<Channel>) {
    @Serializable
    data class Channel(val id: String, val label: String, val push: Boolean, val sms: Boolean, val email: Boolean)
}

@Serializable
data class DeviceRegistration(val token: String, val platform: String, val locale: String, val appVersion: String)
