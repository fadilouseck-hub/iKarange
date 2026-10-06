import Foundation

enum HTTPMethod: String, Sendable {
    case get = "GET", post = "POST", put = "PUT", patch = "PATCH", delete = "DELETE", head = "HEAD"
}

/// A typed request against the ASSUR+ API (`docs/api/openapi.yaml`).
struct Endpoint<Response: Decodable & Sendable>: Sendable {
    var method: HTTPMethod
    var path: String
    var query: [URLQueryItem] = []
    var body: Data?
    var headers: [String: String] = [:]
    var requiresAuth = true

    init(_ method: HTTPMethod, _ path: String, query: [URLQueryItem] = [], requiresAuth: Bool = true) {
        self.method = method
        self.path = path
        self.query = query
        self.requiresAuth = requiresAuth
    }

    init(_ method: HTTPMethod, _ path: String, body: some Encodable, requiresAuth: Bool = true) {
        self.init(method, path, requiresAuth: requiresAuth)
        self.body = try? JSONCoding.encoder().encode(body)
        headers["Content-Type"] = "application/json"
    }
}

extension URLQueryItem {
    /// Builds query items, dropping nil values.
    static func items(_ pairs: [(String, String?)]) -> [URLQueryItem] {
        pairs.compactMap { name, value in value.map { URLQueryItem(name: name, value: $0) } }
    }
}
