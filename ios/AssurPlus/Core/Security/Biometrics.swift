import Foundation
import LocalAuthentication

protocol BiometricAuthenticating: Sendable {
    var availableKind: BiometricKind { get }
    func authenticate(reason: String) async -> Bool
}

enum BiometricKind: Sendable {
    case none, faceID, touchID, opticID

    var label: String {
        switch self {
        case .none: String(localized: "Verrouillage biométrique")
        case .faceID: "Face ID"
        case .touchID: "Touch ID"
        case .opticID: "Optic ID"
        }
    }

    var symbol: String {
        switch self {
        case .touchID: "touchid"
        case .opticID: "opticid"
        default: "faceid"
        }
    }
}

struct DeviceBiometrics: BiometricAuthenticating {
    var availableKind: BiometricKind {
        let context = LAContext()
        guard context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil) else { return .none }
        switch context.biometryType {
        case .faceID: return .faceID
        case .touchID: return .touchID
        case .opticID: return .opticID
        default: return .none
        }
    }

    func authenticate(reason: String) async -> Bool {
        let context = LAContext()
        context.localizedFallbackTitle = String(localized: "Utiliser le code")
        return (try? await context.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: reason)) ?? false
    }
}

struct AlwaysBiometrics: BiometricAuthenticating {
    var availableKind: BiometricKind { .faceID }
    func authenticate(reason: String) async -> Bool { true }
}
