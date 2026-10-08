package sn.assurplus.app.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.time.Duration
import java.time.Instant

// MARK: - Card

@Serializable
data class CardBeneficiary(
    val id: String,
    val fullName: String,
    val relationLabel: String,
    val memberNumber: String,
    val policyNumber: String,
    val insurerName: String,
    val formulaName: String,
    val coverageRate: Int,
    val validUntil: LocalDay,
    val photoURL: String? = null,
    val status: ServerStatus,
    val birthDate: LocalDay? = null,
)

@Serializable
data class MemberCard(val beneficiaries: List<CardBeneficiary>)

/** A signed, short-lived token. The QR code encodes only this string — never personal data. */
@Serializable
data class QRToken(val token: String, val expiresAt: Timestamp) {
    fun isValid(at: Instant = Instant.now(), marginSeconds: Long = 5): Boolean =
        Duration.between(at, expiresAt).seconds > marginSeconds
}

// MARK: - Payments

@Serializable
data class PaymentMethod(
    val code: String,
    val label: String,
    val kind: Kind,
    val enabled: Boolean,
    val requiresPhone: Boolean,
    val help: String? = null,
) {
    val id: String get() = code

    @Serializable
    enum class Kind {
        wave,
        @SerialName("orange_money") orangeMoney,
        card,
        other,
    }
}

@Serializable
data class PaymentCreateRequest(
    val policyId: String,
    val method: String,
    val phone: String? = null,
    /** e.g. "subscription", "renewal". */
    val purpose: String,
    /** The app's return URL once the provider page or app completes. */
    val returnURL: String,
)

@Serializable
data class Payment(
    val id: String,
    val reference: String,
    val amount: Long,
    val methodLabel: String,
    val status: ServerStatus,
    val createdAt: Timestamp,
    val policyNumber: String? = null,
    val purposeLabel: String,
    /** Hosted checkout page (card, Orange Money web). */
    val checkoutURL: String? = null,
    /** Provider app deep link (Wave, Orange Money Max It). */
    val appURL: String? = null,
) {
    val isFinal: Boolean get() = status.code in setOf("succeeded", "failed", "cancelled", "expired")
    val isSuccessful: Boolean get() = status.code == "succeeded"
}
