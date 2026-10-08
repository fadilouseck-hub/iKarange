import Foundation
import Observation
import ObjectiveC

/// Language of the app UI. Initial value follows the device when it is supported by the tenant, otherwise the
/// tenant default (French). A choice made in Profile overrides it and persists. Changes apply immediately.
enum AppLanguage {
    private static let lock = NSLock()
    nonisolated(unsafe) private static var storedCode = Tenant.current.defaultLanguage
    static let preferenceKey = "app.language"

    /// Current language code ("fr", "en"). Safe from any thread (formatters, API headers).
    static var code: String { lock.withLock { storedCode } }

    static var locale: Locale { locale(for: code) }

    static func locale(for code: String) -> Locale {
        // Keep Senegalese regional conventions (dates, numbers) in every language.
        Locale(identifier: "\(code)_SN")
    }

    /// First device language supported by the tenant, else the tenant default.
    static func resolve(preferred: [String], supported: [String], fallback: String) -> String {
        for identifier in preferred {
            let language = Locale(identifier: identifier).language.languageCode?.identifier ?? identifier
            if supported.contains(language) { return language }
        }
        return fallback
    }

    static func set(_ code: String) {
        lock.withLock { storedCode = code }
        LocalizedBundle.select(code)
    }
}

extension Bundle {
    /// The `.lproj` of the language chosen in the app, for `String(localized:bundle:)` — which, unlike SwiftUI
    /// `Text`, ignores the device-language override. Falls back to the main bundle.
    static var appLanguage: Bundle { AppLanguage.bundle }
}

extension AppLanguage {
    private static let bundleLock = NSLock()
    nonisolated(unsafe) private static var bundles: [String: Bundle] = [:]

    static var bundle: Bundle {
        let code = code
        return bundleLock.withLock {
            if let cached = bundles[code] { return cached }
            let bundle = Bundle.main.path(forResource: code, ofType: "lproj").flatMap(Bundle.init(path:)) ?? .main
            bundles[code] = bundle
            return bundle
        }
    }
}

@MainActor
@Observable
final class LanguageSettings {
    private let defaults: UserDefaults
    let supported: [String]
    private(set) var code: String
    /// True when the user has not chosen a language and the device language is used.
    private(set) var followsDevice: Bool

    init(defaults: UserDefaults = .standard, tenant: Tenant = .current, preferred: [String] = Locale.preferredLanguages) {
        self.defaults = defaults
        supported = tenant.supportedLanguages
        if let saved = defaults.string(forKey: AppLanguage.preferenceKey), tenant.supportedLanguages.contains(saved) {
            code = saved
            followsDevice = false
        } else {
            code = AppLanguage.resolve(preferred: preferred, supported: tenant.supportedLanguages, fallback: tenant.defaultLanguage)
            followsDevice = true
        }
        AppLanguage.set(code)
    }

    var locale: Locale { AppLanguage.locale(for: code) }

    func select(_ newCode: String) {
        guard supported.contains(newCode) else { return }
        defaults.set(newCode, forKey: AppLanguage.preferenceKey)
        followsDevice = false
        AppLanguage.set(newCode)
        code = newCode
    }

    /// Name of a language written in that language (shown in the picker).
    static func nativeName(_ code: String) -> String {
        Locale(identifier: code).localizedString(forLanguageCode: code)?.capitalized(with: Locale(identifier: code)) ?? code
    }
}

/// Routes `Bundle.main` string lookups (SwiftUI `Text`, `String(localized:)`, `NSLocalizedString`) to the selected
/// `.lproj`, so the language can change without relaunching.
private final class LocalizedBundle: Bundle, @unchecked Sendable {
    private enum Selection { case none, sourceLanguage, bundle(Bundle) }
    nonisolated(unsafe) private static var selection: Selection = .none
    private static let lock = NSLock()
    /// Strings are written in the source language (French) in code, so it needs no lookup table.
    private static let sourceLanguage = "fr"

    static func select(_ code: String) {
        lock.withLock {
            if !(object_getClass(Bundle.main) is LocalizedBundle.Type) {
                object_setClass(Bundle.main, LocalizedBundle.self)
            }
            if let bundle = Bundle.main.path(forResource: code, ofType: "lproj").flatMap(Bundle.init(path:)),
               bundle.path(forResource: "Localizable", ofType: "strings") != nil {
                selection = .bundle(bundle)
            } else {
                selection = code == sourceLanguage ? .sourceLanguage : .none
            }
        }
    }

    override func localizedString(forKey key: String, value: String?, table tableName: String?) -> String {
        switch Self.lock.withLock({ Self.selection }) {
        case .bundle(let bundle):
            return bundle.localizedString(forKey: key, value: value, table: tableName)
        case .sourceLanguage where tableName == nil || tableName == "Localizable":
            return (value?.isEmpty == false ? value : nil) ?? key
        default:
            return super.localizedString(forKey: key, value: value, table: tableName)
        }
    }

}
