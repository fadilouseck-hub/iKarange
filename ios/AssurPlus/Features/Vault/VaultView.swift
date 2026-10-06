import QuickLook
import SwiftUI

/// Health vault. Files are encrypted at rest on the server; local copies opened for viewing live in
/// protected storage (`NSFileProtectionComplete`, excluded from backups) and are wiped at logout.
@MainActor
@Observable
final class VaultViewModel {
    let resource: RemoteResource<[VaultDocument]>
    private let api: AssurAPI
    private let uploader: Uploading
    var category: VaultCategory?
    private(set) var openingId: String?
    private(set) var uploadProgress: Double?
    var previewURL: URL?
    var error: APIError?

    init(env: AppEnvironment) {
        api = env.api
        uploader = env.uploader
        resource = RemoteResource(cache: env.cache, key: .vault) { try await env.api.vaultDocuments(category: nil) }
    }

    var documents: [VaultDocument] {
        let all = resource.value ?? []
        guard let category else { return all }
        return all.filter { $0.category == category }
    }

    func open(_ document: VaultDocument) async {
        openingId = document.id
        defer { openingId = nil }
        do {
            let data = try await api.vaultFile(id: document.id)
            let ext = document.mimeType == "application/pdf" ? "pdf" : (URL(fileURLWithPath: document.fileName).pathExtension.isEmpty ? "jpg" : URL(fileURLWithPath: document.fileName).pathExtension)
            // The mock serves every file as PDF; trust the bytes over the declared type.
            let isPDF = data.starts(with: Data("%PDF".utf8))
            previewURL = try ProtectedStorage.write(data, named: "vault-\(document.id).\(isPDF ? "pdf" : ext)")
        } catch {
            self.error = .wrap(error)
        }
    }

    func upload(_ picked: PickedDocument, title: String, category: VaultCategory) async -> Bool {
        uploadProgress = 0
        defer { uploadProgress = nil }
        do {
            let uploadId = try await uploader.upload(picked.file, purpose: "vault_document") { [weak self] progress in
                Task { @MainActor in self?.uploadProgress = progress }
            }
            let document = try await api.createVaultDocument(VaultDocumentCreate(uploadId: uploadId, category: category, title: title, beneficiaryId: nil))
            resource.update([document] + (resource.value ?? []))
            return true
        } catch {
            self.error = .wrap(error)
            return false
        }
    }

    func delete(_ document: VaultDocument) async {
        do {
            try await api.deleteVaultDocument(id: document.id)
            resource.update((resource.value ?? []).filter { $0.id != document.id })
            try? FileManager.default.removeItem(at: ProtectedStorage.directory("Files").appendingPathComponent("vault-\(document.id).pdf"))
        } catch {
            self.error = .wrap(error)
        }
    }
}

struct VaultView: View {
    var body: some View {
        WithModel(VaultViewModel.init) { model in
            VaultContent(model: model)
        }
        .navigationTitle("Coffre santé")
    }
}

private struct VaultContent: View {
    @Bindable var model: VaultViewModel
    @Environment(AppEnvironment.self) private var env
    @State private var showPicker = false
    @State private var pending: PickedDocument?
    @State private var toDelete: VaultDocument?

    var body: some View {
        List {
            Section {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: DS.Spacing.s) {
                        chip(nil, label: String(localized: "Tous"), symbol: "tray.full")
                        ForEach(VaultCategory.allCases) { chip($0, label: $0.label, symbol: $0.symbol) }
                    }
                    .padding(.vertical, DS.Spacing.xs)
                }
                .listRowInsets(EdgeInsets(top: 0, leading: DS.Spacing.l, bottom: 0, trailing: DS.Spacing.l))
                .listRowBackground(Color.clear)
                Label("Documents chiffrés, visibles uniquement par vous.", systemImage: "lock.shield")
                    .font(.footnote).foregroundStyle(DS.Palette.textSecondary)
                    .listRowBackground(Color.clear)
                if let progress = model.uploadProgress {
                    ProgressView("Envoi du document…", value: progress).tint(DS.Palette.accent)
                }
                if let error = model.error {
                    Text(error.userMessage).font(.callout).foregroundStyle(DS.Palette.danger)
                }
            }

