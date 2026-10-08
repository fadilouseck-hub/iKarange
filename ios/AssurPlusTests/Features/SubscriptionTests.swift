import Foundation
import Testing
@testable import AssurPlus

/// Counts quote requests going through the mock server.
actor CountingTransport: HTTPTransport {
    let inner: HTTPTransport
    private(set) var quoteRequests = 0
    init(_ inner: HTTPTransport) { self.inner = inner }
    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        if request.url!.path.hasSuffix("/quotes") { quoteRequests += 1 }
        return try await inner.send(request)
    }
}

@MainActor
@Suite("Subscription & payment")
struct SubscriptionTests {
    /// A freshly registered user without a contract.
    private func newUser() async throws -> (AppEnvironment, CountingTransport) {
        let tokens = InMemoryTokenStore()
        let counting = CountingTransport(MockServer(latency: .zero))
        let api = AssurAPI(client: APIClient(baseURL: MockServer.baseURL, transport: counting, tokens: tokens))
        let env = AppEnvironment(
            api: api, uploader: ResumableUploader(api: api), cache: SwiftDataCache(inMemory: true),
            settings: AppSettings(defaults: UserDefaults(suiteName: "tests-\(UUID().uuidString)")!), tokens: tokens,
            biometrics: AlwaysBiometrics(), paymentLauncher: MockPaymentLauncher(), wallet: MockWallet(), isMock: true,
            language: LanguageSettings(defaults: UserDefaults(suiteName: "lang-\(UUID().uuidString)")!, preferred: ["fr"]))
        let challenge = try await api.sendOTP(phone: "+221781112233", purpose: .register)
        let token = try await api.verifyOTP(requestId: challenge.otpRequestId, code: MockServer.otpCode).verificationToken
        env.session.didAuthenticate(try await api.register(RegisterRequest(
            verificationToken: token, firstName: "Ndeye", lastName: "Fall", birthDate: LocalDay(year: 1992, month: 5, day: 4),
            gender: .female, email: nil, address: nil, city: "Thiès", password: "motdepasse", cguVersion: "CGU-2026-01")))
        return (env, counting)
    }

    private func answerNo(_ model: SubscriptionViewModel) {
        for question in model.visibleQuestions where question.kind == .boolean { model.answers[question.id] = "false" }
    }

    @Test func conditionalQuestionAppearsOnlyAfterYes() async throws {
        let (env, _) = try await newUser()
        let model = SubscriptionViewModel(env: env)
        await model.load()
        #expect(!model.visibleQuestions.contains { $0.id == "q_chronic_detail" })
        model.answers["q_chronic"] = "true"
        #expect(model.visibleQuestions.contains { $0.id == "q_chronic_detail" })
        answerNo(model)
        model.answers["q_chronic"] = "true"
        #expect(!model.questionnaireComplete) // detail is required
        model.answers["q_chronic_detail"] = "asthma"
        #expect(model.questionnaireComplete)
    }

    @Test func quoteIsDebouncedAndRecalculatedFromServer() async throws {
        let (env, counting) = try await newUser()
        let model = SubscriptionViewModel(env: env)
        model.quoteDebounce = .milliseconds(80)
        await model.load()
        answerNo(model)

        // Several quick changes → a single request for the final state.
        for rate in [70, 80, 90, 100] {
            model.coverageRate = rate
            model.scheduleQuote()
        }
        try await Task.sleep(for: .milliseconds(300))
        #expect(await counting.quoteRequests == 1)
        let at100 = try #require(model.quote)
        #expect(at100.eligible)

        model.coverageRate = 70
        model.scheduleQuote()
        try await Task.sleep(for: .milliseconds(300))
        let at70 = try #require(model.quote)
        #expect(at70.totalPremium < at100.totalPremium)

        model.addDependent(QuoteMember(firstName: "Awa", lastName: "Fall", relation: .child, birthDate: LocalDay(year: 2020, month: 1, day: 1), gender: .female))
        try await Task.sleep(for: .milliseconds(300))
        #expect(model.quote?.perMember.count == 2)
        #expect(model.quote!.totalPremium > at70.totalPremium)
    }

    @Test func healthAnswersDriveSurchargeAndEligibility() async throws {
        let (env, _) = try await newUser()
        let model = SubscriptionViewModel(env: env)
        await model.load()
        answerNo(model)
        model.answers["q_chronic"] = "true"
        model.answers["q_chronic_detail"] = "diabetes"
        await model.fetchQuote(try #require(model.quoteRequest))
        #expect(model.quote?.surcharges.isEmpty == false)
        #expect(model.quote?.eligible == true)

        model.answers["q_incurable"] = "true"
        await model.fetchQuote(try #require(model.quoteRequest))
        #expect(model.quote?.eligible == false)
        #expect(model.quote?.messages.contains { $0.level == .error } == true)
    }

    @Test func fullSubscriptionPaysAndActivatesTheContract() async throws {
        let (env, _) = try await newUser()
        #expect(try await env.api.dashboard().policy == nil)
        let model = SubscriptionViewModel(env: env)
        await model.load()

        await model.next() // identification → personal
        await model.next() // personal → health
        #expect(model.step == .health)
        answerNo(model)
        await model.next() // → guarantees
        model.select(product: model.products.first { $0.name == "Essentiel" })
        await model.fetchQuote(try #require(model.quoteRequest))
        await model.next() // → premium
        await model.next() // → validation
        #expect(model.step == .validation)
        #expect(!model.canContinue)
        model.acceptedConditions = true
        await model.next() // creates policy → payment
        #expect(model.step == .payment)
        let created = try #require(model.createdPolicy)
        #expect(created.status.code == "pending_payment")

        let payment = try #require(model.payment)
        payment.pollInterval = .milliseconds(5)
        await payment.loadMethods()
        #expect(payment.methods.map(\.code) == ["wave", "orange_money", "card"])
        #expect(PhoneNumber.e164(payment.phone) == "+221781112233") // pre-filled
        await payment.pay()
        #expect(payment.succeeded)
        #expect(payment.payment?.reference.hasPrefix("TX-") == true)

        await model.paymentSucceeded()
        #expect(model.step == .confirmation)
        #expect(env.session.user?.hasActivePolicy == true)
        let dashboard = try await env.api.dashboard()
        #expect(dashboard.policy?.number == created.number)
        #expect(try await env.api.card().beneficiaries.count == 1)
        #expect(try await env.api.payments().first?.status.code == "succeeded")
    }

    @Test func mobileMoneyRequiresAValidPhone() async throws {
        let (env, _) = try await newUser()
        let payment = PaymentViewModel(env: env, policyId: "x", amount: 1000, purpose: "subscription")
        await payment.loadMethods()
        payment.selectedMethod = "orange_money"
        payment.phone = "12"
        #expect(!payment.canPay)
        payment.selectedMethod = "card"
        #expect(payment.canPay)
    }
}
