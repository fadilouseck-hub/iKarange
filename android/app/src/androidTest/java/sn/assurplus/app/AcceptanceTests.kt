package sn.assurplus.app

import android.content.Intent
import androidx.compose.ui.semantics.SemanticsProperties
import androidx.compose.ui.semantics.getOrNull
import androidx.compose.ui.test.SemanticsNodeInteraction
import androidx.compose.ui.test.hasTestTag
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.ComposeTestRule
import androidx.compose.ui.test.junit4.createEmptyComposeRule
import androidx.compose.ui.test.onAllNodesWithTag
import androidx.compose.ui.test.onAllNodesWithText
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import androidx.compose.ui.test.performTouchInput
import androidx.compose.ui.test.swipeDown
import androidx.test.core.app.ActivityScenario
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.MainActivity

/**
 * The three acceptance scenarios of the specification, run on the mock flavor — ports of the iOS
 * `SubscriptionAcceptanceTests`, `CardAcceptanceTests`, `ClaimAcceptanceTests` and the dependant check of
 * `ScreensTourTests`, using the same identifiers (test tags).
 */
@RunWith(AndroidJUnit4::class)
class AcceptanceTests {
    @get:Rule val compose = createEmptyComposeRule()
    private var scenario: ActivityScenario<MainActivity>? = null

    private fun launch(signedInAs: String? = null, vararg extra: Pair<String, String>) {
        AppEnvironment.resetForTests()
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        val intent = Intent(context, MainActivity::class.java)
            .putExtra("ResetState", true)
            .putExtra("MockLatency", "0.05")
            .putExtra("AppLanguage", "fr")
        signedInAs?.let { intent.putExtra("MockSignIn", it) }
        extra.forEach { (key, value) -> intent.putExtra(key, value) }
        scenario = ActivityScenario.launch(intent)
    }

    @After fun tearDown() {
        scenario?.close()
        AppEnvironment.resetForTests()
    }

    // Acceptance 1 — subscription: create an account → choose a formula → get a quote → pay → receive the contract.
    @Test fun createAccountSubscribeAndPay() {
        launch()
        compose.tag("welcome.register").performClick()
        compose.tag("auth.phone").performTextInput("781112233")
        assertEquals("78 111 22 33", compose.tag("auth.phone").text())
        compose.tag("register.sendCode").performClick()
        compose.tag("auth.otp").performTextInput("123456") // auto-submits at 6 digits

        compose.tag("register.firstName").performTextInput("Ndeye")
        compose.tag("register.lastName").performTextInput("Fall")
        compose.tag("register.usePassword").performScrollTo().performClick() // off: OTP-only login
        compose.tag("register.cgu").performScrollTo().performClick()
        compose.tag("register.submit").performScrollTo().performClick()

        compose.tag("home.subscribe", 10_000).performScrollTo().performClick()
        compose.tag("subscription.next").performClick() // identification
        compose.tag("subscription.next").performClick() // personal information

        for (id in listOf("q_chronic", "q_hospital", "q_incurable")) {
            compose.waitFor(hasTestTag("question.$id"))
            compose.onNode(hasText("Non").and(androidx.compose.ui.test.hasAnyAncestor(hasTestTag("question.$id"))), useUnmergedTree = true)
                .performScrollTo().performClick()
        }
        compose.tag("subscription.next").performClick()

        compose.tag("subscription.product.prd_essentiel").performScrollTo().performClick()
        compose.tag("subscription.total", 10_000)
        Thread.sleep(1500) // let the debounced quote for Essentiel land
        compose.waitForIdle()
        val essentialTotal = compose.tag("subscription.total").text()
        compose.onNode(hasText("70\u00A0%").and(androidx.compose.ui.test.hasAnyAncestor(hasTestTag("subscription.rate"))), useUnmergedTree = true)
            .performScrollTo().performClick()
        compose.waitUntil(5_000) { runCatching { compose.tag("subscription.total").text() != essentialTotal }.getOrDefault(false) }
        compose.tag("subscription.next").performClick()

        compose.waitFor(hasText("Vous êtes éligible à cette formule."))
        compose.tag("subscription.next").performClick()
        compose.tag("subscription.acceptConditions").performScrollTo().performClick()
        compose.tag("subscription.next").performClick()

        compose.tag("payment.method.wave", 10_000).performScrollTo().performClick()
        compose.tag("payment.pay").performScrollTo().performClick()
        try {
            compose.tag("payment.continue", 20_000).performScrollTo().performClick()
        } catch (e: Throwable) {
            val shot = InstrumentationRegistry.getInstrumentation().uiAutomation.takeScreenshot()
            InstrumentationRegistry.getInstrumentation().targetContext.openFileOutput("failure.png", 0).use { shot.compress(android.graphics.Bitmap.CompressFormat.PNG, 100, it) }
            throw e
        }

        val policy = compose.tag("subscription.policyNumber").text()
        assertTrue(policy, policy.contains("POL-2026-"))
        compose.tag("subscription.showCard").performScrollTo().performClick()
        compose.tag("card.qr", 10_000)
    }

