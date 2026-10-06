import Foundation
import SwiftData

/// Read-only offline cache of API responses (dashboard, card, contract, dependants, claims, providers…).
@MainActor
protocol ResponseCache: AnyObject {
    func load<T: Decodable>(_ type: T.Type, key: CacheKey) -> T?
    func store<T: Encodable>(_ value: T, key: CacheKey)
    func remove(_ key: CacheKey)
    func clear()
}

enum CacheKey: String, CaseIterable {
    case me, dashboard, card, policy, dependents, claims, claimTypes, providers, payments, notifications
    case vault, products, qrTokens, claimDraft
}

@Model
final class CachedResponse {
    @Attribute(.unique) var key: String
    var payload: Data
    var updatedAt: Date

    init(key: String, payload: Data, updatedAt: Date = .now) {
        self.key = key
        self.payload = payload
        self.updatedAt = updatedAt
    }
}

@MainActor
final class SwiftDataCache: ResponseCache {
    private let container: ModelContainer
    private var context: ModelContext { container.mainContext }

    init(inMemory: Bool = false) {
        let configuration: ModelConfiguration
        if inMemory {
            configuration = ModelConfiguration(isStoredInMemoryOnly: true)
        } else {
            let url = ProtectedStorage.directory("Cache").appendingPathComponent("responses.store")
            configuration = ModelConfiguration(url: url)
        }
        do {
            container = try ModelContainer(for: CachedResponse.self, configurations: configuration)
        } catch {
            // A corrupt cache must never block the app: fall back to memory.
            container = try! ModelContainer(for: CachedResponse.self, configurations: ModelConfiguration(isStoredInMemoryOnly: true))
        }
    }

    func load<T: Decodable>(_ type: T.Type, key: CacheKey) -> T? {
        guard let entry = fetch(key) else { return nil }
        return try? JSONCoding.decoder().decode(T.self, from: entry.payload)
    }

    func store<T: Encodable>(_ value: T, key: CacheKey) {
        guard let data = try? JSONCoding.encoder().encode(value) else { return }
        if let entry = fetch(key) {
            entry.payload = data
            entry.updatedAt = .now
        } else {
            context.insert(CachedResponse(key: key.rawValue, payload: data))
        }
        try? context.save()
    }

    func remove(_ key: CacheKey) {
        guard let entry = fetch(key) else { return }
        context.delete(entry)
        try? context.save()
    }

    func clear() {
        try? context.delete(model: CachedResponse.self)
        try? context.save()
    }

    private func fetch(_ key: CacheKey) -> CachedResponse? {
        let raw = key.rawValue
        var descriptor = FetchDescriptor<CachedResponse>(predicate: #Predicate { $0.key == raw })
        descriptor.fetchLimit = 1
        return try? context.fetch(descriptor).first
    }
}
