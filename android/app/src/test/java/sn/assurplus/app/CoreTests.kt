package sn.assurplus.app

import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.delay
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Assert.fail
import org.junit.Test
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.model.AuthTokens
import sn.assurplus.app.core.model.ClaimStatus
import sn.assurplus.app.core.model.JsonCoding
import sn.assurplus.app.core.model.LocalDay
import sn.assurplus.app.core.model.ProviderType
import sn.assurplus.app.core.network.APIClient
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.network.Endpoint
import sn.assurplus.app.core.network.HttpMethod
import sn.assurplus.app.core.network.HttpRequest
import sn.assurplus.app.core.network.HttpResponse
import sn.assurplus.app.core.network.HttpTransport
import sn.assurplus.app.core.persist.ProtectedStorage
import sn.assurplus.app.core.security.InMemoryTokenStore
import sn.assurplus.app.core.upload.ImageCompressor
import java.util.concurrent.atomic.AtomicInteger

/** Mirrors `ios/AssurPlusTests/Core` (formatting, localisation, API client). */
class FormattingTests {
    @Test fun moneyUsesNonBreakingSpacesAndFcfa() {
        val cases = mapOf(0L to "0 FCFA", 500L to "500 FCFA", 20_000L to "20 000 FCFA", 1_500_000L to "1 500 000 FCFA", -2_500L to "-2 500 FCFA")
        cases.forEach { (amount, expected) -> assertEquals(expected, Money.format(amount).replace(' ', ' ')) }
        assertFalse(Money.format(20_000).contains(' '))
    }

    @Test fun phoneNumbersNormaliseToE164() {
        for (input in listOf("771234567", "77 123 45 67", "+221 77 123 45 67", "00221771234567", "221771234567")) {
            assertEquals("+221771234567", PhoneNumber.e164(input))
            assertEquals("+221 77 123 45 67", PhoneNumber.display(input))
        }
        for (input in listOf("12345", "791234567", "77123456", "")) assertFalse(PhoneNumber.isValid(input))
        assertEquals("77 12", PhoneNumber.formatInput("7712"))
        assertEquals("77 123 45 67", PhoneNumber.formatInput("771234567999"))
    }

    @Test fun imagesAreResizedToSixteenHundredPixels() {
        assertEquals(1600 to 1200, ImageCompressor.targetSize(4032, 3024))
        assertEquals(800 to 600, ImageCompressor.targetSize(800, 600))
    }

    @Test fun fileNamesAreSanitised() {
        assertEquals(".._.._etc_passwd", ProtectedStorage.sanitize("../../etc/passwd"))
        assertEquals("facture_01.pdf", ProtectedStorage.sanitize("facture 01.pdf"))
    }

    @Test fun catalogFormatSpecifiers() {
        assertEquals("Bonjour Awa", L10n.format("Bonjour %@", arrayOf("Awa")))
        assertEquals("Étape 2 sur 8", L10n.format("Étape %lld sur %lld", arrayOf(2, 8)))
        assertEquals("b a", L10n.format("%2\$@ %1\$@", arrayOf("a", "b")))
        assertEquals("80 %", L10n.format("%lld %%", arrayOf(80)))
    }

    @Test fun unknownEnumValuesDecodeToFallbacks() {
        assertEquals(ClaimStatus.unknown, JsonCoding.json.decodeFromString<ClaimStatus>("\"new_status\""))
        assertEquals(ClaimStatus.documentsRequested, JsonCoding.json.decodeFromString<ClaimStatus>("\"documents_requested\""))
        assertEquals(ProviderType.other, JsonCoding.json.decodeFromString<ProviderType>("\"dentist\""))
        assertEquals(LocalDay(1988, 4, 12), JsonCoding.json.decodeFromString<LocalDay>("\"1988-04-12T00:00:00Z\""))
        assertNull(LocalDay.parse("2026-13-01"))
    }
}

class APIClientTests {
    private class Stub(val handler: suspend (HttpRequest) -> HttpResponse) : HttpTransport {
        val requests = mutableListOf<HttpRequest>()
        override suspend fun send(request: HttpRequest): HttpResponse {
            synchronized(requests) { requests += request }
            return handler(request)
        }
    }

    private fun json(body: String, status: Int = 200) = HttpResponse(status, body.toByteArray())

    @Test fun refreshesOnceOn401AndRetriesWithRotatedToken() = runTest {
        val tokens = InMemoryTokenStore(AuthTokens("old", "r1", 900))
        val refreshes = AtomicInteger()
        val stub = Stub { request ->
            when {
                request.url.endsWith("auth/refresh") -> {
                    refreshes.incrementAndGet(); delay(50)
                    json("""{"accessToken":"new","refreshToken":"r2","expiresIn":900}""")
                }
                request.headers["Authorization"] == "Bearer new" -> json("""{}""")
                else -> json("""{"error":{"code":"unauthorized","message":"x"}}""", 401)
            }
        }
        val client = APIClient("https://api.test/v1", stub, tokens)
        // Concurrent 401s share one refresh call.
        (1..5).map { async { client.sendRaw(Endpoint(HttpMethod.GET, "me")) } }.awaitAll()
        assertEquals(1, refreshes.get())
        assertEquals("r2", tokens.load()?.refreshToken)
    }

    @Test fun rejectedRefreshLogsOut() = runTest {
        val tokens = InMemoryTokenStore(AuthTokens("old", "r1", 900))
        var expired = false
        val stub = Stub { request ->
            if (request.url.endsWith("auth/refresh")) json("""{"error":{"code":"invalid_refresh","message":"Session expirée."}}""", 401)
            else json("""{"error":{"code":"unauthorized","message":"x"}}""", 401)
        }
        val client = APIClient("https://api.test/v1", stub, tokens).apply { onSessionExpired = { expired = true } }
        try {
            client.sendRaw(Endpoint(HttpMethod.GET, "me"))
            fail("expected Unauthorized")
        } catch (e: APIError) {
            assertEquals(APIError.Unauthorized, e)
        }
        assertTrue(expired)
        assertNull(tokens.load())
    }

    @Test fun serverErrorsKeepTheFrenchMessageAndFields() = runTest {
        val stub = Stub { json("""{"error":{"code":"invalid_phone","message":"Numéro invalide.","fields":{"phone":"9 chiffres"}}}""", 422) }
        val client = APIClient("https://api.test/v1", stub, InMemoryTokenStore())
        try {
            client.sendRaw(Endpoint(HttpMethod.POST, "auth/otp/send", requiresAuth = false))
            fail("expected error")
        } catch (e: APIError) {
            assertEquals("Numéro invalide.", e.userMessage)
            assertEquals("9 chiffres", e.fieldErrors["phone"])
            assertFalse(e.isRetryable)
        }
    }

    @Test fun requestsCarryLanguageAndBearer() = runTest {
        val stub = Stub { json("{}") }
        val client = APIClient("https://api.test/v1", stub, InMemoryTokenStore(AuthTokens("acc", "ref", 900)))
        client.sendRaw(Endpoint(HttpMethod.GET, "providers", query = Endpoint.query("type" to "pharmacy", "q" to null)))
        val request = stub.requests.single()
        assertEquals("https://api.test/v1/providers?type=pharmacy", request.url)
        assertEquals("Bearer acc", request.headers["Authorization"])
        assertEquals(L10n.code, request.headers["Accept-Language"])
    }
}
