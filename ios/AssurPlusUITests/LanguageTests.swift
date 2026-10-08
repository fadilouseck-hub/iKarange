import XCTest

/// French is the default/fallback, English follows the device, and the in-app choice applies immediately.
final class LanguageTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    private func launch(_ language: String, signedIn: Bool) -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["-UseMockAPI", "-ResetState", "-MockLatency", "0.05", "-AppleLanguages", "(\(language))"]
        if signedIn { app.launchArguments += ["-MockSignIn", "principal"] }
        app.launch()
        return app
    }

    func testEnglishDeviceShowsEnglishAndCanSwitchToFrench() {
        let app = launch("en", signedIn: false)
        XCTAssertEqual(app.buttons["welcome.login"].waitToExist().label, "Sign in")
        screenshot(app, "en-welcome")
        app.buttons["welcome.login"].tap()
        XCTAssertTrue(app.navigationBars["Sign in"].waitForExistence(timeout: 5))
        app.terminate()

        let signedIn = launch("en", signedIn: true)
        XCTAssertTrue(signedIn.staticTexts["Remaining limit"].waitForExistence(timeout: 10))
        XCTAssertEqual(signedIn.buttons["tab.claims"].label, "Claims")
        signedIn.buttons["tab.card"].tap()
        XCTAssertTrue(signedIn.navigationBars["Direct billing card"].waitForExistence(timeout: 5))
        signedIn.buttons["tab.claims"].tap()
        XCTAssertTrue(signedIn.navigationBars["Claims"].waitForExistence(timeout: 5))
        screenshot(signedIn, "en-claims")

        signedIn.buttons["tab.profile"].tap()
        signedIn.buttons["profile.language"].waitToExist().tap()
        signedIn.buttons["Français"].waitToExist().tap()
        XCTAssertTrue(signedIn.navigationBars["Profil"].waitForExistence(timeout: 5))
        XCTAssertEqual(signedIn.buttons["tab.home"].label, "Accueil")
        screenshot(signedIn, "fr-after-switch")
    }

    func testUnsupportedDeviceLanguageFallsBackToFrench() {
        let app = launch("de", signedIn: false)
        XCTAssertEqual(app.buttons["welcome.login"].waitToExist().label, "Se connecter")
    }
}
