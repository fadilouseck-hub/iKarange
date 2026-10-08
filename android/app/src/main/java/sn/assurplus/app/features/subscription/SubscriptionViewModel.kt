package sn.assurplus.app.features.subscription

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.CreatedPolicy
import sn.assurplus.app.core.model.Gender
import sn.assurplus.app.core.model.HealthQuestionnaire
import sn.assurplus.app.core.model.LocalDay
import sn.assurplus.app.core.model.Me
import sn.assurplus.app.core.model.MeUpdate
import sn.assurplus.app.core.model.Product
import sn.assurplus.app.core.model.QuestionnaireAnswer
import sn.assurplus.app.core.model.Quote
import sn.assurplus.app.core.model.QuoteMember
import sn.assurplus.app.core.model.QuoteRequest
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.features.payment.PaymentViewModel
import java.time.LocalDate

/**
 * Onboarding / subscription in 8 steps (CDC §5.2). Products, questions, premiums, surcharges and eligibility all
 * come from the API; every change triggers a debounced `POST /quotes`. Port of the iOS `SubscriptionViewModel`.
 */
class SubscriptionViewModel(private val env: AppEnvironment, private val scope: CoroutineScope) {
    enum class Step {
        identification, personal, health, guarantees, premium, validation, payment, confirmation;

        val title: String
            get() = when (this) {
                identification -> t("Identification")
                personal -> t("Informations personnelles")
                health -> t("Questionnaire de santé")
                guarantees -> t("Garanties")
                premium -> t("Votre prime")
                validation -> t("Validation")
                payment -> t("Paiement")
                confirmation -> t("Confirmation")
            }
    }

    private val api get() = env.api

    var step by mutableStateOf(Step.identification)
        private set
    var products by mutableStateOf<List<Product>>(emptyList())
        private set
    var questionnaire by mutableStateOf<HealthQuestionnaire?>(null)
        private set
    var isLoading by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    // Personal information
    var birthDate by mutableStateOf(env.session.user?.birthDate ?: LocalDay.of(LocalDate.now().minusYears(30)))
    var gender by mutableStateOf(env.session.user?.gender ?: Gender.female)
    var email by mutableStateOf(env.session.user?.email ?: "")
    var city by mutableStateOf(env.session.user?.city ?: "")

    /** Health questionnaire: question id → "true" / "false" / option code / text. */
    val answers = mutableStateMapOf<String, String>()

    // Guarantees
    var productId by mutableStateOf<String?>(null)
    var coverageRate by mutableStateOf<Int?>(null)
    var territoriality by mutableStateOf<String?>(null)
    val dependents = mutableStateListOf<QuoteMember>()

    // Quote
    var quote by mutableStateOf<Quote?>(null)
        private set
    var isQuoting by mutableStateOf(false)
        private set
    var quoteError by mutableStateOf<APIError?>(null)
        private set
    private var quoteJob: Job? = null
    var quoteDebounceMillis = 400L

    // Validation & payment
    var acceptedConditions by mutableStateOf(false)
    var createdPolicy by mutableStateOf<CreatedPolicy?>(null)
        private set
    var payment by mutableStateOf<PaymentViewModel?>(null)
        private set
    var isCreatingPolicy by mutableStateOf(false)
        private set

    val user: Me? get() = env.session.user
    val product: Product? get() = products.firstOrNull { it.id == productId }
    val progressIndex: Int get() = step.ordinal + 1

    // MARK: Loading

    suspend fun load() {
        if (products.isNotEmpty()) return
        isLoading = true
        try {
            coroutineScope {
                val products = async { api.products() }
                val questionnaire = async { api.healthQuestionnaire(null) }
                this@SubscriptionViewModel.products = products.await()
                this@SubscriptionViewModel.questionnaire = questionnaire.await()
            }
            if (productId == null) select(products.firstOrNull { it.highlight == true } ?: products.firstOrNull())
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isLoading = false
        }
    }

    fun select(product: Product?) {
        product ?: return
        productId = product.id
        if (coverageRate == null || coverageRate !in product.coverageRates) coverageRate = product.coverageRates.lastOrNull()
        if (territoriality == null || product.territorialities.none { it.code == territoriality }) {
            territoriality = product.territorialities.firstOrNull()?.code
        }
    }

    // MARK: Questionnaire

