package sn.assurplus.app

import android.content.Intent
import android.graphics.Bitmap
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.test.captureToImage
import androidx.compose.ui.test.junit4.createEmptyComposeRule
import androidx.compose.ui.test.onAllNodesWithTag
import androidx.compose.ui.test.onRoot
import androidx.compose.ui.test.performClick
import androidx.test.core.app.ActivityScenario
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Assume.assumeTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.MainActivity
import sn.assurplus.app.core.model.LoginRequest
import java.io.File

/** Opt-in live-backend checks using a synthetic member. Credentials are supplied at runtime,
 * never in source or Gradle properties. No claims, payments or account changes are submitted.
 * Screenshots omit the member-card QR token and account credentials.
 */
@RunWith(AndroidJUnit4::class)
class LiveReadOnlySmokeTests {
    @get:Rule val compose = createEmptyComposeRule()

    @Test fun frenchLiveScreens() = checkLive("fr")
    @Test fun englishLiveScreens() = checkLive("en")

    private fun checkLive(language: String) {
        val args = InstrumentationRegistry.getArguments()
        assumeTrue(!BuildConfig.MOCK_API && args.getString("liveSmoke") == "true")
        val username = args.getString("liveUsername") ?: error("Synthetic username required")
        require(username in setOf("review.assurplus", "Demo")) { "Only approved synthetic members may be captured" }
        val password = args.getString("livePassword") ?: error("Synthetic password required")
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        AppEnvironment.resetForTests()
        val intent = Intent(context, MainActivity::class.java)
            .putExtra("ResetState", true).putExtra("AppLanguage", language)
        val scenario = ActivityScenario.launch<MainActivity>(intent)
        try {
            val env = AppEnvironment.current
            assertFalse(env.isMock)
            val response = runBlocking { env.api.login(LoginRequest(identifier = username, password = password)) }
            assertTrue("Access token returned", response.tokens.accessToken.isNotEmpty())
            assertTrue("Refresh token returned", response.tokens.refreshToken.isNotEmpty())
            env.api.client.tokens.save(response.tokens)
            val (claimId, providerId) = runBlocking {
                env.api.me()
                env.api.dashboard()
                assertTrue("Synthetic member has a card", env.api.card().beneficiaries.isNotEmpty())
                val claims = env.api.claims()
                val providers = env.api.providers(null, null, null, null, null).providers
                assertTrue("Synthetic member has claims", claims.isNotEmpty())
                assertTrue("Care network has providers", providers.isNotEmpty())
                claims.first().id to providers.first().id
            }
            compose.runOnIdle { env.session.didAuthenticate(response) }
            waitFor("home.policyCard")
            save(language, "home")
            waitFor("tab.claims").performClick()
            waitFor("claims.row.$claimId")
            save(language, "claims")
            waitFor("tab.network").performClick()
            waitFor("network.provider.$providerId")
            save(language, "network")
        } finally {
            scenario.close()
            AppEnvironment.current.api.client.tokens.clear()
            AppEnvironment.resetForTests()
        }
    }

    private fun waitFor(tag: String): androidx.compose.ui.test.SemanticsNodeInteraction {
        compose.waitUntil(30_000) { compose.onAllNodesWithTag(tag, useUnmergedTree = true).fetchSemanticsNodes().isNotEmpty() }
        return compose.onAllNodesWithTag(tag, useUnmergedTree = true)[0]
    }

    private fun save(language: String, screen: String) {
        compose.waitForIdle()
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        val directory = File(context.getExternalFilesDir(null), "play-smoke-screenshots").apply { mkdirs() }
        File(directory, "$language-$screen.png").outputStream().use {
            compose.onRoot().captureToImage().asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it)
        }
    }
}
