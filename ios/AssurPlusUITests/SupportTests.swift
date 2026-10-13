import XCTest

/// Assistant button and Profile > feedback, on the MockAPI.
final class SupportTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testAssistantThenFeedback() {
        let app = XCUIApplication.mock(signedInAs: "principal")
        // The assistant button floats above the menu on every tab. Ask through a suggestion, then by typing.
        app.buttons["assistant.orb"].waitToExist().tap()
        app.buttons["assistant.suggestion.0"].waitToExist().tap()
        app.staticTexts["assistant.message.assistant"].firstMatch.waitToExist()
        let input = app.textFields["assistant.input"].exists ? app.textFields["assistant.input"] : app.textViews["assistant.input"]
        input.waitToExist().tap()
        input.typeText("Et ma carte ?")
        app.buttons["assistant.send"].tap()
        waitUntil(10, { app.staticTexts.matching(identifier: "assistant.message.assistant").count == 2 }, message: "second answer")
        screenshot(app, "20-assistant")
        app.navigationBars.buttons[L("Fermer", "Close")].tap()

        // Send a bug report.
        app.tab("Profil").waitToExist().tap()
        app.switches["profile.biometrics"].waitToExist()
        app.swipeUp()
        app.buttons["profile.feedback"].waitToExist().tap()
        let message = app.textFields["feedback.message"].exists ? app.textFields["feedback.message"] : app.textViews["feedback.message"]
        message.waitToExist()
        XCTAssertFalse(app.buttons["feedback.send"].isEnabled)
        app.staticTexts[L("Problème technique", "Technical problem")].firstMatch.tap()
        message.tap()
        message.typeText("La carte ne s'affiche pas hors connexion.")
        screenshot(app, "21-feedback")
        app.buttons["feedback.send"].tap()
        app.staticTexts[L("Merci, message envoyé", "Thank you, message sent")].waitToExist()
        screenshot(app, "22-feedback-sent")
    }
}
