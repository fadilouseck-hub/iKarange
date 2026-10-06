import SwiftUI

@MainActor
@Observable
final class FamilyViewModel {
    let resource: RemoteResource<DependentsResponse>
    private let api: AssurAPI
    private let uploader: Uploading
    private(set) var isSending = false
    var error: APIError?
    var confirmation: Message?

    init(env: AppEnvironment) {
        api = env.api
        uploader = env.uploader
        resource = RemoteResource(cache: env.cache, key: .dependents) { try await env.api.dependents() }
    }

    /// Creates an addition request (validated by the back office), with optional supporting documents.
    func requestAddition(_ member: QuoteMember, documents: [PickedDocument]) async -> Bool {
        isSending = true
        defer { isSending = false }
        do {
            var uploadIds: [String] = []
            for document in documents {
                uploadIds.append(try await uploader.upload(document.file, purpose: "dependent_document") { _ in })
            }
            let request = try await api.requestDependentAddition(DependentAddRequest(
                firstName: member.firstName, lastName: member.lastName, relation: member.relation,
                birthDate: member.birthDate, gender: member.gender, uploadIds: uploadIds))
            confirmation = Message(level: .success, text: String(localized: "Demande d'ajout de \(request.fullName) envoyée. \(request.status.label)."))
            await resource.load()
            return true
        } catch {
            self.error = .wrap(error)
            return false
        }
    }

    func requestRemoval(of dependent: Dependent, reason: String) async {
        isSending = true
        defer { isSending = false }
        do {
            let request = try await api.requestDependentRemoval(id: dependent.id, reason: reason)
            confirmation = Message(level: .success, text: String(localized: "Demande de retrait de \(request.fullName) envoyée."))
            await resource.load()
        } catch {
            self.error = .wrap(error)
        }
    }
}

struct FamilyView: View {
    var body: some View {
        WithModel(FamilyViewModel.init) { model in
            FamilyContent(model: model)
        }
        .navigationTitle("Ma famille")
    }
}

private struct FamilyContent: View {
    @Bindable var model: FamilyViewModel
    @Environment(AppEnvironment.self) private var env
    @State private var showAddForm = false
    @State private var removal: Dependent?
    @State private var removalReason = ""

    private var canManage: Bool { env.session.can(.familyManage) && model.resource.value?.canRequestChanges == true }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: DS.Spacing.l) {
                if let message = model.confirmation { MessageBanner(message: message) }
                if let error = model.error { MessageBanner(message: Message(level: .error, text: error.userMessage)) }
                LoadableContent(value: model.resource.value, isLoading: model.resource.isLoading, error: model.resource.error, retry: { Task { await model.resource.load() } }) { response in
                    VStack(alignment: .leading, spacing: DS.Spacing.l) {
                        if let info = response.info {
                            Text(info).font(.footnote).foregroundStyle(DS.Palette.textSecondary)
                        }
                        if response.dependents.isEmpty {
                            EmptyStateView(title: String(localized: "Aucun ayant droit"), message: String(localized: "Ajoutez votre conjoint(e) ou vos enfants à votre contrat."), symbol: "person.3")
                        }
                        ForEach(response.dependents) { dependent in
                            DependentCard(dependent: dependent, canRemove: canManage && dependent.pendingRequest == nil) {
                                removalReason = ""
                                removal = dependent
                            }
                        }
                        if !response.requests.isEmpty {
                            VStack(alignment: .leading, spacing: DS.Spacing.m) {
                                SectionHeader(title: String(localized: "Demandes en cours"))
                                ForEach(response.requests) { request in
                                    HStack(alignment: .top) {
                                        Image(systemName: request.kind == .add ? "person.badge.plus" : "person.badge.minus")
                                            .foregroundStyle(DS.Palette.accent)
                                        VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                                            Text(request.kind == .add ? "Ajout de \(request.fullName)" : "Retrait de \(request.fullName)")
                                                .font(.subheadline.weight(.semibold))
                                            Text(DateText.dateTime(request.createdAt)).font(.caption).foregroundStyle(DS.Palette.textSecondary)
                                            if let message = request.message { Text(message).font(.caption) }
                                        }
                                        Spacer()
                                        StatusBadge(request.status)
                                    }
                                    .accessibilityElement(children: .combine)
                                    .accessibilityIdentifier("family.request.\(request.id)")
                                }
                            }
                            .card()
                        }
                        NavigationLink(value: Route.policy) {
                            Label("Renouvellement et résiliation du contrat", systemImage: "arrow.triangle.2.circlepath")
                                .font(.callout.weight(.semibold))
                                .frame(maxWidth: .infinity, alignment: .leading)
                                .card()
                        }
                        .buttonStyle(.plain)
                    }
                } placeholder: {
                    VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 4); SkeletonCard(lines: 4) }
                }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await model.resource.refresh() }
        .task { await model.resource.load() }
        .toolbar {
            if canManage {
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showAddForm = true } label: { Label("Ajouter", systemImage: "person.badge.plus") }
                        .accessibilityIdentifier("family.add")
                }
            }
        }
        .sheet(isPresented: $showAddForm) {
            AddDependentRequestSheet(model: model)
        }
        .alert("Retirer \(removal?.fullName ?? "")", isPresented: Binding(get: { removal != nil }, set: { if !$0 { removal = nil } })) {
            TextField("Motif du retrait", text: $removalReason)
            Button("Annuler", role: .cancel) {}
            Button("Envoyer la demande", role: .destructive) {
                if let removal { Task { await model.requestRemoval(of: removal, reason: removalReason) } }
            }
        } message: {
            Text("Le retrait sera effectif après validation par votre gestionnaire.")
        }
    }
}

