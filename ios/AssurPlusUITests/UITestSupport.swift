import XCTest

/// Language of the UI-test run: French by default, English with `TEST_RUNNER_UI_LANGUAGE=en`.
let uiLanguage = ProcessInfo.processInfo.environment["UI_LANGUAGE"] == "en" ? "en" : "fr"

/// The app label in the language of the run.
func L(_ fr: String, _ en: String) -> String { uiLanguage == "en" ? en : fr }

extension XCUIApplication {
    /// Launches on the in-process MockAPI with a fresh state.
    static func mock(signedInAs account: String? = nil, extra: [String] = []) -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["-UseMockAPI", "-ResetState", "-MockLatency", "0.05", "-AppleLanguages", "(\(uiLanguage))", "-AppleLocale", "\(uiLanguage)_SN"] + extra
        if let account { app.launchArguments += ["-MockSignIn", account] }
        app.launch()
        return app
    }

    /// A slow drag from the top of the content, long enough to trigger `.refreshable`.
    func pullToRefresh() {
        let start = coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.3))
        let end = coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.85))
        start.press(forDuration: 0.1, thenDragTo: end, withVelocity: .slow, thenHoldForDuration: 0.3)
    }

    /// Floating menu button by its French label (identifiers are language-independent).
    func tab(_ label: String) -> XCUIElement {
        let ids = ["Accueil": "home", "Carte": "card", "Sinistres": "claims", "Réseau": "network", "Profil": "profile"]
        return buttons["tab.\(ids[label] ?? label)"]
    }
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
