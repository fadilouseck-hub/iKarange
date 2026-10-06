import Foundation
import SwiftUI
import Testing
@testable import AssurPlus

@MainActor
@Suite("Family, vault, network, notifications, routing")
struct OtherFeaturesTests {
    private var sample: PickedDocument { DocumentSource.fromImages([MockDocuments.sampleInvoice()], baseName: "doc")! }

    @Test func familyAddAndRemoveCreateRequests() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = FamilyViewModel(env: env)
        await model.resource.load()
        #expect(model.resource.value?.dependents.count == 3)

        let added = await model.requestAddition(
            QuoteMember(firstName: "Aminata", lastName: "Diop", relation: .child, birthDate: LocalDay(year: 2026, month: 3, day: 1), gender: .female),
            documents: [sample])
        #expect(added)
        let fatou = try #require(model.resource.value?.dependents.first { $0.firstName == "Fatou" })
        await model.requestRemoval(of: fatou, reason: "Majorité")
        let requests = try #require(model.resource.value?.requests)
        #expect(requests.map(\.kind) == [.remove, .add])
        #expect(requests.allSatisfy { $0.status.code == "pending" })
        #expect(model.resource.value?.dependents.count == 3) // nothing removed until the back office validates
    }

    @Test func vaultUploadOpenDelete() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = VaultViewModel(env: env)
        await model.resource.load()
        #expect(model.resource.value?.count == 2)

        model.category = .vaccine
        #expect(model.documents.map(\.id) == ["vlt_2"])
        model.category = nil

        #expect(await model.upload(sample, title: "Analyse sang", category: .labResult))
        let created = try #require(model.resource.value?.first)
        #expect(created.title == "Analyse sang")

        await model.open(created)
        let url = try #require(model.previewURL)
        #expect(try url.resourceValues(forKeys: [.isExcludedFromBackupKey]).isExcludedFromBackup == true)
        let protection = try FileManager.default.attributesOfItem(atPath: url.path)[.protectionKey] as? FileProtectionType
        #expect(protection == nil || protection == .complete) // Simulator may not report protection classes

        await model.delete(created)
        #expect(model.resource.value?.contains { $0.id == created.id } == false)
        #expect(try await env.api.vaultDocuments(category: nil).count == 2)
    }

    @Test func providersFilterAndSortByDistance() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let pharmacies = try await env.api.providers(type: .pharmacy, latitude: nil, longitude: nil, radiusKm: nil, search: nil)
        #expect(!pharmacies.providers.isEmpty)
        #expect(pharmacies.providers.allSatisfy { $0.type == .pharmacy })

        // Near the Plateau, within 5 km, nearest first; Thiès (60 km) excluded.
        let near = try await env.api.providers(type: nil, latitude: 14.67, longitude: -17.44, radiusKm: 5, search: nil).providers
        let distances = near.compactMap(\.distanceMeters)
        #expect(distances == distances.sorted())
        #expect(distances.allSatisfy { $0 <= 5_000 })
        #expect(!near.contains { $0.city == "Thiès" })

        let pdf = try await env.api.networkPDF(type: .pharmacy)
        #expect(pdf.starts(with: Data("%PDF".utf8)))
    }

    @Test func notificationsReadState() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        #expect(try await env.api.notifications().unreadCount == 2)
        try await env.api.markNotificationRead(id: "ntf_1")
        #expect(try await env.api.notifications().unreadCount == 1)
        try await env.api.markAllNotificationsRead()
        #expect(try await env.api.notifications().unreadCount == 0)
    }

    @Test func deepLinksOpenTheRightScreen() {
        let router = Router()
        router.open(url: URL(string: "assurplus://claims/clm_2")!)
        #expect(router.selectedTab == .claims)
        #expect(router.claimsPath.count == 1)

        router.open(url: URL(string: "assurplus://card")!)
        #expect(router.selectedTab == .card)

        router.open(DeepLinkTarget(kind: .payment, id: "pay_1"))
        #expect(router.selectedTab == .home)
        #expect(router.homePath.count == 2)

        router.open(url: URL(string: "assurplus://subscribe")!)
        #expect(router.presentedSheet == .subscription)

        router.open(url: URL(string: "https://evil.example/claims/1")!) // other schemes are ignored
        #expect(router.presentedSheet == .subscription)
    }

    @Test func pushPayloadCarriesOnlyATarget() {
        let target = AppDelegate.target(from: ["aps": ["alert": "Mise à jour de votre sinistre"], "target": ["kind": "claim", "id": "clm_1"]])
        #expect(target == DeepLinkTarget(kind: .claim, id: "clm_1"))
        #expect(AppDelegate.target(from: ["target": ["kind": "unknown"]]) == nil)
    }

    @Test func profileUpdateAndPasswordChange() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let me = try await env.api.updateMe(MeUpdate(email: "awa@example.org", city: "Saint-Louis"))
        #expect(me.city == "Saint-Louis")
        await #expect(throws: APIError.self) {
            try await env.api.changePassword(PasswordChangeRequest(currentPassword: "faux", newPassword: "nouveau123"))
        }
        try await env.api.changePassword(PasswordChangeRequest(currentPassword: "assur1234", newPassword: "nouveau123"))
        #expect(try await env.api.requestAccountDeletion(reason: nil).code == "requested")
    }
}
