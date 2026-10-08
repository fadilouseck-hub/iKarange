import Foundation
@testable import AssurPlus

/// Scriptable transport: each request is answered by `handler`; all requests are recorded.
actor StubTransport: HTTPTransport {
    typealias Handler = @Sendable (URLRequest, Int) throws -> (Int, Data)
    private let handler: Handler
    private(set) var requests: [URLRequest] = []

    init(_ handler: @escaping Handler) { self.handler = handler }

    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        requests.append(request)
        let (status, data) = try handler(request, requests.count)
        return (data, HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
    }

    func paths() -> [String] { requests.map { "\($0.httpMethod ?? "") \($0.url!.path)" } }
}

enum Fixture {
    static func json(_ object: Any) -> Data { try! JSONSerialization.data(withJSONObject: object) }
    static func error(_ code: String, _ message: String, fields: [String: String] = [:]) -> Data {
        json(["error": ["code": code, "message": message, "fields": fields]])
    }
    static let baseURL = URL(string: "https://api.test/v1")!
    static let tokens = AuthTokens(accessToken: "access-1", refreshToken: "refresh-1", expiresIn: 900)
}

/// A full app environment running on the in-process MockServer (no network, no Keychain).
@MainActor
func makeMockEnvironment() -> AppEnvironment {
    let tokens = InMemoryTokenStore()
    let server = MockServer(latency: .zero)
    let api = AssurAPI(client: APIClient(baseURL: MockServer.baseURL, transport: server, tokens: tokens))
    return AppEnvironment(
        api: api, uploader: ResumableUploader(api: api, baseDelay: .milliseconds(1)), cache: SwiftDataCache(inMemory: true),
        settings: AppSettings(defaults: UserDefaults(suiteName: "tests-\(UUID().uuidString)")!), tokens: tokens,
        biometrics: AlwaysBiometrics(), paymentLauncher: MockPaymentLauncher(), wallet: MockWallet(), isMock: true,
        language: LanguageSettings(defaults: UserDefaults(suiteName: "lang-\(UUID().uuidString)")!, preferred: ["fr"]))
}

/// Logs the demo principal (or dependent) in on a mock environment.
@MainActor
func signIn(_ env: AppEnvironment, phone: String = "770000001") async throws {
    let response = try await env.api.login(LoginRequest(phone: "+221" + phone, password: "assur1234"))
    env.session.didAuthenticate(response)
}
