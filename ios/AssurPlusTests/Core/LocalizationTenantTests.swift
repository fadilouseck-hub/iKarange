import Foundation
import Testing
@testable import AssurPlus

@MainActor
@Suite("Localization & tenant", .serialized)
struct LocalizationTenantTests {
    @Test(arguments: [
        (["en-US"], "en"), (["fr-SN"], "fr"), (["wo-SN"], "fr"), (["de-DE", "en-GB"], "en"), ([String](), "fr"),
    ])
    func initialLanguageFollowsSupportedDeviceLanguageElseFrench(preferred: [String], expected: String) {
        #expect(AppLanguage.resolve(preferred: preferred, supported: ["fr", "en"], fallback: "fr") == expected)
    }

    @Test func userChoicePersistsAndSwitchesStringsImmediately() {
        defer { AppLanguage.set("fr") } // other suites assert French strings
        let defaults = UserDefaults(suiteName: "lang-\(UUID().uuidString)")!
        let english = LanguageSettings(defaults: defaults, preferred: ["en-US"])
        #expect(english.code == "en")
        #expect(english.followsDevice)
        #expect(String(localized: "Se connecter", bundle: .appLanguage) == "Sign in")

        english.select("fr")
        #expect(String(localized: "Se connecter", bundle: .appLanguage) == "Se connecter")
        #expect(AppLanguage.code == "fr")

        // Relaunch with an English device: the explicit French choice wins.
        let relaunched = LanguageSettings(defaults: defaults, preferred: ["en-US"])
        #expect(relaunched.code == "fr")
        #expect(!relaunched.followsDevice)

        english.select("de") // unsupported → ignored
        #expect(AppLanguage.code == "fr")
    }

    @Test func everyCatalogStringHasAnEnglishTranslation() throws {
        let url = try #require(Bundle.main.url(forResource: "en", withExtension: "lproj"))
        let bundle = try #require(Bundle(url: url))
        for key in ["Accueil", "Déclarer un sinistre", "Plafond disponible", "Votre session a expiré. Veuillez vous reconnecter.", "Apparence"] {
            #expect(bundle.localizedString(forKey: key, value: "∅", table: nil) != key, "\(key) untranslated")
        }
    }

    @Test func tenantConfiguration() {
        let tenant = Tenant.current
        #expect(tenant.displayName == "Assur Plus")
        #expect(tenant.defaultLanguage == "fr")
        #expect(tenant.supportedLanguages == ["fr", "en"])
        #expect(tenant.apiURL?.host() == "ikarange.mcedge.sn")
        #expect(tenant.loginIdentifier == .username)
        #expect(!tenant.features.selfRegistration)
    }

    @Test func usernameLogin() async {
        let env = makeMockEnvironment()
        let model = LoginViewModel(api: env.api, session: env.session, identifierKind: .username)
        #expect(!model.canSubmit)
        model.username = " 770000001 "
        model.password = "assur1234"
        #expect(model.canSubmit)
        await model.submit()
        #expect(env.session.state == .signedIn)
    }

    @Test func mockBuildsEnableEveryFeature() {
        let env = makeMockEnvironment()
        #expect(env.features.selfRegistration && env.features.otpLogin)
        #expect(env.loginIdentifier == .phone)
    }
}
