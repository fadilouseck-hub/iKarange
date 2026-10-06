import QuickLook
import SwiftUI

@MainActor
@Observable
final class PolicyViewModel {
    let resource: RemoteResource<PolicyDetail>
    private let api: AssurAPI
    private(set) var pdfURL: URL?
    private(set) var isDownloading = false
    private(set) var terminationStatus: ServerStatus?
    var error: APIError?
    var previewURL: URL?

    init(env: AppEnvironment) {
        api = env.api
        let cache = env.cache
        resource = RemoteResource(cache: env.cache, key: .policy) {
            let policyId: String
            if let cached = cache.load(Dashboard.self, key: .dashboard)?.policy?.id {
                policyId = cached
            } else if let fresh = try await env.api.dashboard().policy?.id {
                policyId = fresh
            } else {
                throw APIError.server(status: 404, code: "no_policy", message: String(localized: "Aucun contrat actif."), fields: [:])
            }
            return try await env.api.policy(id: policyId)
        }
    }

    /// Downloads the Conditions Particulières into protected storage, then opens QuickLook.
    func openConditions() async {
        guard let policy = resource.value else { return }
        if let pdfURL, FileManager.default.fileExists(atPath: pdfURL.path) {
            previewURL = pdfURL
            return
        }
        isDownloading = true
        defer { isDownloading = false }
        do {
            let data = try await api.conditionsPDF(policyId: policy.summary.id)
            let url = try ProtectedStorage.write(data, named: "Conditions-particulieres-\(policy.summary.number).pdf")
            pdfURL = url
            previewURL = url
        } catch {
            self.error = .wrap(error)
        }
    }

    func requestTermination(reason: String) async {
        guard let policy = resource.value else { return }
        do {
            terminationStatus = try await api.requestTermination(policyId: policy.summary.id, reason: reason)
        } catch {
            self.error = .wrap(error)
        }
    }
}

struct PolicyView: View {
    var body: some View {
        WithModel(PolicyViewModel.init) { model in
            PolicyContent(model: model)
        }
        .navigationTitle("Mon contrat")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct PolicyContent: View {
    @Bindable var model: PolicyViewModel
    @Environment(AppEnvironment.self) private var env
    @State private var showTermination = false
    @State private var terminationReason = ""

    var body: some View {
        ScrollView {
            LoadableContent(value: model.resource.value, isLoading: model.resource.isLoading, error: model.resource.error, retry: { Task { await model.resource.load() } }) { policy in
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    summary(policy)
                    conditions(policy)
                    members(policy)
                    guarantees(policy)
                    if let renewal = policy.renewal { renewalSection(renewal, pending: policy.pendingTermination) }
                    rules(policy)
                }
            } placeholder: {
                VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 4); SkeletonCard(lines: 5) }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await model.resource.refresh() }
        .task { await model.resource.load() }
        .quickLookPreview($model.previewURL)
        .alert("Erreur", isPresented: Binding(get: { model.error != nil }, set: { if !$0 { model.error = nil } })) {
            Button("OK", role: .cancel) {}
        } message: {
            Text(model.error?.userMessage ?? "")
        }
        .alert("Demande de résiliation", isPresented: $showTermination) {
            TextField("Motif", text: $terminationReason)
            Button("Annuler", role: .cancel) {}
            Button("Envoyer", role: .destructive) { Task { await model.requestTermination(reason: terminationReason) } }
        } message: {
            Text("Votre demande sera traitée par votre gestionnaire. Le contrat reste actif jusqu'à son échéance.")
        }
    }