    // Acceptance 2 — card: open the card → QR code displayed and refreshed → add to Google Wallet.
    @Test fun cardShowsRefreshingQRAndAddsToWallet() {
        launch("principal", "MockQRLifetime" to "12")
        compose.tag("tab.card", 10_000).performClick()
        compose.tag("card.member")
        val first = compose.tag("card.qr").state()
        assertFalse("QR payload must not contain personal data", first.contains("Awa"))
        // Token is renewed ~10 s before expiry.
        compose.waitUntil(15_000) { runCatching { compose.tag("card.qr").state() != first }.getOrDefault(false) }
        assertNotEquals(first, compose.tag("card.qr").state())

        compose.tag("card.beneficiary.ben_fatou").performClick()
        compose.waitFor(hasText("Fatou Diop"))
        compose.tag("card.addToWallet").performScrollTo().performClick()
        val message = compose.tag("card.walletMessage", 10_000).text()
        assertTrue(message, message.contains("ajoutée"))
    }

    // Acceptance 3 — claim: photograph an invoice → submit → get a claim number → follow its status.
    @Test fun declareClaimAndFollowStatus() {
        launch("principal")
        compose.tag("tab.claims", 10_000).performClick()
        compose.tag("claims.new").performClick()
        compose.tag("claim.beneficiary.ben_fatou").performScrollTo().performClick()
        compose.tag("claim.type.pharmacy").performScrollTo().performClick()
        compose.tag("claim.addReceipt").performScrollTo().performClick()
        // No camera on the emulator: the mock flavor offers a sample invoice (same pipeline: compress → upload → OCR).
        compose.waitFor(hasText("Utiliser une facture d'exemple"))
        compose.onNodeWithText("Utiliser une facture d'exemple").performClick()

        compose.tag("claim.submit", 20_000)
        compose.waitFor(hasText("2 information(s) à vérifier en priorité (surlignées)."))
        assertEquals("20000", compose.tag("claim.field.total").text())
        compose.tag("claim.field.patient").performScrollTo().performTextInput("Fatou Diop")
        compose.tag("claim.submit").performScrollTo().performClick()

        val number = compose.tag("claim.number", 10_000).text()
        assertTrue(number, number.startsWith("SIN-2026-"))
        compose.tag("claim.track").performScrollTo().performClick()
        compose.tag("claimDetail.status", 10_000)
        assertTrue(compose.tag("claimDetail.number").text().contains(number))

        // Pull to refresh: the claim progresses to "En analyse".
        compose.waitUntil(15_000) {
            compose.onAllNodesWithTag("claimDetail.status").fetchSemanticsNodes().isNotEmpty() &&
                compose.tag("claimDetail.status").text().contains("En analyse").also { done ->
                    if (!done) compose.onAllNodesWithTag("claimDetail.number").onFirst().performTouchInput { swipeDown() }
                }
        }
        compose.tag("claimDetail.timeline")
    }

    @Test fun dependentSeesRestrictedFeatures() {
        launch("dependent")
        compose.tag("home.policyCard", 10_000)
        compose.tag("home.action.card")
        compose.tag("home.action.claim")
        assertTrue(compose.onAllNodesWithTag("home.action.family").fetchSemanticsNodes().isEmpty())
        assertTrue(compose.onAllNodesWithTag("home.action.payments").fetchSemanticsNodes().isEmpty())
        compose.tag("tab.card").performClick()
        compose.tag("card.qr", 10_000)
        assertTrue("only their own card", compose.onAllNodesWithTag("card.beneficiary.ben_awa").fetchSemanticsNodes().isEmpty())
    }
}

// MARK: - Helpers (XCUITest-style waits)

private fun ComposeTestRule.waitFor(matcher: androidx.compose.ui.test.SemanticsMatcher, timeout: Long = 5_000) {
    waitUntil(timeout) { onAllNodes(matcher, useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty() }
}

/** Waits for a node with this test tag, then returns it. */
private fun ComposeTestRule.tag(tag: String, timeout: Long = 5_000): SemanticsNodeInteraction {
    waitFor(hasTestTag(tag), timeout)
    return onAllNodesWithTag(tag, useUnmergedTree = true).onFirst()
}

private fun androidx.compose.ui.test.SemanticsNodeInteractionCollection.onFirst() = this[0]

/** Text of a node: editable text for fields, otherwise its (merged) text. */
private fun SemanticsNodeInteraction.text(): String {
    val node = fetchSemanticsNode()
    node.config.getOrNull(SemanticsProperties.EditableText)?.let { return it.text }
    val own = node.config.getOrNull(SemanticsProperties.Text)?.joinToString(" ") { it.text }
    if (!own.isNullOrEmpty()) return own
    fun collect(n: androidx.compose.ui.semantics.SemanticsNode): List<String> =
        (n.config.getOrNull(SemanticsProperties.Text)?.map { it.text } ?: emptyList()) + n.children.flatMap(::collect)
    return collect(node).joinToString(" ")
}

private fun SemanticsNodeInteraction.state(): String =
    fetchSemanticsNode().config.getOrNull(SemanticsProperties.StateDescription) ?: ""

@Suppress("unused")
private fun ComposeTestRule.textExists(text: String) = onAllNodesWithText(text).fetchSemanticsNodes().isNotEmpty()
