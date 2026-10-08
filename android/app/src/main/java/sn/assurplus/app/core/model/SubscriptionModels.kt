package sn.assurplus.app.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.Transient
import sn.assurplus.app.core.l10n.t
import java.util.UUID

@Serializable
data class Option(val code: String, val label: String) {
    val id: String get() = code
}

@Serializable
data class Product(
    val id: String,
    val name: String,
    val tagline: String? = null,
    val description: String? = null,
    /** Indicative "à partir de" premium in XOF, display only. */
    val premiumFrom: Long? = null,
    val highlight: Boolean? = null,
    val guarantees: List<Guarantee> = emptyList(),
    val coverageRates: List<Int> = emptyList(),
    val territorialities: List<Option> = emptyList(),
    val exclusions: List<String> = emptyList(),
    val waitingPeriods: List<String> = emptyList(),
    val deductibleLabel: String? = null,
    val maxDependents: Int? = null,
)

@Serializable
data class HealthQuestionnaire(val version: String, val questions: List<Question>) {
    @Serializable
    data class Question(
        val id: String,
        val label: String,
        val help: String? = null,
        val kind: Kind,
        val options: List<Option>? = null,
        val required: Boolean,
        /** Shown only when the referenced boolean question is answered "yes". */
        val dependsOn: String? = null,
    ) {
        @Serializable
        enum class Kind {
            boolean,
            @SerialName("single_choice") singleChoice,
            number,
            text,
        }
    }
}

@Serializable
data class QuestionnaireAnswer(val questionId: String, val value: String)

@Serializable
enum class Relation {
    spouse, child, other;

    val label: String
        get() = when (this) {
            spouse -> t("Conjoint(e)")
            child -> t("Enfant")
            other -> t("Autre ayant droit")
        }
}

@Serializable
data class QuoteMember(
    val firstName: String,
    val lastName: String,
    val relation: Relation,
    val birthDate: LocalDay,
    val gender: Gender,
    @Transient val id: String = UUID.randomUUID().toString(),
)

@Serializable
data class QuoteRequest(
    val productId: String,
    val coverageRate: Int,
    val territoriality: String,
    val birthDate: LocalDay,
    val gender: Gender,
    val dependents: List<QuoteMember>,
    val questionnaireVersion: String,
    val answers: List<QuestionnaireAnswer>,
)

@Serializable
data class Quote(
    val id: String,
    val eligible: Boolean,
    val messages: List<Message> = emptyList(),
    val basePremium: Long,
    val surcharges: List<Line> = emptyList(),
    val fees: List<Line> = emptyList(),
    val totalPremium: Long,
    val periodLabel: String,
    val perMember: List<Line> = emptyList(),
    val validUntil: Timestamp,
    val conditionsVersion: String,
    val specialConditions: List<String> = emptyList(),
) {
    @Serializable
    data class Line(val label: String, val amount: Long)
}

@Serializable
data class PolicyCreateRequest(val quoteId: String, val acceptedConditionsVersion: String)

@Serializable
data class CreatedPolicy(
    val id: String,
    val number: String,
    val status: ServerStatus,
    val amountDue: Long,
    val startDate: LocalDay,
)
