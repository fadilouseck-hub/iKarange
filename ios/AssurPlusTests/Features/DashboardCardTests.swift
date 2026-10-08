import CoreImage
import Foundation
import Testing
@testable import AssurPlus

@MainActor
@Suite("Dashboard & card")
struct DashboardCardTests {
    @Test func dashboardShowsServerValues() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let dashboard = RemoteResource(cache: env.cache, key: .dashboard) { try await env.api.dashboard() }
        await dashboard.load()
        let value = try #require(dashboard.value)
        #expect(value.policy?.number == "POL-2026-004521")
        #expect(value.limits?.remaining == 2_587_500)
        #expect(value.dependents.count == 3)
        // Cached for offline use
        #expect(env.cache.load(Dashboard.self, key: .dashboard) == value)
    }

    @Test func cachedValueSurvivesOfflineRefresh() async {
        let cache = SwiftDataCache(inMemory: true)
        var calls = 0
        let resource = RemoteResource<[String]>(cache: cache, key: .claims) {
            calls += 1
            if calls > 1 { throw URLError(.notConnectedToInternet) }
            return ["a"]
        }
        await resource.load()
        await resource.load()
        #expect(resource.value == ["a"])
        #expect(resource.error == .offline)

        // A new screen instance starts from the cache, before any network call.
        let reopened = RemoteResource<[String]>(cache: cache, key: .claims) { throw URLError(.timedOut) }
        #expect(reopened.value == ["a"])
    }

    @Test func dependentOnlySeesOwnCardAndNoFamily() async throws {
        let env = makeMockEnvironment()
        try await signIn(env, phone: "770000002")
        let card = try await env.api.card()
        #expect(card.beneficiaries.map(\.fullName) == ["Moussa Diop"])
        #expect(try await env.api.dashboard().dependents.isEmpty)
    }

    @Test func qrTokenIsFetchedCachedAndRenewedNearExpiry() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = CardViewModel(env: env)
        await model.load()
        #expect(model.selected?.id == "ben_awa")

        await model.refreshTokenIfNeeded()
        let first = try #require(model.currentToken())
        #expect(!first.token.contains("Awa")) // no personal data in the QR payload

        await model.refreshTokenIfNeeded()
        #expect(model.currentToken() == first) // still valid → not refetched

        await model.refreshTokenIfNeeded(now: first.expiresAt.addingTimeInterval(-5))
        let second = try #require(model.currentToken())
        #expect(second.token != first.token)
        // Persisted for offline display until expiry
        #expect(env.cache.load([String: QRToken].self, key: .qrTokens)?["ben_awa"] == second)
    }

    @Test func switchingBeneficiaryUsesItsOwnToken() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = CardViewModel(env: env)
        await model.load()
        await model.refreshTokenIfNeeded()
        let awa = model.currentToken()
        model.selectedId = "ben_fatou"
        #expect(model.currentToken() == nil)
        await model.refreshTokenIfNeeded()
        #expect(model.currentToken() != nil)
        #expect(model.currentToken() != awa)
    }

    @Test func qrImageIsGenerated() {
        #expect(QRCode.image(for: "AP1.token.sig") != nil)
    }

    @Test func addToWalletReportsResult() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = CardViewModel(env: env)
        await model.load()
        await model.addToWallet()
        #expect(model.walletMessage?.level == .success)
    }

    @Test func policyDetailAndConditionsPDF() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = PolicyViewModel(env: env)
        await model.resource.load()
        #expect(model.resource.value?.members.count == 4)
        #expect(model.resource.value?.renewal?.tacitRenewal == true)
        await model.openConditions()
        let url = try #require(model.previewURL)
        let data = try Data(contentsOf: url)
        #expect(data.starts(with: Data("%PDF".utf8)))
        let values = try url.resourceValues(forKeys: [.isExcludedFromBackupKey])
        #expect(values.isExcludedFromBackup == true)
    }
}

@MainActor
@Suite("Styled QR code")
struct StyledQRCodeTests {
    /// Dots, rounded eyes and the centre logo must not break decoding.
    @Test(arguments: ["AP1.0f3c9a7d5b2e4c1a8d6f0b9e7c3a5d2f.sig", "AP1." + String(repeating: "a1b2c3d4", count: 6) + ".sig"])
    func styledQRStillDecodes(payload: String) throws {
        let image = try #require(QRCode.styledImage(for: payload))
        let detector = try #require(CIDetector(ofType: CIDetectorTypeQRCode, context: nil, options: [CIDetectorAccuracy: CIDetectorAccuracyHigh]))
        let features = detector.features(in: try #require(CIImage(image: image))).compactMap { $0 as? CIQRCodeFeature }
        #expect(features.first?.messageString == payload)
    }

    @Test func matrixHasFinderPatterns() throws {
        let matrix = try #require(QRMatrix.make("AP1.token.sig"))
        #expect(matrix.size >= 21 && (matrix.size - 17) % 4 == 0) // valid QR versions
        for (x, y) in matrix.finderOrigins {
            #expect(matrix[x, y] && matrix[x + 6, y + 6] && !matrix[x + 1, y + 1] && matrix[x + 3, y + 3])
        }
    }
}
