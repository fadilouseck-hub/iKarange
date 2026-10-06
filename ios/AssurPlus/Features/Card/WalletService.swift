import PassKit
import UIKit

enum WalletResult: Equatable, Sendable {
    case added
    case alreadyAdded
    case simulated
    case cancelled
}

/// Adds the `.pkpass` produced by the server (the platform already signs Apple Wallet passes).
@MainActor
protocol WalletAdding {
    var isAvailable: Bool { get }
    func add(passData: Data) async throws -> WalletResult
}

enum WalletError: LocalizedError {
    case invalidPass
    case unavailable

    var errorDescription: String? {
        switch self {
        case .invalidPass: String(localized: "La carte reçue n'a pas pu être ajoutée à Cartes (Wallet).")
        case .unavailable: String(localized: "Cartes (Wallet) n'est pas disponible sur cet appareil.")
        }
    }
}

@MainActor
final class LiveWallet: NSObject, WalletAdding, PKAddPassesViewControllerDelegate {
    private var continuation: CheckedContinuation<WalletResult, Never>?
    private var pass: PKPass?

    var isAvailable: Bool { PKAddPassesViewController.canAddPasses() }

    func add(passData: Data) async throws -> WalletResult {
        guard isAvailable else { throw WalletError.unavailable }
        guard let pass = try? PKPass(data: passData) else { throw WalletError.invalidPass }
        if PKPassLibrary().containsPass(pass) { return .alreadyAdded }
        guard let controller = PKAddPassesViewController(pass: pass), let presenter = Self.topViewController() else {
            throw WalletError.invalidPass
        }
        self.pass = pass
        controller.delegate = self
        return await withCheckedContinuation { continuation in
            self.continuation = continuation
            presenter.present(controller, animated: true)
        }
    }

    nonisolated func addPassesViewControllerDidFinish(_ controller: PKAddPassesViewController) {
        MainActor.assumeIsolated {
            controller.dismiss(animated: true)
            let added = pass.map { PKPassLibrary().containsPass($0) } ?? false
            continuation?.resume(returning: added ? .added : .cancelled)
            continuation = nil
        }
    }

    static func topViewController() -> UIViewController? {
        let root = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
            .flatMap(\.windows).first { $0.isKeyWindow }?.rootViewController
        var top = root
        while let presented = top?.presentedViewController { top = presented }
        return top
    }
}

/// MockAPI passes are not signed (no certificates locally), so the result is simulated.
@MainActor
struct MockWallet: WalletAdding {
    var isAvailable: Bool { true }
    func add(passData: Data) async throws -> WalletResult {
        guard !passData.isEmpty else { throw WalletError.invalidPass }
        return .simulated
    }
}
