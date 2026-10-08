import XCTest

/// Acceptance scenario 2 — tiers-payant card: open the card → QR code displayed and refreshed → add to Apple Wallet.
final class CardAcceptanceTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testCardShowsRefreshingQRAndAddsToWallet() {
        // Short-lived tokens so the automatic refresh happens during the test.
        let app = XCUIApplication.mock(signedInAs: "principal", extra: ["-MockQRLifetime", "12"])

        app.tab("Carte").waitToExist().tap()
        app.descendants(matching: .any)["card.member"].firstMatch.waitToExist()
        let qr = app.images["card.qr"].waitToExist()
        let firstToken = qr.value as? String
        XCTAssertNotNil(firstToken)
        XCTAssertFalse(firstToken?.contains("Awa") ?? true, "QR payload must not contain personal data")
        screenshot(app, "card-qr")

        // Token is renewed ~10 s before expiry.
        waitUntil(15, { (app.images["card.qr"].value as? String) != firstToken }, message: "QR token refresh")

        // Another beneficiary has its own card.
        app.buttons["card.beneficiary.ben_fatou"].tap()
        waitUntil(5, { app.descendants(matching: .any)["card.member"].firstMatch.label.contains("Fatou") }, message: "Fatou card")

        app.buttons["card.addToWallet"].firstMatch.tap()
        let message = app.descendants(matching: .any)["card.walletMessage"].firstMatch.waitToExist()
        XCTAssertTrue(message.label.contains(L("ajoutée", "added")), message.label)
        screenshot(app, "card-wallet")
    }
}
