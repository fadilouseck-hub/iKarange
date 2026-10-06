import Foundation
import Observation

@MainActor
@Observable
final class CardViewModel {
    let card: RemoteResource<MemberCard>
    private let api: AssurAPI
    private let cache: ResponseCache
    private let wallet: WalletAdding

    var selectedId: String?
    /// Last token per beneficiary. Persisted so the QR can still be shown offline until it expires.
    private(set) var tokens: [String: QRToken]
    private(set) var qrError: APIError?
    private(set) var isAddingToWallet = false
    var walletMessage: Message?

    init(env: AppEnvironment) {
        api = env.api
        cache = env.cache
        wallet = env.wallet
        card = RemoteResource(cache: env.cache, key: .card) { try await env.api.card() }
        tokens = env.cache.load([String: QRToken].self, key: .qrTokens) ?? [:]
        selectedId = card.value?.beneficiaries.first?.id
    }

    var beneficiaries: [CardBeneficiary] { card.value?.beneficiaries ?? [] }
    var selected: CardBeneficiary? { beneficiaries.first { $0.id == selectedId } ?? beneficiaries.first }
    var canAddToWallet: Bool { wallet.isAvailable }

    func currentToken(at date: Date = .now) -> QRToken? {
        guard let id = selected?.id, let token = tokens[id], token.isValid(at: date, margin: 0) else { return nil }
        return token
    }

    func load() async {
        await card.load()
        if selectedId == nil || !beneficiaries.contains(where: { $0.id == selectedId }) {
            selectedId = beneficiaries.first?.id
        }
    }

    /// Fetches a fresh token when there is none or it expires within `margin` seconds.
    func refreshTokenIfNeeded(now: Date = .now, margin: TimeInterval = 10) async {
        guard let id = selected?.id else { return }
        if let token = tokens[id], token.isValid(at: now, margin: margin) { return }
        do {
            let token = try await api.qrToken(beneficiaryId: id)
            tokens[id] = token
            cache.store(tokens, key: .qrTokens)
            qrError = nil
        } catch {
            qrError = .wrap(error)
        }
    }

    /// Keeps the QR fresh while the card is on screen; cancelled with the view's task.
    func runTokenRefreshLoop() async {
        while !Task.isCancelled {
            await refreshTokenIfNeeded()
            let wait: TimeInterval
            if let token = currentToken() {
                wait = max(token.expiresAt.timeIntervalSinceNow - 10, 1)
            } else {
                wait = 5 // offline or failed: retry soon
            }
            try? await Task.sleep(for: .seconds(min(wait, 30)))
        }
    }

    func addToWallet() async {
        guard let id = selected?.id else { return }
        isAddingToWallet = true
        defer { isAddingToWallet = false }
        do {
            let data = try await api.walletPass(beneficiaryId: id)
            switch try await wallet.add(passData: data) {
            case .added: walletMessage = Message(level: .success, text: String(localized: "Carte ajoutée à Cartes (Wallet)."))
            case .alreadyAdded: walletMessage = Message(level: .info, text: String(localized: "Cette carte est déjà dans Cartes (Wallet)."))
            case .simulated: walletMessage = Message(level: .success, text: String(localized: "Carte ajoutée à Cartes (Wallet) — simulation MockAPI."))
            case .cancelled: walletMessage = nil
            }
        } catch let error as WalletError {
            walletMessage = Message(level: .error, text: error.errorDescription ?? "")
        } catch {
            walletMessage = Message(level: .error, text: APIError.wrap(error).userMessage)
        }
    }
}
