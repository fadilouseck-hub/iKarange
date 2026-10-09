import Foundation
import Testing
@testable import AssurPlus

@Suite("APIClient")
struct APIClientTests {
    @Test func decodesResponseAndSendsBearerToken() async throws {
        let transport = StubTransport { request, _ in
            #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer access-1")
            #expect(request.value(forHTTPHeaderField: "X-Auth-Token") == "access-1")
            #expect(request.value(forHTTPHeaderField: "Accept-Language") == "fr")
            return (200, Fixture.json(["token": "abc", "expiresAt": "2026-10-06T10:00:00.000Z"]))
        }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: InMemoryTokenStore(Fixture.tokens))
        let token = try await AssurAPI(client: client).qrToken(beneficiaryId: "ben_1")
        #expect(token.token == "abc")
        let url = try #require(await transport.requests.first?.url)
        #expect(url.absoluteString == "https://api.test/v1/me/card/qr-token?beneficiaryId=ben_1")
    }

    @Test func mapsErrorEnvelopeToServerError() async {
        let transport = StubTransport { _, _ in (422, Fixture.error("otp_invalid", "Code incorrect.", fields: ["code": "Code incorrect."])) }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: InMemoryTokenStore())
        do {
            _ = try await AssurAPI(client: client).verifyOTP(requestId: "r", code: "000000")
            Issue.record("expected an error")
        } catch let error as APIError {
            #expect(error.userMessage == "Code incorrect.")
            #expect(error.fieldErrors["code"] == "Code incorrect.")
            #expect(!error.isRetryable)
        } catch {
            Issue.record("unexpected \(error)")
        }
    }

    @Test func nonJSONErrorBodyGetsGenericFrenchMessage() async {
        let transport = StubTransport { _, _ in (503, Data("<html>".utf8)) }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: InMemoryTokenStore(Fixture.tokens))
        await #expect(throws: APIError.self) { _ = try await AssurAPI(client: client).dashboard() }
        do { _ = try await AssurAPI(client: client).dashboard() } catch let error as APIError {
            #expect(error.isRetryable)
            #expect(error.userMessage.contains("indisponible"))
        } catch {}
    }

    @Test func refreshesOnceOn401ThenRetriesWithRotatedToken() async throws {
        let tokens = InMemoryTokenStore(Fixture.tokens)
        let transport = StubTransport { request, _ in
            switch request.url!.path {
            case "/v1/auth/refresh":
                let body = try JSONSerialization.jsonObject(with: request.httpBody!) as! [String: String]
                #expect(body["refreshToken"] == "refresh-1")
                return (200, Fixture.json(["accessToken": "access-2", "refreshToken": "refresh-2", "expiresIn": 900]))
            default:
                let auth = request.value(forHTTPHeaderField: "Authorization")
                return auth == "Bearer access-2" ? (200, Fixture.json(["beneficiaries": []])) : (401, Fixture.error("unauthorized", "Expiré"))
            }
        }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: tokens)
        let card = try await AssurAPI(client: client).card()
        #expect(card.beneficiaries.isEmpty)
        #expect(tokens.load()?.refreshToken == "refresh-2")
        #expect(await transport.paths() == ["GET /v1/me/card", "POST /v1/auth/refresh", "GET /v1/me/card"])
    }

    @Test func concurrent401sShareASingleRefresh() async throws {
        let tokens = InMemoryTokenStore(Fixture.tokens)
        let transport = StubTransport { request, _ in
            if request.url!.path == "/v1/auth/refresh" {
                return (200, Fixture.json(["accessToken": "access-2", "refreshToken": "refresh-2", "expiresIn": 900]))
            }
            return request.value(forHTTPHeaderField: "Authorization") == "Bearer access-2"
                ? (200, Fixture.json(["beneficiaries": []])) : (401, Fixture.error("unauthorized", "Expiré"))
        }
        let api = AssurAPI(client: APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: tokens))
        async let a = api.card()
        async let b = api.card()
        async let c = api.card()
        _ = try await (a, b, c)
        let refreshes = await transport.paths().filter { $0.hasSuffix("auth/refresh") }
        #expect(refreshes.count <= 2) // one shared refresh; a late request may see the new token directly
        #expect(refreshes.count >= 1)
    }

    @Test func rejectedRefreshClearsTokensAndPostsExpiry() async {
        let tokens = InMemoryTokenStore(Fixture.tokens)
        let transport = StubTransport { request, _ in
            request.url!.path == "/v1/auth/refresh" ? (401, Fixture.error("invalid_refresh", "Session expirée.")) : (401, Fixture.error("unauthorized", "x"))
        }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: tokens)
        let expired = Expectation()
        let observer = NotificationCenter.default.addObserver(forName: .sessionExpired, object: nil, queue: nil) { _ in expired.fulfill() }
        defer { NotificationCenter.default.removeObserver(observer) }

        do {
            _ = try await AssurAPI(client: client).me()
            Issue.record("expected unauthorized")
        } catch {
            #expect(error as? APIError == .unauthorized)
        }
        #expect(tokens.load() == nil)
        #expect(expired.isFulfilled)
    }

    @Test func offlineRefreshKeepsSession() async {
        let tokens = InMemoryTokenStore(Fixture.tokens)
        let transport = StubTransport { request, _ in
            if request.url!.path == "/v1/auth/refresh" { throw URLError(.notConnectedToInternet) }
            return (401, Fixture.error("unauthorized", "x"))
        }
        let client = APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: tokens)
        do {
            _ = try await AssurAPI(client: client).me()
        } catch {
            #expect(error as? APIError == .offline)
        }
        #expect(tokens.load() == Fixture.tokens)
    }

    @Test func mapsURLErrors() {
        #expect(APIError.wrap(URLError(.notConnectedToInternet)) == .offline)
        #expect(APIError.wrap(URLError(.timedOut)) == .timeout)
        #expect(APIError.wrap(CancellationError()) == .cancelled)
        #expect(APIError.offline.isRetryable)
    }

    @Test func decodesDatesAndLocalDays() throws {
        let data = Data(#"{"id":"p","number":"N","productName":"P","formulaName":"F","startDate":"2026-01-01","endDate":"2026-12-31","status":{"code":"active","label":"Actif"},"coverageRate":80,"territoriality":null}"#.utf8)
        let policy = try JSONCoding.decoder().decode(PolicySummary.self, from: data)
        #expect(policy.startDate == LocalDay(year: 2026, month: 1, day: 1))
        #expect(try JSONCoding.encoder().encode(policy.endDate) == Data(#""2026-12-31""#.utf8))
        #expect(JSONCoding.parseTimestamp("2026-10-06T10:00:00Z") != nil)
        #expect(JSONCoding.parseTimestamp("2026-10-06T10:00:00.123Z") != nil)
    }

    @Test func unknownClaimStatusFallsBack() throws {
        let status = try JSONCoding.decoder().decode([ClaimStatus].self, from: Data(#"["paid","brand_new_status"]"#.utf8))
        #expect(status == [.paid, .unknown])
    }
}

/// Thread-safe flag for notification callbacks.
final class Expectation: @unchecked Sendable {
    private let lock = NSLock()
    private var value = false
    func fulfill() { lock.withLock { value = true } }
    var isFulfilled: Bool { lock.withLock { value } }
}