            Section {
                if model.resource.value == nil && model.resource.isLoading {
                    ForEach(0..<3, id: \.self) { _ in SkeletonBlock(height: 44) }
                } else if model.documents.isEmpty {
                    EmptyStateView(title: String(localized: "Aucun document"), message: String(localized: "Ajoutez vos ordonnances, analyses, radios et carnets de vaccination."), symbol: "lock.doc")
                        .listRowBackground(Color.clear)
                }
                ForEach(model.documents) { document in
                    Button { Task { await model.open(document) } } label: { row(document) }
                        .buttonStyle(.plain)
                        .swipeActions {
                            if env.session.can(.vaultManage) {
                                Button("Supprimer", role: .destructive) { toDelete = document }
                            }
                        }
                        .accessibilityIdentifier("vault.document.\(document.id)")
                }
            }
        }
        .scrollContentBackground(.hidden)
        .screenBackground()
        .refreshable { await model.resource.refresh() }
        .task { await model.resource.load() }
        .quickLookPreview($model.previewURL)
        .toolbar {
            if env.session.can(.vaultManage) {
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showPicker = true } label: { Label("Ajouter", systemImage: "plus") }
                        .accessibilityIdentifier("vault.add")
                }
            }
        }
        .documentPicker(isPresented: $showPicker, title: String(localized: "Ajouter un document"), baseName: "document") { pending = $0 }
        .sheet(isPresented: Binding(get: { pending != nil }, set: { if !$0 { pending = nil } })) {
            if let pending {
                VaultDocumentForm(document: pending, initialCategory: model.category ?? .prescription) { title, category in
                    Task { _ = await model.upload(pending, title: title, category: category) }
                }
            }
        }
        .confirmationDialog("Supprimer « \(toDelete?.title ?? "") » ?", isPresented: Binding(get: { toDelete != nil }, set: { if !$0 { toDelete = nil } }), titleVisibility: .visible) {
            Button("Supprimer définitivement", role: .destructive) {
                if let toDelete { Task { await model.delete(toDelete) } }
            }
        }
    }

    private func chip(_ category: VaultCategory?, label: String, symbol: String) -> some View {
        let selected = model.category == category
        return Button { model.category = category } label: {
            Label(label, systemImage: symbol)
                .font(.subheadline.weight(.semibold))
                .padding(.horizontal, DS.Spacing.m).padding(.vertical, DS.Spacing.s)
                .foregroundStyle(selected ? DS.Palette.onPrimary : DS.Palette.textPrimary)
                .background(selected ? DS.Palette.primary : DS.Palette.surface, in: Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(selected ? .isSelected : [])
    }

    private func row(_ document: VaultDocument) -> some View {
        HStack(spacing: DS.Spacing.m) {
            Image(systemName: document.category.symbol)
                .foregroundStyle(DS.Palette.accent)
                .frame(width: 40, height: 40)
                .background(DS.Palette.accentSoft, in: RoundedRectangle(cornerRadius: DS.Radius.s))
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                Text(document.title).font(.subheadline.weight(.semibold))
                Text([document.category.label, document.beneficiaryName, DateText.short(document.createdAt)].compactMap { $0 }.joined(separator: " · "))
                    .font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            Spacer()
            if model.openingId == document.id { ProgressView() } else {
                Text(ByteCountFormatter.string(fromByteCount: Int64(document.size), countStyle: .file))
                    .font(.caption2).foregroundStyle(DS.Palette.textSecondary)
            }
        }
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
    }
}

private struct VaultDocumentForm: View {
    let document: PickedDocument
    let initialCategory: VaultCategory
    let onSave: (String, VaultCategory) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var title = ""
    @State private var category: VaultCategory = .prescription

    var body: some View {
        NavigationStack {
            Form {
                if let preview = document.preview {
                    Image(uiImage: preview).resizable().scaledToFit().frame(maxHeight: 180)
                        .accessibilityLabel(Text("Aperçu du document"))
                }
                TextField("Titre (ex. Ordonnance Dr Ndiaye)", text: $title).accessibilityIdentifier("vault.title")
                Picker("Catégorie", selection: $category) {
                    ForEach(VaultCategory.allCases) { Label($0.label, systemImage: $0.symbol).tag($0) }
                }
            }
            .navigationTitle("Nouveau document")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Annuler") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Enregistrer") {
                        onSave(title.trimmingCharacters(in: .whitespaces), category)
                        dismiss()
                    }
                    .disabled(title.trimmingCharacters(in: .whitespaces).isEmpty)
                    .accessibilityIdentifier("vault.save")
                }
            }
            .onAppear { category = initialCategory }
        }
        .presentationDetents([.medium, .large])
    }
}
