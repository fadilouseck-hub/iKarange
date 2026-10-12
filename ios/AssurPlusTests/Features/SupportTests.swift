import Foundation
import Testing
@testable import AssurPlus

@MainActor
@Suite("Feedback and assistant")
struct SupportTests {
    /// A mock environment whose API calls go to `transport` instead of the MockServer.
    private func environment(on transport: StubTransport) -> AppEnvironment {
        let tokens = InMemoryTokenStore(Fixture.tokens)
        let api = AssurAPI(client: APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: tokens))
        return AppEnvironment(
            api: api, uploader: ResumableUploader(api: api, baseDelay: .milliseconds(1)), cache: SwiftDataCache(inMemory: true),
            settings: AppSettings(defaults: UserDefaults(suiteName: "tests-\(UUID().uuidString)")!), tokens: tokens,
            biometrics: AlwaysBiometrics(), paymentLauncher: MockPaymentLauncher(), wallet: MockWallet(), isMock: true,
            language: LanguageSettings(defaults: UserDefaults(suiteName: "lang-\(UUID().uuidString)")!, preferred: ["fr"]))
    }

    @Test func feedbackIsSentWithDiagnostics() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = FeedbackViewModel(env: env)
        #expect(!model.canSend)

        model.message = "abc "
        #expect(!model.canSend) // too short once trimmed
        model.category = .bug
        model.message = "  L'écran Carte reste vide.  "
        #expect(model.canSend)
        await model.send()
        #expect(model.receipt?.status.code == "received")
        #expect(model.errorText == nil)
    }

    @Test func feedbackRequestMatchesTheContract() async throws {
        let transport = StubTransport { _, _ in
            (201, Fixture.json(["id": "fbk_1", "status": ["code": "received", "label": "Reçu"], "createdAt": "2026-10-12T10:00:00Z"]))
        }
        let api = AssurAPI(client: APIClient(baseURL: Fixture.baseURL, transport: transport, tokens: InMemoryTokenStore(Fixture.tokens)))
        _ = try await api.sendFeedback(FeedbackRequest(
            category: .suggestion, message: "Ajouter le wolof", platform: "ios", appVersion: "1.0 (2)", osVersion: "18.4",
            deviceModel: "iPhone16,2", locale: "fr"))
        let request = try #require(await transport.requests.first)
        #expect(request.httpMethod == "POST")
        #expect(request.url?.path == "/v1/feedback")
        let body = try #require(JSONSerialization.jsonObject(with: request.httpBody ?? Data()) as? [String: String])
        #expect(body == ["category": "suggestion", "message": "Ajouter le wolof", "platform": "ios", "appVersion": "1.0 (2)",
                         "osVersion": "18.4", "deviceModel": "iPhone16,2", "locale": "fr"])
    }

    @Test func feedbackShowsServerValidation() async throws {
        let transport = StubTransport { _, _ in
            (422, Fixture.error("invalid_feedback", "Message trop long.", fields: ["message": "Message trop long."]))
        }
        let failing = environment(on: transport)
        let model = FeedbackViewModel(env: failing)
        model.message = "Un message valable"
        await model.send()
        #expect(model.receipt == nil)
        #expect(model.errorText == "Message trop long.")
    }

    @Test func assistantAnswersAndKeepsTheConversation() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = AssistantViewModel(env: env)
        await model.load()
        #expect(model.info?.available == true)
        #expect(model.info?.suggestions.isEmpty == false)
        #expect(!model.canSend)

        model.draft = "  Comment déclarer un sinistre ?  "
        #expect(model.canSend)
        await model.send()
        #expect(model.draft.isEmpty)
        #expect(model.entries.map(\.kind) == [.user, .assistant])
        #expect(model.entries.first?.text == "Comment déclarer un sinistre ?")
        #expect(model.entries.last?.text.contains("Nouveau sinistre") == true)

        await model.ask("Et ma carte ?")
        #expect(model.entries.map(\.kind) == [.user, .assistant, .user, .assistant])
        #expect(model.entries.last?.text.contains("QR") == true)
    }

    @Test func assistantSendsOnlyAnsweredHistory() async throws {
        // First question fails (503), second succeeds: the failed exchange is not replayed to the server.
        let transport = StubTransport { request, count in
            if request.httpMethod == "GET" {
                return (200, Fixture.json(["available": true, "suggestions": ["Q1"], "disclaimer": "IA"]))
            }
            if count == 2 { return (503, Fixture.error("assistant_unavailable", "Assistant indisponible.")) }
            return (200, Fixture.json(["message": ["role": "assistant", "content": "Réponse"]]))
        }
        let stubbed = environment(on: transport)
        let model = AssistantViewModel(env: stubbed)
        await model.load()
        await model.ask("Première question")
        #expect(model.entries.map(\.kind) == [.user, .failure])
        #expect(model.entries.last?.text == "Assistant indisponible.")

        await model.ask("Deuxième question")
        #expect(model.entries.map(\.kind) == [.user, .failure, .user, .assistant])
        let last = try #require(await transport.requests.last)
        let sent = try JSONCoding.decoder().decode(AssistantRequest.self, from: last.httpBody ?? Data())
        #expect(sent.messages == [AssistantMessage(role: .user, content: "Deuxième question")])
    }

    @Test func mockAnswersByTopic() {
        #expect(MockServer.cannedAnswer(to: "How do I submit a CLAIM?").contains("Sinistres"))
        #expect(MockServer.cannedAnswer(to: "Quel est mon taux ?").contains("Mon contrat"))
        #expect(MockServer.cannedAnswer(to: "Bonjour").contains("gestionnaire"))
    }
}