    /** Questions to display, honouring `dependsOn` (shown only when the parent answer is "yes"). */
    val visibleQuestions: List<HealthQuestionnaire.Question>
        get() = questionnaire?.questions.orEmpty().filter { question ->
            val parent = question.dependsOn ?: return@filter true
            answers[parent] == "true"
        }

    val questionnaireComplete: Boolean
        get() = visibleQuestions.all { !it.required || !answers[it.id].isNullOrEmpty() }

    // MARK: Quote (dynamic simulation)

    val quoteRequest: QuoteRequest?
        get() {
            val productId = productId ?: return null
            val coverageRate = coverageRate ?: return null
            val territoriality = territoriality ?: return null
            val questionnaire = questionnaire ?: return null
            val visible = visibleQuestions.map { it.id }.toSet()
            return QuoteRequest(
                productId = productId, coverageRate = coverageRate, territoriality = territoriality,
                birthDate = birthDate, gender = gender, dependents = dependents.toList(),
                questionnaireVersion = questionnaire.version,
                answers = answers.filter { it.key in visible && it.value.isNotEmpty() }
                    .map { QuestionnaireAnswer(it.key, it.value) }
                    .sortedBy { it.questionId },
            )
        }

    /** Debounced: rapid changes produce a single request for the last state; the previous request is cancelled. */
    fun scheduleQuote() {
        quoteJob?.cancel()
        val request = quoteRequest ?: return
        quoteJob = scope.launch {
            delay(quoteDebounceMillis)
            fetchQuote(request)
        }
    }

    private suspend fun fetchQuote(request: QuoteRequest) {
        isQuoting = true
        try {
            val fresh = api.quote(request)
            // Ignore a late answer for inputs that have changed since.
            if (request == quoteRequest) {
                quote = fresh
                quoteError = null
            }
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            val apiError = APIError.wrap(e)
            if (apiError != APIError.Cancelled) quoteError = apiError
        } finally {
            isQuoting = false
        }
    }

    // MARK: Dependants

    fun addDependent(member: QuoteMember) {
        dependents.add(member)
        scheduleQuote()
    }

    fun removeDependent(id: String) {
        dependents.removeAll { it.id == id }
        scheduleQuote()
    }

    val canAddDependent: Boolean get() = dependents.size < (product?.maxDependents ?: 10)

    // MARK: Navigation

    val canContinue: Boolean
        get() = when (step) {
            Step.identification -> user != null
            Step.personal -> email.isEmpty() || (email.contains("@") && email.contains("."))
            Step.health -> questionnaireComplete
            Step.guarantees -> quoteRequest != null
            Step.premium -> quote?.eligible == true && !isQuoting
            Step.validation -> acceptedConditions && !isCreatingPolicy
            Step.payment, Step.confirmation -> false
        }

    val canGoBack: Boolean get() = step > Step.identification && step < Step.payment

    suspend fun next() {
        if (!canContinue) return
        when (step) {
            Step.personal -> {
                // Contact details belong to the account; birth date and gender feed the quote only.
                if (email != (user?.email ?: "") || city != (user?.city ?: "")) {
                    try {
                        env.session.updateUser(api.updateMe(MeUpdate(email = email, address = null, city = city, preferredLanguage = null)))
                    } catch (e: CancellationException) {
                        throw e
                    } catch (_: Throwable) {
                    }
                }
                step = Step.health
            }
            Step.health -> {
                step = Step.guarantees
                scheduleQuote()
            }
            Step.guarantees -> {
                step = Step.premium
                if (quote == null) scheduleQuote()
            }
            Step.validation -> createPolicy()
            else -> Step.entries.getOrNull(step.ordinal + 1)?.let { step = it }
        }
    }

    fun back() {
        if (!canGoBack) return
        step = Step.entries[step.ordinal - 1]
    }

    private suspend fun createPolicy() {
        val quote = quote ?: return
        isCreatingPolicy = true
        try {
            val policy = api.createPolicy(quote.id, quote.conditionsVersion)
            createdPolicy = policy
            payment = PaymentViewModel(env, policy.id, policy.amountDue, "subscription")
            step = Step.payment
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            val apiError = APIError.wrap(e)
            error = apiError
            if (apiError is APIError.Server && apiError.status == 410) scheduleQuote() // quote expired → recompute
        } finally {
            isCreatingPolicy = false
        }
    }

    suspend fun paymentSucceeded() {
        step = Step.confirmation
        env.session.refreshUser()
    }
}