    private func summary(_ policy: PolicyDetail) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            HStack {
                Text("Formule \(policy.summary.formulaName)").font(.title3.weight(.bold))
                Spacer()
                StatusBadge(policy.summary.status)
            }
            InfoRow(label: String(localized: "N° de contrat"), value: policy.summary.number, emphasized: true)
            InfoRow(label: String(localized: "Assureur"), value: policy.insurerName)
            InfoRow(label: String(localized: "Produit"), value: policy.summary.productName)
            InfoRow(label: String(localized: "Taux de couverture"), value: Percent.format(policy.summary.coverageRate))
            if let territoriality = policy.summary.territoriality {
                InfoRow(label: String(localized: "Territorialité"), value: territoriality)
            }
            InfoRow(label: String(localized: "Période"), value: "\(DateText.day(policy.summary.startDate)) → \(DateText.day(policy.summary.endDate))")
            if let premium = policy.premiumLabel {
                InfoRow(label: String(localized: "Prime"), value: premium)
            }
        }
        .card()
    }

    private func conditions(_ policy: PolicyDetail) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            SectionHeader(title: String(localized: "Conditions particulières"))
            if let version = policy.conditionsVersion {
                Text("Version \(version)").font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            HStack(spacing: DS.Spacing.m) {
                Button {
                    Task { await model.openConditions() }
                } label: {
                    Label("Consulter", systemImage: "doc.richtext")
                }
                .buttonStyle(.primary(loading: model.isDownloading))
                .accessibilityIdentifier("policy.openConditions")
                if let url = model.pdfURL {
                    ShareLink(item: url) {
                        Label("Partager", systemImage: "square.and.arrow.up")
                    }
                    .buttonStyle(.secondary)
                }
            }
        }
        .card()
    }

    private func members(_ policy: PolicyDetail) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            SectionHeader(title: String(localized: "Bénéficiaires"))
            ForEach(policy.members) { member in
                HStack(spacing: DS.Spacing.m) {
                    InitialsAvatar(name: member.fullName, size: 36)
                    VStack(alignment: .leading) {
                        Text(member.fullName).font(.subheadline.weight(.semibold))
                        Text(member.relationLabel).font(.caption).foregroundStyle(DS.Palette.textSecondary)
                    }
                    Spacer()
                    if let birth = member.birthDate {
                        Text(DateText.day(birth)).font(.caption).foregroundStyle(DS.Palette.textSecondary)
                    }
                }
                .accessibilityElement(children: .combine)
            }
        }
        .card()
    }

    private func guarantees(_ policy: PolicyDetail) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            SectionHeader(title: String(localized: "Garanties"))
            ForEach(policy.guarantees) { guarantee in
                VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                    HStack {
                        Text(guarantee.label).font(.subheadline.weight(.semibold))
                        Spacer()
                        if let rate = guarantee.rateLabel { Text(rate).font(.subheadline.monospacedDigit()) }
                    }
                    if let limit = guarantee.limitLabel { Text(limit).font(.caption).foregroundStyle(DS.Palette.textSecondary) }
                    if let description = guarantee.description { Text(description).font(.caption).foregroundStyle(DS.Palette.textSecondary) }
                }
                .accessibilityElement(children: .combine)
                if guarantee != policy.guarantees.last { Divider() }
            }
        }
        .card()
    }

    private func renewalSection(_ renewal: RenewalInfo, pending: ServerStatus?) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            SectionHeader(title: String(localized: "Renouvellement"))
            InfoRow(label: String(localized: "Échéance"), value: DateText.day(renewal.renewalDate))
            InfoRow(label: String(localized: "Tacite reconduction"), value: renewal.tacitRenewal ? String(localized: "Oui") : String(localized: "Non"))
            if let deadline = renewal.terminationDeadline {
                InfoRow(label: String(localized: "Résiliation possible jusqu'au"), value: DateText.day(deadline))
            }
            if let info = renewal.info {
                Text(info).font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            if let status = model.terminationStatus ?? pending {
                StatusBadge(status)
            } else if renewal.canRequestTermination, env.session.can(.policyManage) {
                Button("Demander la résiliation", role: .destructive) { showTermination = true }
                    .font(.callout.weight(.semibold))
                    .padding(.top, DS.Spacing.s)
            }
        }
        .card()
    }

    private func rules(_ policy: PolicyDetail) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            SectionHeader(title: String(localized: "Exclusions et carences"))
            ForEach(policy.exclusions + policy.waitingPeriods, id: \.self) { rule in
                Label(rule, systemImage: "minus.circle").font(.callout)
            }
            if let deductible = policy.deductibleLabel {
                InfoRow(label: String(localized: "Franchise"), value: deductible)
            }
        }
        .card()
    }
}
