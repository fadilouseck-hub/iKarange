import Foundation
import Observation

/// Pay → open the provider (app or hosted page) → come back → poll `GET /payments/{id}` until the server,
/// informed by the provider webhook, reports a final status. The client-side outcome is never trusted.
@MainActor
@Observable
final class PaymentViewModel {
    enum Phase: Equatable { case choosing, launching, confirming, finished }

    let policyId: String
    let amount: Int
    let purpose: String
    private let api: AssurAPI
    private let launcher: PaymentLaunching

    private(set) var methods: [PaymentMethod] = []
    var selectedMethod: String?
    var phone: String
    private(set) var phase: Phase = .choosing
    private(set) var payment: Payment?
    private(set) var isLoadingMethods = false
    var error: APIError?

    var pollInterval: Duration = .seconds(2)
    var pollTimeout: Duration = .seconds(180)

    init(env: AppEnvironment, policyId: String, amount: Int, purpose: String) {
        self.policyId = policyId
        self.amount = amount
        self.purpose = purpose
        api = env.api
        launcher = env.paymentLauncher
        phone = PhoneNumber.formatInput(PhoneNumber.nationalDigits(env.session.user?.phone ?? "") ?? "")
    }

    var method: PaymentMethod? { methods.first { $0.code == selectedMethod } }

    var canPay: Bool {
        guard let method, method.enabled, phase == .choosing else { return false }
        return !method.requiresPhone || PhoneNumber.isValid(phone)
    }

    var succeeded: Bool { payment?.isSuccessful == true }

    func loadMethods() async {
        isLoadingMethods = true
        defer { isLoadingMethods = false }
        do {
            methods = try await api.paymentMethods()
            if selectedMethod == nil { selectedMethod = methods.first { $0.enabled }?.code }
            error = nil
        } catch {
            self.error = .wrap(error)
        }
    }

    func pay() async {
        guard let method, canPay else { return }
        error = nil
        phase = .launching
        do {
            let created = try await api.createPayment(PaymentCreateRequest(
                policyId: policyId, method: method.code,
                phone: method.requiresPhone ? PhoneNumber.e164(phone) : nil,
                purpose: purpose, returnURL: "assurplus://payments/return"))
            payment = created
            let result = await launcher.launch(created)
            if case .failed(let message) = result {
                error = .server(status: 0, code: "launch_failed", message: message, fields: [:])
            }
            // Even when cancelled the user may have paid in the provider app: always ask the server.
            phase = .confirming
            await pollUntilFinal()
        } catch {
            self.error = .wrap(error)
            phase = .choosing
        }
    }

    func pollUntilFinal() async {
        guard let id = payment?.id else { return }
        let clock = ContinuousClock()
        let deadline = clock.now.advanced(by: pollTimeout)
        while clock.now < deadline, !Task.isCancelled {
            if let latest = try? await api.payment(id: id) {
                payment = latest
                if latest.isFinal {
                    phase = .finished
                    return
                }
            }
            try? await Task.sleep(for: pollInterval)
        }
        // Still pending: the user can come back later; history shows the final status.
        phase = .finished
    }

    /// After a failure, let the user pick another method.
    func retry() {
        payment = nil
        phase = .choosing
        error = nil
    }
}
