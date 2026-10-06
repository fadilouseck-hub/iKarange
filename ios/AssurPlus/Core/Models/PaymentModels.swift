import Foundation

struct PaymentMethod: Codable, Hashable, Sendable, Identifiable {
    enum Kind: String, Codable, Sendable { case wave, orangeMoney = "orange_money", card, other }
    var id: String { code }
    let code: String
    let label: String
    let kind: Kind
    let enabled: Bool
    let requiresPhone: Bool
    let help: String?
}

struct PaymentCreateRequest: Codable, Sendable {
    let policyId: String
    let method: String
    let phone: String?
    /// e.g. "subscription", "renewal".
    let purpose: String
    /// The app's return URL once the provider page or app completes.
    let returnURL: String
}

struct Payment: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let reference: String
    let amount: Int
    let methodLabel: String
    let status: ServerStatus
    let createdAt: Date
    let policyNumber: String?
    let purposeLabel: String
    /// Hosted checkout page (card, Orange Money web).
    let checkoutURL: URL?
    /// Provider app deep link (Wave, Orange Money Max It).
    let appURL: URL?

    var isFinal: Bool { ["succeeded", "failed", "cancelled", "expired"].contains(status.code) }
    var isSuccessful: Bool { status.code == "succeeded" }
}
