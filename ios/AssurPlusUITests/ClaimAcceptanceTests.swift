import XCTest

/// Acceptance scenario 3 — claim: photograph an invoice → submit → get a claim number → follow its status.
final class ClaimAcceptanceTests: XCTestCase {
    override func setUp() { continueAfterFailure = false }

    func testDeclareClaimAndFollowStatus() {
        let app = XCUIApplication.mock(signedInAs: "principal")

        app.tab("Sinistres").waitToExist().tap()
        app.buttons["claims.new"].waitToExist().tap()

        app.buttons["claim.beneficiary.ben_fatou"].waitToExist().tap()
        app.buttons["claim.type.pharmacy"].waitToExist().tap()
        app.buttons["claim.addReceipt"].waitToExist().tap()
        // No camera in the Simulator: MockAPI offers a sample invoice (same pipeline: compress → upload → OCR).
        app.buttons[L("Utiliser une facture d'exemple", "Use a sample invoice")].waitToExist().tap()

        // Upload + server OCR, then the pre-filled review form.
        let submit = app.buttons["claim.submit"].waitToExist(20)
        XCTAssertTrue(app.staticTexts[L("2 information(s) à vérifier en priorité (surlignées).", "2 item(s) to check first (highlighted).")].exists)
        let total = app.textFields.matching(identifier: "claim.field.total").firstMatch
        XCTAssertEqual(total.value as? String, "20000")
        let patient = app.textFields.matching(identifier: "claim.field.patient").firstMatch
        patient.tap()
        patient.typeText("Fatou Diop")
        screenshot(app, "claim-review")

        submit.tap()
        let number = app.staticTexts["claim.number"].waitToExist(10)
        XCTAssertTrue(number.label.hasPrefix("SIN-2026-"), number.label)
        let claimNumber = number.label
        screenshot(app, "claim-number")

        app.buttons["claim.track"].tap()
        let status = app.descendants(matching: .any)["claimDetail.status"].firstMatch.waitToExist()
        XCTAssertTrue(app.descendants(matching: .any)["claimDetail.number"].firstMatch.label.contains(claimNumber))
        XCTAssertTrue(status.label.contains(L("Soumis", "Submitted")) || status.label.contains(L("En analyse", "Under review")), status.label)

        // Pull to refresh: the claim progresses to "En analyse".
        waitUntil(15, {
            app.pullToRefresh()
            return app.descendants(matching: .any)["claimDetail.status"].firstMatch.label.contains(L("En analyse", "Under review"))
        }, message: "status update")
        XCTAssertTrue(app.descendants(matching: .any)["claimDetail.timeline"].firstMatch.exists)
        screenshot(app, "claim-tracking")
    }
}
