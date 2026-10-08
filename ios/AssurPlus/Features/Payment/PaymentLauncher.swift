import AuthenticationServices
import UIKit

enum PaymentLaunchResult: Sendable {
    /// The user came back to the app; the server status must now be polled.
    case returned
    case cancelled
    case failed(String)
}

/// Opens the provider's checkout (Wave / Orange Money app, or a hosted card page). The app never trusts
/// the outcome of this step: it always polls `GET /payments/{id}`, which reflects the provider webhook.
@MainActor
protocol PaymentLaunching {
    func launch(_ payment: Payment) async -> PaymentLaunchResult
}

@MainActor
final class LivePaymentLauncher: NSObject, PaymentLaunching, ASWebAuthenticationPresentationContextProviding {
    static let callbackScheme = "assurplus"
    private var session: ASWebAuthenticationSession?

    func launch(_ payment: Payment) async -> PaymentLaunchResult {
        if let appURL = payment.appURL, UIApplication.shared.canOpenURL(appURL) {
            guard await UIApplication.shared.open(appURL) else { return .failed(String(localized: "Impossible d'ouvrir l'application de paiement.", bundle: .appLanguage)) }
            await waitForReturnToForeground()
            return .returned
        }
        guard let checkoutURL = payment.checkoutURL else {
            return .failed(String(localized: "Aucun lien de paiement reçu.", bundle: .appLanguage))
        }
        return await withCheckedContinuation { continuation in
            let session = ASWebAuthenticationSession(url: checkoutURL, callbackURLScheme: Self.callbackScheme) { _, error in
                if let error = error as? ASWebAuthenticationSessionError, error.code == .canceledLogin {
                    continuation.resume(returning: .cancelled)
                } else {
                    // Success or failure is decided by the server; return to polling either way.
                    continuation.resume(returning: .returned)
                }
            }
            session.presentationContextProvider = self
            session.prefersEphemeralWebBrowserSession = true
            self.session = session
            if !session.start() { continuation.resume(returning: .failed(String(localized: "Impossible d'ouvrir la page de paiement.", bundle: .appLanguage))) }
        }
    }

    nonisolated func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        MainActor.assumeIsolated {
            UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }.flatMap(\.windows).first { $0.isKeyWindow } ?? ASPresentationAnchor()
        }
    }

    private func waitForReturnToForeground() async {
        for await _ in NotificationCenter.default.notifications(named: UIApplication.didBecomeActiveNotification) { return }
    }
}

/// MockAPI: pretends the user validated the payment in the provider app.
@MainActor
struct MockPaymentLauncher: PaymentLaunching {
    func launch(_ payment: Payment) async -> PaymentLaunchResult {
        try? await Task.sleep(for: .milliseconds(600))
        return .returned
    }
}
