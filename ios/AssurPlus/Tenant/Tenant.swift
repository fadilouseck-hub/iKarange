import Foundation

/// White-label tenant configuration, read from `Tenant.plist`. Shared features reference the tenant through
/// this type only (name, wordmark, brand colours, backend, support, legal links, languages).
struct Tenant: Decodable, Sendable {
    struct Colors: Decodable, Sendable {
        /// Deep brand colour: primary buttons, cards, QR modules (hex RRGGBB).
        let brandDark: String
        let brandDarkSecondary: String
        /// Bright brand colour: accents on dark backgrounds, logo mark.
        let brandAccent: String
        /// Accent with enough contrast on light backgrounds (links, icons).
        let accentOnLight: String
    }

    struct Features: Decodable, Sendable {
        let selfRegistration: Bool
        let otpLogin: Bool
        let passwordReset: Bool

        static let all = Features(selfRegistration: true, otpLogin: true, passwordReset: true)
    }

    enum LoginIdentifier: String, Decodable, Sendable { case phone, username }

    let id: String
    let displayName: String
    let wordmark: String
    let copyrightHolder: String
    let apiBaseURL: String
    let loginIdentifier: LoginIdentifier
    let features: Features
    let supportPhone: String
    let supportEmail: String
    let privacyPolicyURL: String
    let termsURL: String
    let defaultLanguage: String
    let supportedLanguages: [String]
    let colors: Colors

    static let current: Tenant = load()

    static func load(bundle: Bundle = .main) -> Tenant {
        guard let url = bundle.url(forResource: "Tenant", withExtension: "plist"),
              let data = try? Data(contentsOf: url),
              let tenant = try? PropertyListDecoder().decode(Tenant.self, from: data) else {
            fatalError("Tenant.plist is missing or invalid") // build configuration error, caught by tests
        }
        return tenant
    }

    var apiURL: URL? { URL(string: apiBaseURL) }
    var supportPhoneURL: URL? { supportPhone.isEmpty ? nil : URL(string: "tel:\(supportPhone.filter { $0.isNumber || $0 == "+" })") }
    var supportEmailURL: URL? { supportEmail.isEmpty ? nil : URL(string: "mailto:\(supportEmail)") }
    var privacyURL: URL? { privacyPolicyURL.isEmpty ? nil : URL(string: privacyPolicyURL) }
    var termsOfUseURL: URL? { termsURL.isEmpty ? nil : URL(string: termsURL) }

    static func hex(_ string: String) -> UInt32 { UInt32(string, radix: 16) ?? 0 }
}
