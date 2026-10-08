import Foundation

struct Option: Codable, Hashable, Sendable, Identifiable {
    var id: String { code }
    let code: String
    let label: String
}

struct Product: Codable, Hashable, Sendable, Identifiable {
    let id: String
    let name: String
    let tagline: String?
    let description: String?
    /// Indicative "à partir de" premium in XOF, display only.
    let premiumFrom: Int?
    let highlight: Bool?
    let guarantees: [Guarantee]
    let coverageRates: [Int]
    let territorialities: [Option]
    let exclusions: [String]
    let waitingPeriods: [String]
    let deductibleLabel: String?
    let maxDependents: Int?
}

struct HealthQuestionnaire: Codable, Equatable, Sendable {
    struct Question: Codable, Equatable, Sendable, Identifiable {
        enum Kind: String, Codable, Sendable { case boolean, singleChoice = "single_choice", number, text }
        let id: String
        let label: String
        let help: String?
        let kind: Kind
        let options: [Option]?
        let required: Bool
        /// Shown only when the referenced boolean question is answered "yes".
        let dependsOn: String?
    }
    let version: String
    let questions: [Question]
}

struct QuestionnaireAnswer: Codable, Equatable, Sendable {
    let questionId: String
    let value: String
}

enum Relation: String, Codable, Sendable, CaseIterable, Identifiable {
    case spouse, child, other
    var id: String { rawValue }
    var label: String {
        switch self {
        case .spouse: String(localized: "Conjoint(e)", bundle: .appLanguage)
        case .child: String(localized: "Enfant", bundle: .appLanguage)
        case .other: String(localized: "Autre ayant droit", bundle: .appLanguage)
        }
    }
}

struct QuoteMember: Codable, Equatable, Sendable, Identifiable {
    var id = UUID()
    var firstName: String
    var lastName: String
    var relation: Relation
    var birthDate: LocalDay
    var gender: Gender

    enum CodingKeys: String, CodingKey { case firstName, lastName, relation, birthDate, gender }
}

struct QuoteRequest: Codable, Equatable, Sendable {
    var productId: String
    var coverageRate: Int
    var territoriality: String
    var birthDate: LocalDay
    var gender: Gender
    var dependents: [QuoteMember]
    var questionnaireVersion: String
    var answers: [QuestionnaireAnswer]
}

struct Quote: Codable, Equatable, Sendable, Identifiable {
    struct Line: Codable, Hashable, Sendable {
        let label: String
        let amount: Int
    }
    let id: String
    let eligible: Bool
    let messages: [Message]
    let basePremium: Int
    let surcharges: [Line]
    let fees: [Line]
    let totalPremium: Int
    let periodLabel: String
    let perMember: [Line]
    let validUntil: Date
    let conditionsVersion: String
    let specialConditions: [String]
}

struct PolicyCreateRequest: Codable, Sendable {
    let quoteId: String
    let acceptedConditionsVersion: String
}

struct CreatedPolicy: Codable, Equatable, Sendable {
    let id: String
    let number: String
    let status: ServerStatus
    let amountDue: Int
    let startDate: LocalDay
}
