import Foundation

/// The nine claim statuses defined by the specification (CDC §10).
enum ClaimStatus: String, Codable, Sendable, CaseIterable {
    case draft
    case submitted
    case inReview = "in_review"
    case documentsRequested = "documents_requested"
    case preValidated = "pre_validated"
    case validated
    case rejected
    case paid
    case closed
    case unknown

    init(from decoder: Decoder) throws {
        let raw = try decoder.singleValueContainer().decode(String.self)
        self = ClaimStatus(rawValue: raw) ?? .unknown
    }

    var label: String {
        switch self {
        case .draft: String(localized: "Brouillon", bundle: .appLanguage)
        case .submitted: String(localized: "Soumis", bundle: .appLanguage)
        case .inReview: String(localized: "En analyse", bundle: .appLanguage)
        case .documentsRequested: String(localized: "Pièces complémentaires demandées", bundle: .appLanguage)
        case .preValidated: String(localized: "Pré-validé", bundle: .appLanguage)
        case .validated: String(localized: "Validé", bundle: .appLanguage)
        case .rejected: String(localized: "Rejeté", bundle: .appLanguage)
        case .paid: String(localized: "Payé", bundle: .appLanguage)
        case .closed: String(localized: "Clôturé", bundle: .appLanguage)
        case .unknown: String(localized: "En cours", bundle: .appLanguage)
        }
    }

    var tone: StatusTone {
        switch self {
        case .draft, .unknown: .neutral
        case .submitted, .inReview, .preValidated: .info
        case .documentsRequested: .warning
        case .validated, .paid, .closed: .success
        case .rejected: .danger
        }
    }

    var symbol: String {
        switch self {
        case .draft: "square.and.pencil"
        case .submitted: "paperplane"
        case .inReview: "magnifyingglass"
        case .documentsRequested: "doc.badge.plus"
        case .preValidated: "checkmark.circle"
        case .validated: "checkmark.seal"
        case .rejected: "xmark.octagon"
        case .paid: "banknote"
        case .closed: "archivebox"
        case .unknown: "clock"
        }
    }
}

struct ClaimType: Codable, Hashable, Sendable, Identifiable {
    var id: String { code }
    let code: String
    let label: String
    let symbol: String?
    let requiredDocuments: [String]
}

struct ClaimSummary: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let number: String?
    let status: ClaimStatus
    let typeLabel: String
    let beneficiaryName: String
    let providerName: String?
    let amount: Int?
    let createdAt: Date
    let updatedAt: Date
}

struct ClaimCreateRequest: Codable, Sendable {
    let beneficiaryId: String
    let typeCode: String
}

struct ClaimDocument: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let kind: String
    let fileName: String
    let createdAt: Date
}

struct ClaimDocumentAttach: Codable, Sendable {
    let uploadId: String
    /// "receipt" or "additional" (answering a document request).
    let kind: String
    let requestId: String?
}

/// One extracted value. `confidence` is 0…1 and set by the server's OCR.
struct OCRField: Codable, Equatable, Sendable, Identifiable {
    var id: String { key }
    let key: String
    let label: String
    var value: String
    let confidence: Double?
}

struct OCRLine: Codable, Equatable, Sendable, Identifiable {
    let id: String
    var label: String
    var quantity: String
    var unitPrice: String
    var amount: String
    let confidence: Double?
}

struct OCRResult: Codable, Equatable, Sendable {
    enum State: String, Codable, Sendable { case pending, processing, done, failed }
    let state: State
    /// 0…1 while processing, if the server reports it.
    let progress: Double?
    let fields: [OCRField]
    let lines: [OCRLine]
    /// Fields under this confidence must be checked by the insured (server-defined threshold).
    let reviewThreshold: Double
    let message: String?
}

struct ClaimUpdate: Codable, Sendable {
    let fields: [String: String]
    let lines: [OCRLine]
}

struct ClaimEvent: Codable, Equatable, Sendable, Identifiable {
    var id: String { "\(status.rawValue)-\(date.timeIntervalSince1970)" }
    let status: ClaimStatus
    let date: Date
    let message: String?
}

struct DocumentRequest: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let label: String
    let fulfilled: Bool
}

/// Computed by the server's guarantee engine once the claim is validated.
struct Settlement: Codable, Equatable, Sendable {
    let billedAmount: Int
    let coveredAmount: Int?
    let reimbursementRate: Int
    let deductible: Int?
    let insurerAmount: Int
    let remainingAmount: Int
}

struct Claim: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let number: String?
    let status: ClaimStatus
    let typeCode: String
    let typeLabel: String
    let beneficiaryId: String
    let beneficiaryName: String
    let createdAt: Date
    let submittedAt: Date?
    let fields: [OCRField]
    let lines: [OCRLine]
    let documents: [ClaimDocument]
    let timeline: [ClaimEvent]
    let documentRequests: [DocumentRequest]
    let settlement: Settlement?
    let rejectionReason: String?
}
