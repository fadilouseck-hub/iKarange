import Foundation

/// The single seam between the app and the network. `URLSession` in production, `MockServer` in the
/// MockAPI configuration and UI tests, `StubTransport` in unit tests.
protocol HTTPTransport: Sendable {
    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse)
}

struct URLSessionTransport: HTTPTransport {
    let session: URLSession

    static func makeDefault() -> URLSessionTransport {
        let configuration = URLSessionConfiguration.default
        configuration.timeoutIntervalForRequest = 30
        configuration.timeoutIntervalForResource = 120
        configuration.waitsForConnectivity = false
        configuration.requestCachePolicy = .reloadIgnoringLocalCacheData
        configuration.urlCache = nil // health data must not land in the shared HTTP cache
        configuration.httpAdditionalHeaders = ["Accept-Language": "fr"]
        // Certificate pinning is opt-in (Info.plist PINNED_PUBLIC_KEY_HASHES).
        return URLSessionTransport(session: URLSession(configuration: configuration, delegate: PinningDelegate.fromInfoPlist(), delegateQueue: nil))
    }

    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse else { throw APIError.invalidResponse }
        return (data, http)
    }
}
