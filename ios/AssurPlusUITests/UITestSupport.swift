import XCTest

extension XCUIApplication {
    /// Launches on the in-process MockAPI with a fresh state.
    static func mock(signedInAs account: String? = nil, extra: [String] = []) -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["-UseMockAPI", "-ResetState", "-MockLatency", "0.05", "-AppleLanguages", "(fr)", "-AppleLocale", "fr_SN"] + extra
        if let account { app.launchArguments += ["-MockSignIn", account] }
        app.launch()
        return app
    }

    func tab(_ label: String) -> XCUIElement { tabBars.buttons[label] }
}

extension XCUIElement {
    @discardableResult
    func waitToExist(_ timeout: TimeInterval = 10, file: StaticString = #filePath, line: UInt = #line) -> XCUIElement {
        XCTAssertTrue(waitForExistence(timeout: timeout), "\(self) did not appear", file: file, line: line)
        return self
    }

    /// Replaces the field content.
    func replaceText(_ text: String) {
        tap()
        if let current = value as? String, !current.isEmpty, current != placeholderValue {
            typeText(String(repeating: XCUIKeyboardKey.delete.rawValue, count: current.count))
        }
        typeText(text)
    }
}

extension XCTestCase {
    func waitUntil(_ timeout: TimeInterval = 10, _ condition: @escaping () -> Bool, message: String = "condition", file: StaticString = #filePath, line: UInt = #line) {
        let deadline = Date().addingTimeInterval(timeout)
        while Date() < deadline {
            if condition() { return }
            RunLoop.current.run(until: Date().addingTimeInterval(0.2))
        }
        XCTFail("Timed out waiting for \(message)", file: file, line: line)
    }

    func screenshot(_ app: XCUIApplication, _ name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }
}
