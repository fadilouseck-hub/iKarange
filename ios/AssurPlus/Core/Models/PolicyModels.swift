import Foundation

/// A status sent by the server: a stable `code` for styling and a French `label` for display.
struct ServerStatus: Codable, Hashable, Sendable {
    let code: String
    let label: String

    var tone: StatusTone {
        switch code {
        case "active", "succeeded", "validated", "paid", "approved", "covered": .success
        case "pending", "pending_payment", "processing", "submitted", "in_review", "under_review",
             "pre_validated", "documents_requested", "requested": .warning
        case "suspended", "rejected", "failed", "expired", "terminated", "cancelled": .danger
        default: .neutral
        }
    }
}

enum StatusTone: Sendable { case success, warning, danger, info, neutral }

struct Message: Codable, Hashable, Sendable {
    enum Level: String, Codable, Sendable { case info, warning, error, success }
    let level: Level
    let text: String
}

struct PolicySummary: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let number: String
    let productName: String
    let formulaName: String
    let startDate: LocalDay
    let endDate: LocalDay
    let status: ServerStatus
    /// Percentage, e.g. 80.
    let coverageRate: Int
    let territoriality: String?
}

struct Limits: Codable, Equatable, Sendable {
    let annualLimit: Int
    let consumed: Int
    let reimbursed: Int
    let remaining: Int
}

struct DependentSummary: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let fullName: String
    let relationLabel: String
    let status: ServerStatus
}

struct Dashboard: Codable, Equatable, Sendable {
    let fullName: String
    let memberNumber: String?
    let policy: PolicySummary?
    let limits: Limits?
    let dependents: [DependentSummary]
    let recentClaims: [ClaimSummary]
    let unreadNotifications: Int
    let alerts: [Message]
}

struct Guarantee: Codable, Hashable, Sendable, Identifiable {
    var id: String { code }
    let code: String
    let label: String
    let limitLabel: String?
    let rateLabel: String?
    let description: String?
}

struct RenewalInfo: Codable, Equatable, Sendable {
    let renewalDate: LocalDay
    let tacitRenewal: Bool
    /// Last day a termination request is accepted (server-computed).
    let terminationDeadline: LocalDay?
    let canRequestTermination: Bool
    let info: String?
}

struct PolicyMember: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let fullName: String
    let relationLabel: String
    let birthDate: LocalDay?
}

struct PolicyDetail: Codable, Equatable, Sendable {
    let summary: PolicySummary
    let insurerName: String
    let premiumLabel: String?
    let members: [PolicyMember]
    let guarantees: [Guarantee]
    let exclusions: [String]
    let waitingPeriods: [String]
    let deductibleLabel: String?
    let renewal: RenewalInfo?
    let conditionsVersion: String?
    let pendingTermination: ServerStatus?
}

struct TerminationRequest: Codable, Sendable {
    let reason: String
}
