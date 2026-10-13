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
        sleep(4)
        screenshot(app, "live-after-submit") // shows the server's error message if sign-in fails

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

    /// Online subscription in test mode: a tester without a contract subscribes and pays on the server's test page.
    ///   TEST_RUNNER_LIVE_SUB_USER=… TEST_RUNNER_LIVE_SUB_PASSWORD=… (database/create_subscription_tester.php; --restart between runs)
    func testOnlineSubscriptionWithTestPayment() throws {
        let env = ProcessInfo.processInfo.environment
        guard let url = env["LIVE_API_URL"], let user = env["LIVE_SUB_USER"], let password = env["LIVE_SUB_PASSWORD"] else {
            throw XCTSkip("Live subscription tester not configured")
        }
        let app = XCUIApplication()
        app.launchArguments = ["-APIBaseURL", url, "-ResetState", "-AppleLanguages", "(fr)"]
        app.launch()
        app.buttons["welcome.login"].waitToExist().tap()
        let username = app.textFields["auth.username"].waitToExist()
        username.tap()
        username.typeText(user)
        app.secureTextFields["auth.password"].tap()
        app.secureTextFields["auth.password"].typeText(password)
        app.buttons["auth.submit"].tap()

        // No contract: no card or claims tab, and the home screen offers to subscribe.
        app.buttons["home.subscribe"].waitToExist(15)
        XCTAssertFalse(app.buttons["tab.card"].exists)
        screenshot(app, "live-sub-home")
        app.buttons["home.subscribe"].tap()
        let next = app.buttons["subscription.next"].waitToExist()
        next.tap() // identification
        next.tap() // personal information
        for id in ["q_chronic", "q_hospital", "q_incurable"] {
            app.segmentedControls["question.\(id)"].waitToExist().buttons["Non"].tap()
        }
        next.tap()
        app.buttons["subscription.product.prd_essentiel"].waitToExist(10).tap()
        app.staticTexts["subscription.total"].waitToExist(10)
        Thread.sleep(forTimeInterval: 1.5)
        screenshot(app, "live-sub-guarantees")
        next.tap()
        XCTAssertTrue(app.staticTexts["Vous êtes éligible à cette formule."].waitForExistence(timeout: 10))
        next.tap()
        let accept = app.switches["subscription.acceptConditions"].waitToExist()
        accept.coordinate(withNormalizedOffset: CGVector(dx: 0.93, dy: 0.5)).tap()
        if (accept.value as? String) != "1" { accept.switches.firstMatch.tap() }
        next.tap()

        // Card needs no phone number. The system asks before opening the hosted page.
        app.buttons["payment.method.card"].waitToExist(10).tap()
        app.buttons["payment.pay"].tap()
        let springboard = XCUIApplication(bundleIdentifier: "com.apple.springboard")
        let allow = springboard.buttons["Continuer"].exists ? springboard.buttons["Continuer"] : springboard.buttons["Continue"]
        if allow.waitForExistence(timeout: 10) { allow.tap() }
        let pay = app.webViews.buttons["Simuler un paiement réussi"]
        pay.waitToExist(20)
        screenshot(app, "live-sub-test-payment")
        pay.tap()

        app.buttons["payment.continue"].waitToExist(20).tap()
        let policyNumber = app.descendants(matching: .any)["subscription.policyNumber"].firstMatch.waitToExist()
        XCTAssertTrue(policyNumber.label.contains("ASP-TEST-"), policyNumber.label)
        screenshot(app, "live-sub-confirmation")
        app.buttons["subscription.showCard"].tap()
        app.images["card.qr"].waitToExist(15)
        screenshot(app, "live-sub-card")
    }
}
