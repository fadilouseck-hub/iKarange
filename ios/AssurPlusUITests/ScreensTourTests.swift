import XCTest

/// Smoke tests that walk the secondary features and capture screenshots for review.
final class ScreensTourTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testPrincipalScreens() {
        let app = XCUIApplication.mock(signedInAs: "principal")
        app.descendants(matching: .any)["home.policyCard"].firstMatch.waitToExist()
        screenshot(app, "01-home")

        app.buttons["home.action.family"].waitToExist().tap()
        app.staticTexts["Fatou Diop"].waitToExist()
        screenshot(app, "02-family")
        // Request a new dependant (validated by the back office).
        app.buttons["family.add"].tap()
        app.textFields["family.firstName"].waitToExist().tap()
        app.textFields["family.firstName"].typeText("Aminata")
        app.textFields["family.lastName"].tap()
        app.textFields["family.lastName"].typeText("Diop")
        app.buttons["family.send"].tap()
        app.staticTexts["Ajout de Aminata Diop"].firstMatch.waitToExist()
        app.navigationBars.buttons.element(boundBy: 0).tap()

        app.buttons["home.action.policy"].waitToExist().tap()
        app.buttons["policy.openConditions"].waitToExist()
        screenshot(app, "03-policy")
        app.navigationBars.buttons.element(boundBy: 0).tap()

        app.buttons["home.action.vault"].waitToExist().tap()
        app.descendants(matching: .any)["vault.document.vlt_1"].firstMatch.waitToExist()
        screenshot(app, "04-vault")
        app.navigationBars.buttons.element(boundBy: 0).tap()

        app.buttons["home.action.payments"].waitToExist().tap()
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
        app.segmentedControls.buttons["Carte"].tap()
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
