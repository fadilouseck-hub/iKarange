import Foundation

// MARK: - Family

struct Dependent: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let firstName: String
    let lastName: String
    let relation: Relation
    let relationLabel: String
    let birthDate: LocalDay
    let status: ServerStatus
    let guarantees: [String]
    let limits: Limits?
    let pendingRequest: DependentRequest?

    var fullName: String { "\(firstName) \(lastName)" }
}

struct DependentRequest: Codable, Equatable, Sendable, Identifiable {
    enum Kind: String, Codable, Sendable { case add, remove }
    let id: String
    let kind: Kind
    let fullName: String
    let status: ServerStatus
    let createdAt: Date
    let message: String?
}

struct DependentsResponse: Codable, Equatable, Sendable {
    let dependents: [Dependent]
    let requests: [DependentRequest]
    let canRequestChanges: Bool
    let info: String?
}

struct DependentAddRequest: Codable, Sendable {
    var firstName: String
    var lastName: String
    var relation: Relation
    var birthDate: LocalDay
    var gender: Gender
    var uploadIds: [String]
}

struct DependentRemoveRequest: Codable, Sendable {
    let reason: String
}

// MARK: - Vault

enum VaultCategory: String, Codable, Sendable, CaseIterable, Identifiable {
    case prescription, labResult = "lab_result", imaging, vaccine
    var id: String { rawValue }
    var label: String {
        switch self {
        case .prescription: String(localized: "Ordonnances", bundle: .appLanguage)
        case .labResult: String(localized: "Analyses", bundle: .appLanguage)
        case .imaging: String(localized: "Radios", bundle: .appLanguage)
        case .vaccine: String(localized: "Vaccins", bundle: .appLanguage)
        }
    }
    var symbol: String {
        switch self {
        case .prescription: "pills"
        case .labResult: "testtube.2"
        case .imaging: "rays"
        case .vaccine: "syringe"
        }
    }
}

struct VaultDocument: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let category: VaultCategory
    let title: String
    let fileName: String
    let mimeType: String
    let size: Int
    let createdAt: Date
    let beneficiaryName: String?
}

struct VaultDocumentCreate: Codable, Sendable {
    let uploadId: String
    let category: VaultCategory
    let title: String
    let beneficiaryId: String?
}

// MARK: - Providers

enum ProviderType: String, Codable, Sendable, CaseIterable, Identifiable {
    case doctor, specialist, pharmacy, clinic, hospital, laboratory, other
    var id: String { rawValue }

    init(from decoder: Decoder) throws {
        let raw = try decoder.singleValueContainer().decode(String.self)
        self = ProviderType(rawValue: raw) ?? .other
    }

    var label: String {
        switch self {
        case .doctor: String(localized: "Médecins", bundle: .appLanguage)
        case .specialist: String(localized: "Spécialistes", bundle: .appLanguage)
        case .pharmacy: String(localized: "Pharmacies", bundle: .appLanguage)
        case .clinic: String(localized: "Cliniques", bundle: .appLanguage)
        case .hospital: String(localized: "Hôpitaux", bundle: .appLanguage)
        case .laboratory: String(localized: "Laboratoires", bundle: .appLanguage)
        case .other: String(localized: "Autres", bundle: .appLanguage)
        }
    }

    var symbol: String {
        switch self {
        case .doctor: "stethoscope"
        case .specialist: "heart.text.square"
        case .pharmacy: "cross.case"
        case .clinic: "building.2"
        case .hospital: "cross"
        case .laboratory: "testtube.2"
        case .other: "mappin"
        }
    }
}

struct Provider: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let name: String
    let type: ProviderType
    let specialty: String?
    let address: String
    let city: String
    let phone: String?
    /// Coordinates are optional: providers without them appear in the list but not on the map.
    let latitude: Double?
    let longitude: Double?
    /// True when the backend estimated the position (e.g. city centre): show it as approximate, route by address.
    var locationApproximate: Bool?
    let distanceMeters: Int?
    let tiersPayant: Bool
    let openingHours: String?
}

struct ProvidersResponse: Codable, Equatable, Sendable {
    let providers: [Provider]
}

// MARK: - Notifications

struct DeepLinkTarget: Codable, Hashable, Sendable {
    enum Kind: String, Codable, Sendable {
        case claim, payment, policy, dependents, card, vault, notifications, subscription
    }
    let kind: Kind
    let id: String?
}

struct AppNotification: Codable, Equatable, Sendable, Identifiable {
    let id: String
    let category: String
    let title: String
    let body: String
    let createdAt: Date
    var read: Bool
    let target: DeepLinkTarget?
}

struct NotificationsResponse: Codable, Equatable, Sendable {
    let notifications: [AppNotification]
    let unreadCount: Int
}

// MARK: - Uploads

/// tus-like resumable upload session (see `/uploads` in the OpenAPI spec).
struct UploadSession: Codable, Equatable, Sendable {
    let uploadId: String
    let chunkSize: Int
    let offset: Int
}

struct UploadCreateRequest: Codable, Sendable {
    let fileName: String
    let mimeType: String
    let size: Int
    /// "claim_document", "vault_document", "dependent_document", "profile_photo".
    let purpose: String
}
