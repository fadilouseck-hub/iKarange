import Foundation
import Observation

/// Onboarding / subscription in 8 steps (CDC §5.2). Products, questions, premiums, surcharges and
/// eligibility all come from the API; every change triggers a debounced `POST /quotes`.
@MainActor
@Observable
final class SubscriptionViewModel {
    enum Step: Int, CaseIterable, Comparable {
        static func < (lhs: Step, rhs: Step) -> Bool { lhs.rawValue < rhs.rawValue }

        case identification, personal, health, guarantees, premium, validation, payment, confirmation

        var title: String {
            switch self {
            case .identification: String(localized: "Identification")
            case .personal: String(localized: "Informations personnelles")
            case .health: String(localized: "Questionnaire de santé")
            case .guarantees: String(localized: "Garanties")
            case .premium: String(localized: "Votre prime")
            case .validation: String(localized: "Validation")
            case .payment: String(localized: "Paiement")
            case .confirmation: String(localized: "Confirmation")
            }
        }
    }

    private let env: AppEnvironment
    private var api: AssurAPI { env.api }

    private(set) var step: Step = .identification
    private(set) var products: [Product] = []
    private(set) var questionnaire: HealthQuestionnaire?
    private(set) var isLoading = false
    var error: APIError?

    // Personal information
    var birthDate: Date
    var gender: Gender
    var email: String
    var city: String

    // Health questionnaire: question id → "true" / "false" / option code / text
    var answers: [String: String] = [:]

    // Guarantees
    var productId: String?
    var coverageRate: Int?
    var territoriality: String?
    var dependents: [QuoteMember] = []

    // Quote
    private(set) var quote: Quote?
    private(set) var isQuoting = false
    private(set) var quoteError: APIError?
    private var quoteTask: Task<Void, Never>?
    var quoteDebounce: Duration = .milliseconds(400)

    // Validation & payment
    var acceptedConditions = false
    private(set) var createdPolicy: CreatedPolicy?
    private(set) var payment: PaymentViewModel?
    private(set) var isCreatingPolicy = false

    init(env: AppEnvironment) {
        self.env = env
        let user = env.session.user
        birthDate = user?.birthDate?.date ?? Calendar.current.date(byAdding: .year, value: -30, to: .now) ?? .now
        gender = user?.gender ?? .female
        email = user?.email ?? ""
        city = user?.city ?? ""
    }

    var user: Me? { env.session.user }
    var product: Product? { products.first { $0.id == productId } }
    var progressIndex: Int { step.rawValue + 1 }

    // MARK: Loading

    func load() async {
        guard products.isEmpty else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            async let products = api.products()
            async let questionnaire = api.healthQuestionnaire(productId: nil)
            self.products = try await products
            self.questionnaire = try await questionnaire
            if productId == nil { select(product: self.products.first { $0.highlight == true } ?? self.products.first) }
            error = nil
        } catch {
            self.error = .wrap(error)
        }
    }

    func select(product: Product?) {
        guard let product else { return }
        productId = product.id
        if let rate = coverageRate, product.coverageRates.contains(rate) {} else { coverageRate = product.coverageRates.last }
        if let zone = territoriality, product.territorialities.contains(where: { $0.code == zone }) {} else {
            territoriality = product.territorialities.first?.code
        }
    }

    // MARK: Questionnaire

    /// Questions to display, honouring `dependsOn` (shown only when the parent answer is "yes").
    var visibleQuestions: [HealthQuestionnaire.Question] {
        (questionnaire?.questions ?? []).filter { question in
            guard let parent = question.dependsOn else { return true }
            return answers[parent] == "true"
        }
    }

    var questionnaireComplete: Bool {
        visibleQuestions.allSatisfy { !$0.required || !(answers[$0.id] ?? "").isEmpty }
    }

    // MARK: Quote (dynamic simulation)

    var quoteRequest: QuoteRequest? {
        guard let productId, let coverageRate, let territoriality, let questionnaire else { return nil }
        let visible = Set(visibleQuestions.map(\.id))
        return QuoteRequest(
            productId: productId, coverageRate: coverageRate, territoriality: territoriality,
            birthDate: LocalDay(date: birthDate), gender: gender, dependents: dependents,
            questionnaireVersion: questionnaire.version,
            answers: answers.filter { visible.contains($0.key) && !$0.value.isEmpty }
                .map { QuestionnaireAnswer(questionId: $0.key, value: $0.value) }
                .sorted { $0.questionId < $1.questionId })
    }

    /// Debounced: rapid changes (slider, toggles) produce a single request for the last state.
    func scheduleQuote() {
        quoteTask?.cancel()
        guard let request = quoteRequest else { return }
        let delay = quoteDebounce
        quoteTask = Task { [weak self] in
            try? await Task.sleep(for: delay)
            guard !Task.isCancelled else { return }
            await self?.fetchQuote(request)
        }
    }

    func fetchQuote(_ request: QuoteRequest) async {
        isQuoting = true
        defer { isQuoting = false }
        do {
            let fresh = try await api.quote(request)
            // Ignore a late answer for inputs that have changed since.
            if request == quoteRequest {
                quote = fresh
                quoteError = nil
            }
        } catch {
            let apiError = APIError.wrap(error)
            if apiError != .cancelled { quoteError = apiError }
        }
    }

    // MARK: Dependants

    func addDependent(_ member: QuoteMember) {
        dependents.append(member)
        scheduleQuote()
    }

    func removeDependent(_ id: UUID) {
        dependents.removeAll { $0.id == id }
        scheduleQuote()
    }

    var canAddDependent: Bool { dependents.count < (product?.maxDependents ?? 10) }

    // MARK: Navigation

    var canContinue: Bool {
        switch step {
        case .identification: user != nil
        case .personal: email.isEmpty || (email.contains("@") && email.contains("."))
        case .health: questionnaireComplete
        case .guarantees: quoteRequest != nil
        case .premium: quote?.eligible == true && !isQuoting
        case .validation: acceptedConditions && !isCreatingPolicy
        case .payment, .confirmation: false
        }
    }

    func next() async {
        guard canContinue else { return }
        switch step {
        case .personal:
            // Contact details belong to the account; birth date and gender feed the quote only.
            if email != (user?.email ?? "") || city != (user?.city ?? "") {
                if let me = try? await api.updateMe(MeUpdate(email: email, address: nil, city: city, preferredLanguage: nil)) {
                    env.session.updateUser(me)
                }
            }
            step = .health
        case .health:
            step = .guarantees
            scheduleQuote()
        case .guarantees:
            step = .premium
            if quote == nil { scheduleQuote() }
        case .validation:
            await createPolicy()
        default:
            if let following = Step(rawValue: step.rawValue + 1) { step = following }
        }
    }

    func back() {
        guard step.rawValue > 0, step < .payment, let previous = Step(rawValue: step.rawValue - 1) else { return }
        step = previous
    }

    private func createPolicy() async {
        guard let quote else { return }
        isCreatingPolicy = true
        defer { isCreatingPolicy = false }
        do {
            let policy = try await api.createPolicy(quoteId: quote.id, conditionsVersion: quote.conditionsVersion)
            createdPolicy = policy
            payment = PaymentViewModel(env: env, policyId: policy.id, amount: policy.amountDue, purpose: "subscription")
            step = .payment
            error = nil
        } catch {
            self.error = .wrap(error)
            if case .server(410, _, _, _) = self.error { scheduleQuote() } // quote expired → recompute
        }
    }

    func paymentSucceeded() async {
        step = .confirmation
        await env.session.refreshUser()
    }
}
