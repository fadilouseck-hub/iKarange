import Observation
import UIKit

/// Locally saved progress, so a declaration can be finished later or after a network failure.
struct ClaimDraft: Codable, Equatable, Sendable {
    var step: NewClaimViewModel.Step
    var beneficiaryId: String?
    var typeCode: String?
    var claimId: String?
    var receiptFile: String?
    var receiptMimeType: String?
    var fields: [OCRField]
    var lines: [OCRLine]
    var updatedAt: Date
}

/// The 10-step declaration (CDC §10): beneficiary → type → photo → upload → OCR (server) → pre-fill →
/// review → submit → number → tracking. The app never computes amounts or eligibility.
@MainActor
@Observable
final class NewClaimViewModel {
    enum Step: Int, Codable, CaseIterable, Sendable {
        case beneficiary, type, capture, upload, ocr, review, done

        var title: String {
            switch self {
            case .beneficiary: String(localized: "Bénéficiaire", bundle: .appLanguage)
            case .type: String(localized: "Type de prestation", bundle: .appLanguage)
            case .capture: String(localized: "Justificatif", bundle: .appLanguage)
            case .upload: String(localized: "Envoi", bundle: .appLanguage)
            case .ocr: String(localized: "Lecture automatique", bundle: .appLanguage)
            case .review: String(localized: "Vérification", bundle: .appLanguage)
            case .done: String(localized: "Confirmation", bundle: .appLanguage)
            }
        }
    }

    private let api: AssurAPI
    private let uploader: Uploading
    private let cache: ResponseCache

    private(set) var step: Step = .beneficiary
    private(set) var beneficiaries: [CardBeneficiary] = []
    private(set) var claimTypes: [ClaimType] = []
    private(set) var beneficiaryId: String?
    private(set) var typeCode: String?
    private(set) var receipt: PickedDocument?
    private(set) var claim: Claim?
    private(set) var uploadProgress: Double = 0
    private(set) var ocr: OCRResult?
    var fields: [OCRField] = []
    var lines: [OCRLine] = []
    private(set) var submitted: Claim?
    private(set) var isWorking = false
    private(set) var isLoadingOptions = false
    var error: APIError?
    private(set) var pendingDraft: ClaimDraft?

    /// How long to wait for the server OCR before offering manual entry (target < 15 s).
    var ocrTimeout: Duration = .seconds(30)
    var ocrPollInterval: Duration = .seconds(1)

    init(env: AppEnvironment) {
        api = env.api
        uploader = env.uploader
        cache = env.cache
        pendingDraft = env.cache.load(ClaimDraft.self, key: .claimDraft)
    }

    var selectedBeneficiary: CardBeneficiary? { beneficiaries.first { $0.id == beneficiaryId } }
    var selectedType: ClaimType? { claimTypes.first { $0.code == typeCode } }
    var reviewThreshold: Double { ocr?.reviewThreshold ?? 0.85 }
    var progressIndex: Int { step.rawValue + 1 }

    func isLowConfidence(_ confidence: Double?) -> Bool {
        guard let confidence else { return false }
        return confidence < reviewThreshold
    }

    var lowConfidenceCount: Int {
        fields.filter { isLowConfidence($0.confidence) }.count + lines.filter { isLowConfidence($0.confidence) }.count
    }

    var canSubmit: Bool {
        !isWorking && !(fields.first { $0.key == "total" }?.value.trimmingCharacters(in: .whitespaces).isEmpty ?? true)
    }

    // MARK: Options

    func loadOptions() async {
        isLoadingOptions = true
        defer { isLoadingOptions = false }
        do {
            async let card = api.card()
            async let types = api.claimTypes()
            beneficiaries = try await card.beneficiaries
            claimTypes = try await types
            if beneficiaries.count == 1, beneficiaryId == nil { beneficiaryId = beneficiaries.first?.id }
            error = nil
        } catch {
            // Offline: fall back to the cached card so the user can still prepare a draft.
            beneficiaries = cache.load(MemberCard.self, key: .card)?.beneficiaries ?? []
            claimTypes = cache.load([ClaimType].self, key: .claimTypes) ?? []
            self.error = .wrap(error)
        }
        if !claimTypes.isEmpty { cache.store(claimTypes, key: .claimTypes) }
    }

    func selectBeneficiary(_ id: String) {
        beneficiaryId = id
        step = .type
        saveDraft()
    }

    func selectType(_ code: String) {
        typeCode = code
        step = .capture
        saveDraft()
    }

    func back() {
        switch step {
        case .type: step = .beneficiary
        case .capture: step = .type
        case .review where claim == nil: step = .capture
        default: break
        }
    }

    // MARK: Capture & upload

    func setReceipt(_ document: PickedDocument) async {
        receipt = document
        step = .upload
        saveDraft()
        await upload()
    }

