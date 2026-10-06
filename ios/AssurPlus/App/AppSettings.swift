import Foundation
import Observation

/// Local, non-sensitive preferences.
@MainActor
@Observable
final class AppSettings {
    private let defaults: UserDefaults

    var biometricLockEnabled: Bool {
        didSet { defaults.set(biometricLockEnabled, forKey: "biometricLockEnabled") }
    }

    var hasSeenWelcome: Bool {
        didSet { defaults.set(hasSeenWelcome, forKey: "hasSeenWelcome") }
    }

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        biometricLockEnabled = defaults.bool(forKey: "biometricLockEnabled")
        hasSeenWelcome = defaults.bool(forKey: "hasSeenWelcome")
    }
}
