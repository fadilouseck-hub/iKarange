import SwiftUI

@MainActor
@Observable
final class ClaimDetailViewModel {
    let resource: RemoteResource<Claim>
    private let api: AssurAPI
    private let uploader: Uploading
    private(set) var uploadingRequestId: String?
    private(set) var uploadProgress: Double = 0
    var error: APIError?

    init(env: AppEnvironment, claimId: String) {
        api = env.api
        uploader = env.uploader
        resource = RemoteResource { try await env.api.claim(id: claimId) }
    }

    /// Answers a "pièces complémentaires" request directly from the claim screen.
    func send(_ document: PickedDocument, for requestId: String) async {
        guard let claim = resource.value else { return }
        uploadingRequestId = requestId
        uploadProgress = 0
        defer { uploadingRequestId = nil }
        do {
            let uploadId = try await uploader.upload(document.file, purpose: "claim_document") { [weak self] progress in
                Task { @MainActor in self?.uploadProgress = progress }
            }
            _ = try await api.attachClaimDocument(claimId: claim.id, ClaimDocumentAttach(uploadId: uploadId, kind: "additional", requestId: requestId))
            await resource.load()
        } catch {
            self.error = .wrap(error)
        }
    }
}

struct ClaimDetailView: View {
    let claimId: String

    var body: some View {
        WithModel({ ClaimDetailViewModel(env: $0, claimId: claimId) }) { model in
            ClaimDetailContent(model: model)
        }
        .navigationTitle("Sinistre")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct ClaimDetailContent: View {
    let model: ClaimDetailViewModel
    @State private var pickerRequestId: String?
    @State private var showPicker = false

    var body: some View {
        ScrollView {
            LoadableContent(value: model.resource.value, isLoading: model.resource.isLoading, error: model.resource.error, retry: { Task { await model.resource.load() } }) { claim in
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    header(claim)
                    if let error = model.error { MessageBanner(message: Message(level: .error, text: error.userMessage)) }
                    if let reason = claim.rejectionReason {
                        MessageBanner(message: Message(level: .error, text: String(localized: "Motif du rejet : \(reason)")))
                    }
                    if !claim.documentRequests.isEmpty { requests(claim) }
                    if let settlement = claim.settlement { SettlementCard(settlement: settlement) }
                    timeline(claim)
                    if !claim.fields.isEmpty || !claim.lines.isEmpty { details(claim) }
                }
            } placeholder: {
                VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 3); SkeletonCard(lines: 5) }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await model.resource.refresh() }
        .task {
            await model.resource.load()
            // Live tracking while the claim is being processed.
            while !Task.isCancelled {
                try? await Task.sleep(for: .seconds(20))
                guard let status = model.resource.value?.status, ![.paid, .closed, .rejected].contains(status) else { break }
                await model.resource.load()
            }
        }
        .documentPicker(isPresented: $showPicker, title: String(localized: "Ajouter la pièce demandée"), baseName: "piece") { document in
            if let requestId = pickerRequestId { Task { await model.send(document, for: requestId) } }
        }
    }