private struct DependentCard: View {
    let dependent: Dependent
    let canRemove: Bool
    let onRemove: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            HStack(spacing: DS.Spacing.m) {
                InitialsAvatar(name: dependent.fullName)
                VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                    Text(dependent.fullName).font(.headline)
                    Text("\(dependent.relationLabel) · né(e) le \(DateText.day(dependent.birthDate))")
                        .font(.caption).foregroundStyle(DS.Palette.textSecondary)
                }
                Spacer()
                StatusBadge(dependent.status)
            }
            if let limits = dependent.limits {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    HStack {
                        AmountTile(label: String(localized: "Consommé"), amount: limits.consumed)
                        AmountTile(label: String(localized: "Plafond disponible"), amount: limits.remaining, tone: .success)
                    }
                    ProgressView(value: limits.annualLimit > 0 ? min(Double(limits.consumed) / Double(limits.annualLimit), 1) : 0)
                        .tint(DS.Palette.accent)
                        .accessibilityLabel(Text("Plafond consommé"))
                }
            }
            if !dependent.guarantees.isEmpty {
                Text(dependent.guarantees.joined(separator: " · ")).font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            if let pending = dependent.pendingRequest {
                StatusBadge(pending.status)
            } else if canRemove {
                Button("Demander le retrait", role: .destructive, action: onRemove)
                    .font(.footnote.weight(.semibold))
                    .accessibilityIdentifier("family.remove.\(dependent.id)")
            }
        }
        .card()
    }
}

private struct AddDependentRequestSheet: View {
    let model: FamilyViewModel
    @Environment(\.dismiss) private var dismiss
    @State private var firstName = ""
    @State private var lastName = ""
    @State private var relation: Relation = .child
    @State private var gender: Gender = .female
    @State private var birthDate = Calendar.current.date(byAdding: .year, value: -5, to: .now) ?? .now
    @State private var documents: [PickedDocument] = []
    @State private var showPicker = false

    private var isValid: Bool {
        !firstName.trimmingCharacters(in: .whitespaces).isEmpty && !lastName.trimmingCharacters(in: .whitespaces).isEmpty
    }

    var body: some View {
        NavigationStack {
            Form {
                Section("Bénéficiaire") {
                    Picker("Lien", selection: $relation) { ForEach(Relation.allCases) { Text($0.label).tag($0) } }
                    TextField("Prénom", text: $firstName).textContentType(.givenName).accessibilityIdentifier("family.firstName")
                    TextField("Nom", text: $lastName).textContentType(.familyName).accessibilityIdentifier("family.lastName")
                    Picker("Sexe", selection: $gender) { ForEach(Gender.allCases) { Text($0.label).tag($0) } }
                    DatePicker("Date de naissance", selection: $birthDate, in: ...Date.now, displayedComponents: .date)
                }
                Section {
                    ForEach(documents.indices, id: \.self) { index in
                        Label(documents[index].file.fileName, systemImage: "doc")
                    }
                    Button("Ajouter un justificatif") { showPicker = true }
                } header: {
                    Text("Justificatifs")
                } footer: {
                    Text("Acte de naissance, certificat de mariage… La demande est validée par votre gestionnaire.")
                }
                if let error = model.error {
                    Section { Text(error.userMessage).foregroundStyle(DS.Palette.danger) }
                }
            }
            .navigationTitle("Ajouter un ayant droit")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Annuler") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    if model.isSending {
                        ProgressView()
                    } else {
                        Button("Envoyer") {
                            Task {
                                let member = QuoteMember(firstName: firstName.trimmingCharacters(in: .whitespaces), lastName: lastName.trimmingCharacters(in: .whitespaces), relation: relation, birthDate: LocalDay(date: birthDate), gender: gender)
                                if await model.requestAddition(member, documents: documents) { dismiss() }
                            }
                        }
                        .disabled(!isValid)
                        .accessibilityIdentifier("family.send")
                    }
                }
            }
            .documentPicker(isPresented: $showPicker, title: String(localized: "Ajouter un justificatif"), baseName: "justificatif-\(documents.count + 1)") {
                documents.append($0)
            }
        }
    }
}
