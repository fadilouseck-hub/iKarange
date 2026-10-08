import Foundation
import SwiftUI
import Observation

/// Local, non-sensitive preferences.
@MainActor
@Observable
final class AppSettings {
    let defaults: UserDefaults

    var biometricLockEnabled: Bool {
        didSet { defaults.set(biometricLockEnabled, forKey: "biometricLockEnabled") }
    }

    /// Light / dark / follow the system.
    var appearance: Appearance {
        didSet { defaults.set(appearance.rawValue, forKey: "appearance") }
    }

    var hasSeenWelcome: Bool {
        didSet { defaults.set(hasSeenWelcome, forKey: "hasSeenWelcome") }
    }

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        biometricLockEnabled = defaults.bool(forKey: "biometricLockEnabled")
        hasSeenWelcome = defaults.bool(forKey: "hasSeenWelcome")
        appearance = Appearance(rawValue: defaults.string(forKey: "appearance") ?? "") ?? .system
    }
}

enum Appearance: String, CaseIterable, Identifiable, Sendable {
    case system, light, dark
    var id: Self { self }

    var colorScheme: ColorScheme? {
        switch self {
        case .system: nil
        case .light: .light
        case .dark: .dark
        }
    }

    var title: LocalizedStringKey {
        switch self {
        case .system: "Automatique"
        case .light: "Clair"
        case .dark: "Sombre"
        }
    }
}
