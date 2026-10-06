import Foundation

/// Shapes of the JSON files in `Resources/MockFixtures`.
struct MockAccountFixture: Decodable {
    struct User: Decodable {
        let phone: String
        let password: String
        let me: Me
    }
    let users: [User]
    let policy: PolicyDetail
    let limits: Limits
    let card: MemberCard
    let dependents: DependentsResponse
    let claims: [Claim]
    let payments: [Payment]
    let vault: [VaultDocument]
    let notifications: [AppNotification]
}

struct MockCatalogFixture: Decodable {
    let products: [Product]
    let questionnaire: HealthQuestionnaire
    let paymentMethods: [PaymentMethod]
    let claimTypes: [ClaimType]
    let ocr: OCRResult
    let legal: [LegalDocument]
    let notificationPreferences: NotificationPreferences
}

struct MockProvidersFixture: Decodable {
    let providers: [Provider]
}

enum MockFixtures {
    static func load<T: Decodable>(_ type: T.Type, named name: String, bundle: Bundle = .main) throws -> T {
        guard let url = bundle.url(forResource: name, withExtension: "json") else {
            throw CocoaError(.fileNoSuchFile, userInfo: [NSFilePathErrorKey: name])
        }
        return try JSONCoding.decoder().decode(T.self, from: Data(contentsOf: url))
    }

    static func account(bundle: Bundle = .main) throws -> MockAccountFixture {
        try load(MockAccountFixture.self, named: "mock-account", bundle: bundle)
    }

    static func catalog(bundle: Bundle = .main) throws -> MockCatalogFixture {
        try load(MockCatalogFixture.self, named: "mock-catalog", bundle: bundle)
    }

    static func providers(bundle: Bundle = .main) throws -> MockProvidersFixture {
        try load(MockProvidersFixture.self, named: "mock-providers", bundle: bundle)
    }
}
