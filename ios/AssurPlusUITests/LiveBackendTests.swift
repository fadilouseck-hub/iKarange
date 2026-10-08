import XCTest

/// End-to-end check against a real backend (local copy or staging). Skipped unless configured:
///   TEST_RUNNER_LIVE_API_URL=http://localhost:8080/api/mobile/v1 TEST_RUNNER_LIVE_USER=… TEST_RUNNER_LIVE_PASSWORD=… \
///   xcodebuild test -scheme AssurPlus -only-testing:AssurPlusUITests/LiveBackendTests …
/// Use a synthetic test member only — never real member credentials.
final class LiveBackendTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testLoginDashboardCardClaimAndNetwork() throws {
        let env = ProcessInfo.processInfo.environment
        guard let url = env["LIVE_API_URL"], let user = env["LIVE_USER"], let password = env["LIVE_PASSWORD"] else {
            throw XCTSkip("Live backend not configured")
        }
        let app = XCUIApplication()
        app.launchArguments = ["-APIBaseURL", url, "-ResetState", "-AppleLanguages", "(fr)"]
        app.launch()

        XCTAssertFalse(app.buttons["welcome.register"].exists, "self-registration is disabled for this tenant")
        app.buttons["welcome.login"].waitToExist().tap()
        let username = app.textFields["auth.username"].waitToExist()
        username.tap()
        username.typeText(user)
        app.secureTextFields["auth.password"].tap()
        app.secureTextFields["auth.password"].typeText(password)
        app.buttons["auth.submit"].tap()

        app.descendants(matching: .any)["home.policyCard"].firstMatch.waitToExist(15)
        screenshot(app, "live-home")

        app.buttons["tab.card"].tap()
        app.images["card.qr"].waitToExist(10)
        screenshot(app, "live-card")

        // Claim: no server OCR → manual entry.
        app.buttons["tab.claims"].tap()
        app.buttons["claims.new"].waitToExist().tap()
        app.buttons.matching(NSPredicate(format: "identifier BEGINSWITH 'claim.beneficiary.'")).firstMatch.waitToExist().tap()
        app.buttons["claim.type.pharmacie"].waitToExist().tap()
        app.buttons["claim.addReceipt"].waitToExist().tap()
        app.buttons["Importer un fichier"].waitToExist()
        // A photo/file picker needs a real device or library; the upload API is covered by the curl checks.
        app.buttons["Annuler"].firstMatch.tap()
        app.buttons["claim.close"].tap()

        app.buttons["tab.network"].tap()
        XCTAssertTrue(app.descendants(matching: .any).matching(NSPredicate(format: "identifier BEGINSWITH 'network.provider.'")).firstMatch.waitForExistence(timeout: 10))
        screenshot(app, "live-network")
    }
}
