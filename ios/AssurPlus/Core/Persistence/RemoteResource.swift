import Foundation
import Observation

/// Stale-while-revalidate state for one API resource: shows the cached copy immediately (offline use),
/// refreshes from the network, keeps the cached value when the refresh fails.
@MainActor
@Observable
final class RemoteResource<Value: Codable & Sendable> {
    private(set) var value: Value?
    private(set) var isLoading = false
    private(set) var error: APIError?
    private(set) var lastUpdated: Date?

    private let cache: ResponseCache?
    private let cacheKey: CacheKey?
    private let fetch: @MainActor () async throws -> Value

    init(cache: ResponseCache? = nil, key: CacheKey? = nil, fetch: @escaping @MainActor () async throws -> Value) {
        self.cache = cache
        cacheKey = key
        self.fetch = fetch
        if let cache, let key { value = cache.load(Value.self, key: key) }
    }

    func load() async {
        guard !isLoading else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let fresh = try await fetch()
            update(fresh)
            error = nil
        } catch {
            let apiError = APIError.wrap(error)
            if apiError != .cancelled { self.error = apiError }
        }
    }

    /// Replaces the value locally (after a mutation) and persists it.
    func update(_ newValue: Value) {
        value = newValue
        lastUpdated = .now
        if let cache, let cacheKey { cache.store(newValue, key: cacheKey) }
    }
}
