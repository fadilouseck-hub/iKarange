import XCTest

/// Acceptance scenario 1 — subscription: create an account → choose a formula → get a quote → pay → receive the contract.
final class SubscriptionAcceptanceTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testCreateAccountSubscribeAndPay() {
        let app = XCUIApplication.mock()

        // 1. Create the account (phone verified by SMS OTP, CGU accepted).
        app.buttons["welcome.register"].waitToExist().tap()
        let phone = app.textFields["auth.phone"].waitToExist()
        // Keystrokes can be dropped while the field formats input: retype until the full number is there.
        for _ in 0..<3 where (phone.value as? String) != "78 111 22 33" {
            phone.tap()
            if let current = phone.value as? String, current != phone.placeholderValue {
                phone.typeText(String(repeating: XCUIKeyboardKey.delete.rawValue, count: current.count))
            }
            phone.typeText("781112233")
        }
        XCTAssertEqual(phone.value as? String, "78 111 22 33")
        app.buttons["register.sendCode"].tap()
        let otp = app.textFields["auth.otp"].waitToExist()
        otp.tap()
        otp.typeText("123456") // auto-submits at 6 digits

        app.textFields["register.firstName"].waitToExist().tap()
        app.textFields["register.firstName"].typeText("Ndeye")
        app.textFields["register.lastName"].tap()
        app.textFields["register.lastName"].typeText("Fall")
        // OTP-only account (no password): also avoids the Simulator's strong-password autofill.
        toggleOff(app.switches["register.usePassword"], in: app)
        toggleOn(app.switches["register.cgu"], in: app)
        app.buttons["register.submit"].tap()

        // 2. No contract yet → subscribe.
        app.buttons["home.subscribe"].waitToExist(10).tap()
        let next = app.buttons["subscription.next"].waitToExist()
        next.tap() // identification
        next.tap() // personal information

        // Health questionnaire: answer "Non" to each yes/no question.
        for id in ["q_chronic", "q_hospital", "q_incurable"] {
            let picker = app.segmentedControls["question.\(id)"].waitToExist()
            picker.buttons["Non"].tap()
        }
        next.tap()

        // 3. Formula + coverage rate, live premium.
        app.buttons["subscription.product.prd_essentiel"].waitToExist().tap()
        let total = app.staticTexts["subscription.total"].waitToExist(10)
        Thread.sleep(forTimeInterval: 1.5) // let the debounced quote for Essentiel land
        let essentialTotal = total.label
        app.segmentedControls["subscription.rate"].buttons.element(boundBy: 0).tap() // 70 %
        waitUntil(5, { app.staticTexts["subscription.total"].label != essentialTotal }, message: "premium recalculation")
        screenshot(app, "subscription-guarantees")
        next.tap()

        // 4. Quote details, then validation of the special conditions.
        XCTAssertTrue(app.staticTexts["Vous êtes éligible à cette formule."].waitForExistence(timeout: 5))
        next.tap()
        toggleOn(app.switches["subscription.acceptConditions"].waitToExist(), in: app)
        next.tap()

        // 5. Pay with Wave (MockAPI simulates the provider), server confirms.
        app.buttons["payment.method.wave"].waitToExist(10).tap()
        app.buttons["payment.pay"].tap()
        app.buttons["payment.continue"].waitToExist(20).tap()

        // 6. Contract issued.
        let policyNumber = app.descendants(matching: .any)["subscription.policyNumber"].firstMatch.waitToExist()
        XCTAssertTrue(policyNumber.label.contains("POL-2026-"), policyNumber.label)
        screenshot(app, "subscription-confirmation")
        app.buttons["subscription.showCard"].tap()
        app.images["card.qr"].waitToExist(10)
    }

    private func toggleOff(_ toggle: XCUIElement, in app: XCUIApplication) {
        toggle.waitToExist()
        dismissKeyboard(app)
        toggle.coordinate(withNormalizedOffset: CGVector(dx: 0.93, dy: 0.5)).tap()
        if (toggle.value as? String) != "0" { toggle.switches.firstMatch.tap() }
        XCTAssertEqual(toggle.value as? String, "0")
    }

    private func dismissKeyboard(_ app: XCUIApplication) {
        if app.keyboards.count > 0 { app.navigationBars.firstMatch.tap() }
    }

    private func toggleOn(_ toggle: XCUIElement, in app: XCUIApplication) {
        toggle.waitToExist()
        dismissKeyboard(app)
        // Tap the switch itself (trailing edge), not the label.
        toggle.coordinate(withNormalizedOffset: CGVector(dx: 0.93, dy: 0.5)).tap()
        if (toggle.value as? String) != "1" { toggle.switches.firstMatch.tap() }
        XCTAssertEqual(toggle.value as? String, "1")
    }
}
