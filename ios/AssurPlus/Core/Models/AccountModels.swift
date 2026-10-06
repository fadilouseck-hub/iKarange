import Foundation

/// Rights returned by the server at login. The UI hides anything not granted; the server enforces them.
enum Permission: String, Codable, Sendable, CaseIterable {
    case policyView = "policy.view"
    case policyManage = "policy.manage"
    case cardView = "card.view"
    case claimsView = "claims.view"
    case claimsCreate = "claims.create"
    case familyView = "family.view"
    case familyManage = "family.manage"
    case vaultView = "vault.view"
    case vaultManage = "vault.manage"
    case paymentsView = "payments.view"
    case paymentsCreate = "payments.create"
    case subscriptionCreate = "subscription.create"
    case profileEdit = "profile.edit"
}

enum AccountRole: String, Codable, Sendable {
    case principal
    case dependent
}

enum Gender: String, Codable, Sendable, CaseIterable, Identifiable {
    case female = "F"
    case male = "M"
    var id: String { rawValue }
    var label: String {
        switch self {
        case .female: String(localized: "Femme")
        case .male: String(localized: "Homme")
        }
    }
}

struct Me: Codable, Equatable, Sendable {
    var id: String
    var firstName: String
    var lastName: String
    var phone: String
    var email: String?
    var birthDate: LocalDay?
    var gender: Gender?
    var address: String?
    var city: String?
    var photoURL: URL?
    var role: AccountRole
    /// Raw permission strings; unknown values from newer servers are ignored.
    var permissions: [String]
    var memberNumber: String?
    var hasActivePolicy: Bool
    var preferredLanguage: String?

    var fullName: String { "\(firstName) \(lastName)" }
    var grantedPermissions: Set<Permission> { Set(permissions.compactMap(Permission.init(rawValue:))) }
}

struct AuthResponse: Codable, Sendable {
    let tokens: AuthTokens
    let user: Me
}

enum OTPPurpose: String, Codable, Sendable {
    case register
    case login
    case resetPassword = "reset_password"
}

struct OTPSendRequest: Codable, Sendable {
    let phone: String
    let purpose: OTPPurpose
}

struct OTPChallenge: Codable, Equatable, Sendable {
    let otpRequestId: String
    let expiresIn: Int
    let resendAfter: Int
    /// Masked destination, e.g. "+221 77 *** ** 01".
    let maskedPhone: String
}

struct OTPVerifyRequest: Codable, Sendable {
    let otpRequestId: String
    let code: String
}

struct OTPVerification: Codable, Sendable {
    /// Short-lived proof that the phone number was verified; used by register, OTP login and reset.
    let verificationToken: String
}

struct LoginRequest: Codable, Sendable {
    let phone: String
    var password: String?
    var verificationToken: String?
}

struct RegisterRequest: Codable, Sendable {
    var verificationToken: String
    var firstName: String
    var lastName: String
    var birthDate: LocalDay
    var gender: Gender
    var email: String?
    var address: String?
    var city: String?
    var password: String?
    var cguVersion: String
}

struct PasswordResetRequest: Codable, Sendable {
    let verificationToken: String
    let newPassword: String
}

struct PasswordChangeRequest: Codable, Sendable {
    let currentPassword: String
    let newPassword: String
}

struct LegalDocument: Codable, Identifiable, Sendable {
    let id: String
    let title: String
    let version: String
    let url: URL
}

struct MeUpdate: Codable, Sendable {
    var email: String?
    var address: String?
    var city: String?
    var preferredLanguage: String?
}

struct NotificationPreferences: Codable, Equatable, Sendable {
    struct Channel: Codable, Equatable, Identifiable, Sendable {
        let id: String
        let label: String
        var push: Bool
        var sms: Bool
        var email: Bool
    }
    var categories: [Channel]
}

struct DeviceRegistration: Codable, Sendable {
    let token: String
    let platform: String
    let locale: String
    let appVersion: String
}
