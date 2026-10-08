import XCTest

/// Smoke tests that walk the secondary features and capture screenshots for review.
final class ScreensTourTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testPrincipalScreens() {
        let app = XCUIApplication.mock(signedInAs: "principal")
        app.descendants(matching: .any)["home.policyCard"].firstMatch.waitToExist()
        screenshot(app, "01-home")

        tapHomeAction("family", in: app)
        app.staticTexts["Fatou Diop"].waitToExist()
        screenshot(app, "02-family")
        // Request a new dependant (validated by the back office).
        app.buttons["family.add"].tap()
        app.textFields["family.firstName"].waitToExist().tap()
        app.textFields["family.firstName"].typeText("Aminata")
        app.textFields["family.lastName"].tap()
        app.textFields["family.lastName"].typeText("Diop")
        app.buttons["family.send"].tap()
        app.staticTexts[L("Ajout de Aminata Diop", "Adding Aminata Diop")].firstMatch.waitToExist()
        app.navigationBars.buttons.element(boundBy: 0).tap()

        tapHomeAction("policy", in: app)
        app.buttons["policy.openConditions"].waitToExist()
        screenshot(app, "03-policy")
        app.navigationBars.buttons.element(boundBy: 0).tap()

        tapHomeAction("vault", in: app)
        app.descendants(matching: .any)["vault.document.vlt_1"].firstMatch.waitToExist()
        screenshot(app, "04-vault")
        app.navigationBars.buttons.element(boundBy: 0).tap()

        tapHomeAction("payments", in: app)
        app.staticTexts["Cotisation annuelle 2026"].waitToExist()
        screenshot(app, "05-payments")
        app.navigationBars.buttons.element(boundBy: 0).tap()

        // Notification → deep link into the claim with a missing document.
        app.buttons["home.notifications"].waitToExist().tap()
        app.buttons["notification.ntf_1"].waitToExist()
        screenshot(app, "06-notifications")
        app.buttons["notification.ntf_1"].tap()
        app.buttons["claimDetail.addDocument.req_1"].waitToExist()
        screenshot(app, "07-claim-documents-requested")

        app.tab("Réseau").tap()
        app.descendants(matching: .any)["network.provider.prv_1"].firstMatch.waitToExist()
        app.buttons["network.type.pharmacy"].tap()
        waitUntil(5, { !app.descendants(matching: .any)["network.provider.prv_1"].firstMatch.exists }, message: "pharmacy filter")
        screenshot(app, "08-network-list")
        app.segmentedControls.buttons[L("Carte", "Map")].tap()
        sleep(2)
        screenshot(app, "09-network-map")

        app.tab("Profil").tap()
        app.switches["profile.biometrics"].waitToExist()
        screenshot(app, "10-profile")
        app.swipeUp(); app.swipeUp()
        app.buttons["profile.logout"].waitToExist().tap()
        app.buttons["profile.logout.confirm"].firstMatch.waitToExist().tap()
        app.buttons["welcome.login"].waitToExist()
    }

    /// Quick actions can sit under the floating menu: scroll them into view first.
    private func tapHomeAction(_ id: String, in app: XCUIApplication) {
        let button = app.buttons["home.action.\(id)"].waitToExist()
        let bar = app.buttons["tab.home"].frame
        var attempts = 0
        while button.frame.maxY > bar.minY - 8 && attempts < 4 {
            app.swipeUp()
            attempts += 1
        }
        button.tap()
    }

    func testDependentSeesRestrictedFeatures() {
        let app = XCUIApplication.mock(signedInAs: "dependent")
        app.descendants(matching: .any)["home.policyCard"].firstMatch.waitToExist()
        XCTAssertTrue(app.buttons["home.action.card"].exists)
        XCTAssertTrue(app.buttons["home.action.claim"].exists)
        XCTAssertFalse(app.buttons["home.action.family"].exists, "dependants cannot manage the family")
        XCTAssertFalse(app.buttons["home.action.payments"].exists)
        screenshot(app, "11-dependent-home")

        app.tab("Carte").tap()
        app.images["card.qr"].waitToExist()
        XCTAssertFalse(app.buttons["card.beneficiary.ben_awa"].exists, "only their own card")
    }
}