    /// Creates the server draft if needed, uploads the receipt (resumable) and attaches it.
    func upload() async {
        guard let receipt, let beneficiaryId, let typeCode else { return }
        isWorking = true
        error = nil
        defer { isWorking = false }
        do {
            if claim == nil {
                claim = try await api.createClaim(beneficiaryId: beneficiaryId, typeCode: typeCode)
                saveDraft()
            }
            guard let claimId = claim?.id else { return }
            uploadProgress = 0
            let uploadId = try await uploader.upload(receipt.file, purpose: "claim_document") { [weak self] progress in
                Task { @MainActor in self?.uploadProgress = progress }
            }
            _ = try await api.attachClaimDocument(claimId: claimId, ClaimDocumentAttach(uploadId: uploadId, kind: "receipt", requestId: nil))
            uploadProgress = 1
            step = .ocr
            saveDraft()
            await waitForOCR()
        } catch {
            self.error = .wrap(error)
        }
    }

    /// Polls the server-side OCR, then pre-fills the form. Falls back to manual entry on failure/timeout.
    func waitForOCR() async {
        guard let claimId = claim?.id else { return }
        let clock = ContinuousClock()
        let deadline = clock.now.advanced(by: ocrTimeout)
        while clock.now < deadline, !Task.isCancelled {
            do {
                let result = try await api.ocr(claimId: claimId)
                ocr = result
                switch result.state {
                case .done:
                    fields = result.fields
                    lines = result.lines
                    step = .review
                    saveDraft()
                    return
                case .failed:
                    startManualEntry()
                    return
                case .pending, .processing:
                    try await Task.sleep(for: ocrPollInterval)
                }
            } catch {
                let apiError = APIError.wrap(error)
                if apiError == .cancelled { return }
                if !apiError.isRetryable {
                    self.error = apiError
                    startManualEntry()
                    return
                }
                try? await Task.sleep(for: ocrPollInterval)
            }
        }
        startManualEntry()
    }

    /// Empty form when OCR is unavailable; the claim can still be submitted and checked by a human.
    func startManualEntry() {
        if fields.isEmpty {
            fields = [
                ("invoiceNumber", String(localized: "N° de facture", bundle: .appLanguage)), ("date", String(localized: "Date", bundle: .appLanguage)),
                ("provider", String(localized: "Prestataire", bundle: .appLanguage)), ("patient", String(localized: "Patient", bundle: .appLanguage)),
                ("act", String(localized: "Acte", bundle: .appLanguage)), ("total", String(localized: "Montant total", bundle: .appLanguage)),
            ].map { OCRField(key: $0.0, label: $0.1, value: "", confidence: nil) }
        }
        step = .review
        saveDraft()
    }

    func addLine() {
        lines.append(OCRLine(id: UUID().uuidString, label: "", quantity: "1", unitPrice: "", amount: "", confidence: nil))
    }

    func removeLine(_ id: String) { lines.removeAll { $0.id == id } }

    // MARK: Submit

    func submit() async {
        guard let claimId = claim?.id else { return }
        isWorking = true
        error = nil
        defer { isWorking = false }
        do {
            let values = Dictionary(fields.map { ($0.key, $0.value.trimmingCharacters(in: .whitespaces)) }, uniquingKeysWith: { $1 })
            _ = try await api.updateClaim(id: claimId, ClaimUpdate(fields: values, lines: lines))
            submitted = try await api.submitClaim(id: claimId)
            step = .done
            discardDraft()
        } catch {
            self.error = .wrap(error)
            saveDraft()
        }
    }

    // MARK: Drafts

    func saveDraft() {
        guard step != .done else { return }
        var receiptFile: String?
        if let receipt {
            let name = "claim-draft-receipt"
            receiptFile = (try? ProtectedStorage.write(receipt.file.data, named: name))?.lastPathComponent
        }
        let draft = ClaimDraft(
            step: step, beneficiaryId: beneficiaryId, typeCode: typeCode, claimId: claim?.id, receiptFile: receiptFile,
            receiptMimeType: receipt?.file.mimeType, fields: fields, lines: lines, updatedAt: .now)
        cache.store(draft, key: .claimDraft)
    }

    func resumeDraft() async {
        guard let draft = pendingDraft else { return }
        pendingDraft = nil
        beneficiaryId = draft.beneficiaryId
        typeCode = draft.typeCode
        fields = draft.fields
        lines = draft.lines
        if let file = draft.receiptFile,
           let data = try? Data(contentsOf: ProtectedStorage.directory("Files").appendingPathComponent(file)) {
            let mime = draft.receiptMimeType ?? "image/jpeg"
            receipt = PickedDocument(file: UploadFile(data: data, fileName: mime == "application/pdf" ? "justificatif.pdf" : "justificatif.jpg", mimeType: mime), preview: UIImage(data: data))
        }
        if let claimId = draft.claimId { claim = try? await api.claim(id: claimId) }
        switch draft.step {
        case .upload where receipt != nil:
            step = .upload
            await upload()
        case .ocr where claim != nil:
            step = .ocr
            await waitForOCR()
        case .done:
            step = .beneficiary
        default:
            step = draft.step == .upload || draft.step == .ocr ? .capture : draft.step
        }
    }

    func discardDraft() {
        pendingDraft = nil
        cache.remove(.claimDraft)
        try? FileManager.default.removeItem(at: ProtectedStorage.directory("Files").appendingPathComponent("claim-draft-receipt"))
    }
}
