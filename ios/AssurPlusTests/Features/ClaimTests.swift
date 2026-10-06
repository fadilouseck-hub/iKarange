import Foundation
import Testing
import UIKit
@testable import AssurPlus

@MainActor
@Suite("Claims")
struct ClaimTests {
    private func makeModel() async throws -> (AppEnvironment, NewClaimViewModel) {
        let env = makeMockEnvironment()
        try await signIn(env)
        let model = NewClaimViewModel(env: env)
        model.ocrPollInterval = .milliseconds(10)
        await model.loadOptions()
        return (env, model)
    }

    private var sampleReceipt: PickedDocument { DocumentSource.fromImages([MockDocuments.sampleInvoice()], baseName: "facture")! }

    @Test func fullDeclarationAssignsANumberAndCanBeTracked() async throws {
        let (env, model) = try await makeModel()
        #expect(model.beneficiaries.count == 4)
        #expect(model.claimTypes.contains { $0.code == "pharmacy" })

        model.selectBeneficiary("ben_fatou")
        model.selectType("pharmacy")
        #expect(model.step == .capture)

        await model.setReceipt(sampleReceipt)
        #expect(model.uploadProgress == 1)
        #expect(model.step == .review)
        #expect(model.fields.first { $0.key == "total" }?.value == "20000")

        // Server threshold 0.85: "patient" (0.42) and one line (0.79) need review.
        #expect(model.isLowConfidence(model.fields.first { $0.key == "patient" }?.confidence))
        #expect(model.lowConfidenceCount == 2)
        #expect(!model.isLowConfidence(nil))

        if let index = model.fields.firstIndex(where: { $0.key == "patient" }) { model.fields[index].value = "Fatou Diop" }
        await model.submit()
        let submitted = try #require(model.submitted)
        #expect(model.step == .done)
        #expect(submitted.number?.hasPrefix("SIN-2026-") == true)
        #expect(submitted.status == .submitted)
        #expect(env.cache.load(ClaimDraft.self, key: .claimDraft) == nil)

        // Status tracking: the next fetches move it to "En analyse".
        _ = try await env.api.claim(id: submitted.id)
        let tracked = try await env.api.claim(id: submitted.id)
        #expect(tracked.status == .inReview)
        #expect(tracked.timeline.map(\.status) == [.draft, .submitted, .inReview])
        #expect(tracked.fields.first { $0.key == "patient" }?.value == "Fatou Diop")
    }

    @Test func receiptIsCompressedBeforeUpload() {
        let big = UIGraphicsImageRenderer(size: CGSize(width: 3000, height: 4000), format: {
            let f = UIGraphicsImageRendererFormat(); f.scale = 1; return f
        }()).image { _ in UIColor.white.setFill(); UIRectFill(CGRect(x: 0, y: 0, width: 3000, height: 4000)) }
        let picked = DocumentSource.fromImages([big, big], baseName: "facture")!
        #expect(picked.file.mimeType == "image/jpeg")
        let image = UIImage(data: picked.file.data)!
        #expect(max(image.size.width, image.size.height) * image.scale <= 1600)
    }

    @Test func ocrTimeoutFallsBackToManualEntry() async throws {
        let (_, model) = try await makeModel()
        model.ocrTimeout = .milliseconds(1) // the mock needs 3 polls
        model.selectBeneficiary("ben_awa")
        model.selectType("consultation")
        await model.setReceipt(sampleReceipt)
        #expect(model.step == .review)
        #expect(model.fields.map(\.key) == ["invoiceNumber", "date", "provider", "patient", "act", "total"])
        #expect(!model.canSubmit) // total required
        if let index = model.fields.firstIndex(where: { $0.key == "total" }) { model.fields[index].value = "15000" }
        #expect(model.canSubmit)
    }

    @Test func draftIsSavedAndResumed() async throws {
        let (env, model) = try await makeModel()
        model.selectBeneficiary("ben_ibou")
        model.selectType("lab")
        #expect(env.cache.load(ClaimDraft.self, key: .claimDraft)?.typeCode == "lab")

        let reopened = NewClaimViewModel(env: env)
        #expect(reopened.pendingDraft != nil)
        await reopened.loadOptions()
        await reopened.resumeDraft()
        #expect(reopened.step == .capture)
        #expect(reopened.selectedBeneficiary?.fullName == "Ibrahima Diop")

        reopened.discardDraft()
        #expect(env.cache.load(ClaimDraft.self, key: .claimDraft) == nil)
    }

    @Test func requestedDocumentCanBeAddedFromTheClaim() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let detail = ClaimDetailViewModel(env: env, claimId: "clm_2")
        await detail.resource.load()
        #expect(detail.resource.value?.status == .documentsRequested)
        await detail.send(sampleReceipt, for: "req_1")
        #expect(detail.resource.value?.documentRequests.first?.fulfilled == true)
        #expect(detail.resource.value?.status == .inReview)
    }

    @Test func validatedClaimShowsServerSettlement() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        let claim = try await env.api.claim(id: "clm_1")
        let settlement = try #require(claim.settlement)
        #expect(settlement.insurerAmount == 16_000)
        #expect(settlement.remainingAmount == 4_000)
    }

    @Test func dependentCannotClaimForOthers() async throws {
        let env = makeMockEnvironment()
        try await signIn(env, phone: "770000002")
        await #expect(throws: APIError.self) { _ = try await env.api.createClaim(beneficiaryId: "ben_fatou", typeCode: "pharmacy") }
        #expect(try await env.api.claims().allSatisfy { $0.beneficiaryName == "Moussa Diop" })
    }

    @Test func resumableUploadContinuesAfterFailures() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        // Wrap the mock server so every other chunk fails once with a network error.
        actor Flaky: HTTPTransport {
            let inner: MockServer
            var failNext = true
            var failures = 0
            init(_ inner: MockServer) { self.inner = inner }
            func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
                if request.httpMethod == "PATCH", request.url!.path.contains("uploads") {
                    failNext.toggle()
                    if !failNext { failures += 1; throw URLError(.networkConnectionLost) }
                }
                return try await inner.send(request)
            }
        }
        let server = MockServer(latency: .zero)
        let tokens = InMemoryTokenStore()
        let api = AssurAPI(client: APIClient(baseURL: MockServer.baseURL, transport: Flaky(server), tokens: tokens))
        tokens.save(try await api.login(LoginRequest(phone: "+221770000001", password: "assur1234")).tokens)

        let data = Data((0..<700_000).map { UInt8($0 % 251) }) // 3 chunks of 256 KB
        let uploader = ResumableUploader(api: api, baseDelay: .milliseconds(1))
        let progress = ProgressLog()
        let id = try await uploader.upload(UploadFile(data: data, fileName: "a.bin", mimeType: "application/octet-stream"), purpose: "claim_document") { progress.append($0) }
        #expect(try await api.uploadOffset(uploadId: id).offset == data.count)
        #expect(progress.values.last == 1)
    }
}

final class ProgressLog: @unchecked Sendable {
    private let lock = NSLock()
    private var storage: [Double] = []
    func append(_ value: Double) { lock.withLock { storage.append(value) } }
    var values: [Double] { lock.withLock { storage } }
}
