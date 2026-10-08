package sn.assurplus.app.core.model

import kotlinx.serialization.Serializable

/** A status sent by the server: a stable `code` for styling and a French `label` for display. */
@Serializable
data class ServerStatus(val code: String, val label: String) {
    val tone: StatusTone
        get() = when (code) {
            "active", "succeeded", "validated", "paid", "approved", "covered" -> StatusTone.success
            "pending", "pending_payment", "processing", "submitted", "in_review", "under_review",
            "pre_validated", "documents_requested", "requested" -> StatusTone.warning
            "suspended", "rejected", "failed", "expired", "terminated", "cancelled" -> StatusTone.danger
            else -> StatusTone.neutral
        }
}

enum class StatusTone { success, warning, danger, info, neutral }

@Serializable
data class Message(val level: Level, val text: String) {
    @Serializable
    enum class Level { info, warning, error, success }
}

@Serializable
data class PolicySummary(
    val id: String,
    val number: String,
    val productName: String,
    val formulaName: String,
    val startDate: LocalDay,
    val endDate: LocalDay,
    val status: ServerStatus,
    /** Percentage, e.g. 80. */
    val coverageRate: Int,
    val territoriality: String? = null,
)

@Serializable
data class Limits(val annualLimit: Long, val consumed: Long, val reimbursed: Long, val remaining: Long)

@Serializable
data class DependentSummary(val id: String, val fullName: String, val relationLabel: String, val status: ServerStatus)

@Serializable
data class Dashboard(
    val fullName: String,
    val memberNumber: String? = null,
    val policy: PolicySummary? = null,
    val limits: Limits? = null,
    val dependents: List<DependentSummary> = emptyList(),
    val recentClaims: List<ClaimSummary> = emptyList(),
    val unreadNotifications: Int = 0,
    val alerts: List<Message> = emptyList(),
)

@Serializable
data class Guarantee(
    val code: String,
    val label: String,
    val limitLabel: String? = null,
    val rateLabel: String? = null,
    val description: String? = null,
) {
    val id: String get() = code
}

@Serializable
data class RenewalInfo(
    val renewalDate: LocalDay,
    val tacitRenewal: Boolean,
    /** Last day a termination request is accepted (server-computed). */
    val terminationDeadline: LocalDay? = null,
    val canRequestTermination: Boolean,
    val info: String? = null,
)

@Serializable
data class PolicyMember(val id: String, val fullName: String, val relationLabel: String, val birthDate: LocalDay? = null)

@Serializable
data class PolicyDetail(
    val summary: PolicySummary,
    val insurerName: String,
    val premiumLabel: String? = null,
    val members: List<PolicyMember> = emptyList(),
    val guarantees: List<Guarantee> = emptyList(),
    val exclusions: List<String> = emptyList(),
    val waitingPeriods: List<String> = emptyList(),
    val deductibleLabel: String? = null,
    val renewal: RenewalInfo? = null,
    val conditionsVersion: String? = null,
    val pendingTermination: ServerStatus? = null,
)

@Serializable
data class TerminationRequest(val reason: String)
