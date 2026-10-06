import Foundation

struct CardBeneficiary: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let fullName: String
    let relationLabel: String
    let memberNumber: String
    let policyNumber: String
    let insurerName: String
    let formulaName: String
    let coverageRate: Int
    let validUntil: LocalDay
    let photoURL: URL?
    let status: ServerStatus
    let birthDate: LocalDay?
}

struct MemberCard: Codable, Equatable, Sendable {
    let beneficiaries: [CardBeneficiary]
}

/// A signed, short-lived token. The QR code encodes only this string — never personal data.
struct QRToken: Codable, Equatable, Sendable {
    let token: String
    let expiresAt: Date

    func isValid(at date: Date = .now, margin: TimeInterval = 5) -> Bool {
        expiresAt.timeIntervalSince(date) > margin
    }
}