    private func header(_ claim: Claim) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            HStack(alignment: .top) {
                VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                    Text(claim.typeLabel).font(.title3.weight(.bold))
                    Text(claim.beneficiaryName).font(.callout).foregroundStyle(DS.Palette.textSecondary)
                }
                Spacer()
                StatusBadge(claim.status).accessibilityIdentifier("claimDetail.status")
            }
            if let number = claim.number {
                InfoRow(label: String(localized: "N° de sinistre"), value: number, emphasized: true)
                    .accessibilityIdentifier("claimDetail.number")
            }
            InfoRow(label: String(localized: "Déclaré le"), value: DateText.dateTime(claim.submittedAt ?? claim.createdAt))
            if let total = claim.fields.first(where: { $0.key == "total" }).flatMap({ Int($0.value) }) {
                InfoRow(label: String(localized: "Montant déclaré"), value: Money.format(total))
            }
        }
        .card()
    }

    private func requests(_ claim: Claim) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            SectionHeader(title: String(localized: "Pièces complémentaires demandées"))
            ForEach(claim.documentRequests) { request in
                HStack {
                    Image(systemName: request.fulfilled ? "checkmark.circle.fill" : "doc.badge.plus")
                        .foregroundStyle(request.fulfilled ? DS.Palette.success : DS.Palette.warning)
                    Text(request.label).font(.callout)
                    Spacer()
                    if model.uploadingRequestId == request.id {
                        ProgressView(value: model.uploadProgress).frame(width: 60)
                    } else if !request.fulfilled {
                        Button("Ajouter") {
                            pickerRequestId = request.id
                            showPicker = true
                        }
                        .buttonStyle(.borderedProminent)
                        .tint(DS.Palette.primary)
                        .accessibilityIdentifier("claimDetail.addDocument.\(request.id)")
                    } else {
                        Text("Reçue").font(.caption).foregroundStyle(DS.Palette.success)
                    }
                }
            }
        }
        .card()
    }

    private func timeline(_ claim: Claim) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            SectionHeader(title: String(localized: "Suivi"))
            ForEach(Array(claim.timeline.reversed().enumerated()), id: \.element.id) { index, event in
                HStack(alignment: .top, spacing: DS.Spacing.m) {
                    VStack(spacing: 0) {
                        Image(systemName: event.status.symbol)
                            .font(.footnote.weight(.bold))
                            .foregroundStyle(index == 0 ? .white : event.status.tone.foreground)
                            .frame(width: 30, height: 30)
                            .background(index == 0 ? event.status.tone.foreground : event.status.tone.background, in: Circle())
                        if index < claim.timeline.count - 1 {
                            Rectangle().fill(DS.Palette.border).frame(width: 2).frame(minHeight: 24)
                        }
                    }
                    VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                        Text(event.status.label).font(.subheadline.weight(.semibold))
                        Text(DateText.dateTime(event.date)).font(.caption).foregroundStyle(DS.Palette.textSecondary)
                        if let message = event.message { Text(message).font(.callout) }
                    }
                    .padding(.bottom, DS.Spacing.s)
                }
                .accessibilityElement(children: .combine)
            }
        }
        .card()
        .accessibilityIdentifier("claimDetail.timeline")
    }

    private func details(_ claim: Claim) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            SectionHeader(title: String(localized: "Informations du justificatif"))
            ForEach(claim.fields) { field in
                InfoRow(label: field.label, value: field.key == "total" ? (Int(field.value).map(Money.format) ?? field.value) : field.value)
            }
            if !claim.lines.isEmpty {
                Divider()
                ForEach(claim.lines) { line in
                    HStack {
                        Text("\(line.quantity) × \(line.label)").font(.callout)
                        Spacer()
                        Text(Int(line.amount).map(Money.format) ?? line.amount).font(.callout.monospacedDigit())
                    }
                }
            }
        }
        .card()
    }
}

/// Settlement computed by the server's guarantee engine.
struct SettlementCard: View {
    let settlement: Settlement

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            SectionHeader(title: String(localized: "Calcul de la prise en charge"))
            InfoRow(label: String(localized: "Montant facturé"), value: Money.format(settlement.billedAmount))
            InfoRow(label: String(localized: "Montant couvert"), value: Money.format(settlement.coveredAmount))
            InfoRow(label: String(localized: "Taux de remboursement"), value: Percent.format(settlement.reimbursementRate))
            InfoRow(label: String(localized: "Franchise"), value: Money.format(settlement.deductible))
            Divider()
            InfoRow(label: String(localized: "Payé par l'assureur"), value: Money.format(settlement.insurerAmount), emphasized: true)
            InfoRow(label: String(localized: "Reste à charge"), value: Money.format(settlement.remainingAmount), emphasized: true)
        }
        .card()
        .accessibilityIdentifier("claimDetail.settlement")
    }
}
