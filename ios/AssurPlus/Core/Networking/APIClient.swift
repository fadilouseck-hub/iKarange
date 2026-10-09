import Foundation

extension Notification.Name {
    /// Posted when the refresh token is rejected; `AuthSession` logs the user out.
    static let sessionExpired = Notification.Name("sn.assurplus.sessionExpired")
}

/// Typed HTTP client: builds requests, attaches the bearer token, refreshes it once on 401 (rotation),
/// maps errors to `APIError`. Contains no business logic.
final class APIClient: Sendable {
    let baseURL: URL
    let transport: HTTPTransport
    let tokens: TokenStore
    private let refresher = RefreshCoordinator()

    init(baseURL: URL, transport: HTTPTransport, tokens: TokenStore) {
        self.baseURL = baseURL
        self.transport = transport
        self.tokens = tokens
    }

    func send<R>(_ endpoint: Endpoint<R>) async throws -> R {
        let data = try await sendRaw(endpoint)
        if R.self == EmptyResponse.self, let empty = EmptyResponse() as? R { return empty }
        do {
            return try JSONCoding.decoder().decode(R.self, from: data)
        } catch {
            throw APIError.decoding(String(describing: error))
        }
    }

    /// Returns the raw body (PDF, `.pkpass`, files).
    func sendRaw<R>(_ endpoint: Endpoint<R>, allowRefresh: Bool = true) async throws -> Data {
        let request = try makeRequest(endpoint)
        let (data, response) = try await perform(request)

        switch response.statusCode {
        case 200..<300:
            return data
        case 401 where endpoint.requiresAuth && allowRefresh:
            try await refreshTokens()
            return try await sendRaw(endpoint, allowRefresh: false)
        case 401 where endpoint.requiresAuth:
            expireSession()
            throw APIError.unauthorized
        default:
            throw Self.error(from: data, status: response.statusCode)
        }
    }

    func makeRequest<R>(_ endpoint: Endpoint<R>) throws -> URLRequest {
        var components = URLComponents(
            url: baseURL.appendingPathComponent(endpoint.path), resolvingAgainstBaseURL: false)
        if !endpoint.query.isEmpty { components?.queryItems = endpoint.query }
        guard let url = components?.url else { throw APIError.invalidResponse }

        var request = URLRequest(url: url)
        request.httpMethod = endpoint.method.rawValue
        request.httpBody = endpoint.body
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue(AppLanguage.code, forHTTPHeaderField: "Accept-Language")
        for (name, value) in endpoint.headers { request.setValue(value, forHTTPHeaderField: name) }
        if endpoint.requiresAuth, let access = tokens.load()?.accessToken {
            request.setValue("Bearer \(access)", forHTTPHeaderField: "Authorization")
            // Shared hosting (Apache + CGI/FPM) can strip Authorization before PHP sees it.
            request.setValue(access, forHTTPHeaderField: "X-Auth-Token")
        }
        return request
    }

    private func perform(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        do {
            return try await transport.send(request)
        } catch {
            throw APIError.wrap(error)
        }
    }

    /// Concurrent 401s share one refresh call.
    func refreshTokens() async throws {
        try await refresher.run { [self] in
            guard let current = tokens.load() else {
                expireSession()
                throw APIError.unauthorized
            }
            let endpoint = Endpoint<AuthTokens>(
                .post, "auth/refresh", body: ["refreshToken": current.refreshToken], requiresAuth: false)
            do {
                let fresh = try await send(endpoint)
                tokens.save(fresh)
            } catch let error as APIError {
                // Offline or 5xx: keep the session so the user can retry. Rejected token: log out.
                if case .server(let status, _, _, _) = error, (400..<500).contains(status) {
                    expireSession()
                    throw APIError.unauthorized
                }
                throw error
            }
        }
    }

    private func expireSession() {
        tokens.clear()
        NotificationCenter.default.post(name: .sessionExpired, object: nil)
    }

    static func error(from data: Data, status: Int) -> APIError {
        if let envelope = try? JSONCoding.decoder().decode(APIErrorEnvelope.self, from: data) {
            return .server(
                status: status, code: envelope.error.code, message: envelope.error.message,
                fields: envelope.error.fields ?? [:])
        }
        return .server(
            status: status, code: "http_\(status)",
            message: String(localized: "Le service est momentanément indisponible. Veuillez réessayer.", bundle: .appLanguage),
            fields: [:])
    }
}

private actor RefreshCoordinator {
    private var inFlight: Task<Void, Error>?

    func run(_ operation: @escaping @Sendable () async throws -> Void) async throws {
        if let inFlight { return try await inFlight.value }
        let task = Task { try await operation() }
        inFlight = task
        defer { inFlight = nil }
        try await task.value
    }
}
