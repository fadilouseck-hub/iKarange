import PhotosUI
import SwiftUI

@MainActor
@Observable
final class ProfileViewModel {
    private let env: AppEnvironment
    private(set) var legal: [LegalDocument] = []
    private(set) var isUploadingPhoto = false
    private(set) var deletionStatus: ServerStatus?
    var message: Message?

    init(env: AppEnvironment) { self.env = env }

    var user: Me? { env.session.user }

    func loadLegal() async {
        if legal.isEmpty { legal = (try? await env.api.legalDocuments()) ?? [] }
    }

    func setPhoto(_ data: Data) async {
        guard let jpeg = ImageCompressor.jpeg(from: data) else { return }
        isUploadingPhoto = true
        defer { isUploadingPhoto = false }
        do {
            let uploadId = try await env.uploader.upload(UploadFile(data: jpeg, fileName: "photo.jpg", mimeType: "image/jpeg"), purpose: "profile_photo") { _ in }
            env.session.updateUser(try await env.api.setPhoto(uploadId: uploadId))
            message = Message(level: .success, text: String(localized: "Photo mise à jour. Elle apparaîtra sur votre carte après validation.", bundle: .appLanguage))
        } catch {
            message = Message(level: .error, text: APIError.wrap(error).userMessage)
        }
    }

    func setBiometricLock(_ enabled: Bool) async {
        if enabled {
            guard env.biometrics.availableKind != .none || env.isMock else {
                message = Message(level: .warning, text: String(localized: "Aucune biométrie configurée sur cet appareil.", bundle: .appLanguage))
                return
            }
            guard await env.biometrics.authenticate(reason: String(localized: "Activer le déverrouillage biométrique", bundle: .appLanguage)) else { return }
        }
        env.settings.biometricLockEnabled = enabled
    }

    func requestDeletion(reason: String) async {
        do {
            deletionStatus = try await env.api.requestAccountDeletion(reason: reason.isEmpty ? nil : reason)
        } catch {
            message = Message(level: .error, text: APIError.wrap(error).userMessage)
        }
    }
}

struct ProfileView: View {
    var body: some View {
        WithModel(ProfileViewModel.init) { model in
            ProfileContent(model: model)
        }
        .navigationTitle("Profil")
    }
}

private struct ProfileContent: View {
    @Bindable var model: ProfileViewModel
    @Environment(AppEnvironment.self) private var env
    @Environment(\.openURL) private var openURL
    @State private var photoItem: PhotosPickerItem?
    @State private var sheet: Sheet?
    @State private var confirmLogout = false
    @State private var confirmDeletion = false
    @State private var deletionReason = ""

    enum Sheet: String, Identifiable {
        case contact, password, notifications
        var id: String { rawValue }
    }

    var body: some View {
        List {
            if let user = model.user { header(user) }
            if let message = model.message {
                MessageBanner(message: message).listRowInsets(EdgeInsets()).listRowBackground(Color.clear)
            }

            Section("Mon compte") {
                Button { sheet = .contact } label: { Label("Coordonnées", systemImage: "person.text.rectangle") }
                    .disabled(!env.session.can(.profileEdit))
                if env.session.can(.familyView) {
                    NavigationLink(value: Route.family) { Label("Ma famille", systemImage: "person.3") }
                }
                if env.session.can(.paymentsView) {
                    NavigationLink(value: Route.payments) { Label("Paiements", systemImage: "creditcard") }
                }
                if env.session.can(.vaultView) {
                    NavigationLink(value: Route.vault) { Label("Coffre santé", systemImage: "lock.doc") }
                }
            }

            Section("Sécurité") {
                Toggle(isOn: Binding(get: { env.settings.biometricLockEnabled }, set: { value in Task { await model.setBiometricLock(value) } })) {
                    Label("Verrouiller avec \(env.biometrics.availableKind.label)", systemImage: env.biometrics.availableKind.symbol)
                }
                .tint(DS.Palette.accent)
                .accessibilityIdentifier("profile.biometrics")
                Button { sheet = .password } label: { Label("Changer le mot de passe", systemImage: "key") }
            }

            Section("Préférences") {
                Button { sheet = .notifications } label: { Label("Notifications", systemImage: "bell.badge") }
                Picker(selection: Binding(get: { env.language.code }, set: { code in
                    env.language.select(code)
                    // Server-side preference: SMS, e-mails and push content in the same language.
                    Task { if let me = try? await env.api.updateMe(MeUpdate(preferredLanguage: code)) { env.session.updateUser(me) } }
                })) {
                    ForEach(env.language.supported, id: \.self) { code in
                        Text(verbatim: LanguageSettings.nativeName(code)).tag(code)
                    }
                } label: {
                    Label("Langue", systemImage: "globe")
                }
                .accessibilityIdentifier("profile.language")
                Picker(selection: Binding(get: { env.settings.appearance }, set: { env.settings.appearance = $0 })) {
                    ForEach(Appearance.allCases) { Text($0.title).tag($0) }
                } label: {
                    Label("Apparence", systemImage: "circle.lefthalf.filled")
                }
                .accessibilityIdentifier("profile.appearance")
            }

            Section("Aide et informations") {
                ForEach(model.legal) { document in
                    Button { openURL(document.url) } label: {
                        Label("\(document.title) (\(document.version))", systemImage: "doc.plaintext")
                    }
                }
                if model.legal.isEmpty {
                    if let url = Tenant.current.termsOfUseURL {
                        Button { openURL(url) } label: { Label("Conditions générales d'utilisation", systemImage: "doc.plaintext") }
                    }
                    if let url = Tenant.current.privacyURL {
                        Button { openURL(url) } label: { Label("Politique de confidentialité", systemImage: "hand.raised") }
                    }
                }
                if let url = Tenant.current.supportPhoneURL {
                    Button { openURL(url) } label: { Label("Appeler le support", systemImage: "phone") }
                }
                if let url = Tenant.current.supportEmailURL {
                    Button { openURL(url) } label: { Label("Écrire au support", systemImage: "envelope") }
                }
            }

            Section {
                Button("Se déconnecter") { confirmLogout = true }
                    .accessibilityIdentifier("profile.logout")
                if let status = model.deletionStatus {
                    StatusBadge(status)
                } else {
                    Button("Supprimer mon compte", role: .destructive) { confirmDeletion = true }
                }
            } footer: {
                Text(verbatim: "\(Tenant.current.displayName) \(AppConfig.version) · Copyright © \(Tenant.current.copyrightHolder)")
            }
        }
        .scrollContentBackground(.hidden)
        .screenBackground()
        .tint(DS.Palette.textPrimary)
        .task { await model.loadLegal() }
        .onChange(of: photoItem) { _, item in
            guard let item else { return }
            Task {
                if let data = try? await item.loadTransferable(type: Data.self) { await model.setPhoto(data) }
                photoItem = nil
            }
        }
        .sheet(item: $sheet) { sheet in
            switch sheet {
            case .contact: ContactForm()
            case .password: PasswordChangeForm()
            case .notifications: NotificationPreferencesForm()
            }
        }
        .confirmationDialog("Se déconnecter ?", isPresented: $confirmLogout, titleVisibility: .visible) {
            Button("Se déconnecter", role: .destructive) { Task { await env.session.logout() } }
                .accessibilityIdentifier("profile.logout.confirm")
        } message: {
            Text("Les données enregistrées sur cet appareil seront effacées.")
        }
        .alert("Supprimer mon compte", isPresented: $confirmDeletion) {
            TextField("Motif (facultatif)", text: $deletionReason)
            Button("Annuler", role: .cancel) {}
            Button("Demander la suppression", role: .destructive) { Task { await model.requestDeletion(reason: deletionReason) } }
        } message: {
            Text("Votre demande sera traitée selon la réglementation. Les données liées à un contrat en cours peuvent être conservées pendant la durée légale.")
        }
    }

    private func header(_ user: Me) -> some View {
        let isUploading = model.isUploadingPhoto
        let canEditPhoto = env.session.can(.profileEdit)
        return HStack(spacing: DS.Spacing.l) {
            PhotosPicker(selection: $photoItem, matching: .images) {
                ZStack(alignment: .bottomTrailing) {
                    if let url = user.photoURL {
                        AsyncImage(url: url) { $0.resizable().scaledToFill() } placeholder: { InitialsAvatar(name: user.fullName, size: 72) }
                            .frame(width: 72, height: 72).clipShape(Circle())
                    } else {
                        InitialsAvatar(name: user.fullName, size: 72)
                    }
                    if canEditPhoto {
                    Image(systemName: isUploading ? "arrow.up.circle.fill" : "camera.circle.fill")
                        .font(.title3)
                        .foregroundStyle(DS.Palette.accent)
                        .background(Circle().fill(DS.Palette.surface))
                    }
                }
            }
            .accessibilityLabel(Text("Changer la photo de profil"))
            .disabled(!canEditPhoto)
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                Text(user.fullName).font(.title3.weight(.bold))
                Text(PhoneNumber.display(user.phone)).font(.callout).foregroundStyle(DS.Palette.textSecondary)
                if let member = user.memberNumber {
                    Text("N° \(member)").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                }
                (user.role == .principal ? Text("Assuré principal") : Text("Ayant droit")).font(.caption.weight(.semibold)).foregroundStyle(DS.Palette.accent)
            }
        }
        .listRowBackground(Color.clear)
        .listRowInsets(EdgeInsets(top: DS.Spacing.s, leading: 0, bottom: DS.Spacing.s, trailing: 0))
    }
}

enum AppConfig {
    static var version: String {
        let info = Bundle.main.infoDictionary
        return "\(info?["CFBundleShortVersionString"] as? String ?? "1.0") (\(info?["CFBundleVersion"] as? String ?? "1"))"
    }
}

private struct ContactForm: View {
    @Environment(AppEnvironment.self) private var env
    @Environment(\.dismiss) private var dismiss
    @State private var email = ""
    @State private var address = ""
    @State private var city = ""
    @State private var error: APIError?
    @State private var isSaving = false

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    LabeledContent("Téléphone", value: PhoneNumber.display(env.session.user?.phone ?? ""))
                } footer: {
                    Text("Pour changer de numéro, contactez le support.")
                }
                TextField("E-mail", text: $email).keyboardType(.emailAddress).textInputAutocapitalization(.never).textContentType(.emailAddress)
                TextField("Adresse", text: $address).textContentType(.fullStreetAddress)
                TextField("Ville", text: $city).textContentType(.addressCity)
                if let error { Text(error.fieldErrors.values.first ?? error.userMessage).foregroundStyle(DS.Palette.danger) }
            }
            .navigationTitle("Coordonnées")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Annuler") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Enregistrer") {
                        Task {
                            isSaving = true
                            defer { isSaving = false }
                            do {
                                env.session.updateUser(try await env.api.updateMe(MeUpdate(email: email, address: address, city: city)))
                                dismiss()
                            } catch { self.error = .wrap(error) }
                        }
                    }
                    .disabled(isSaving)
                }
            }
            .onAppear {
                email = env.session.user?.email ?? ""
                address = env.session.user?.address ?? ""
                city = env.session.user?.city ?? ""
            }
        }
    }
}

private struct PasswordChangeForm: View {
    @Environment(AppEnvironment.self) private var env
    @Environment(\.dismiss) private var dismiss
    @State private var current = ""
    @State private var new = ""
    @State private var confirmation = ""
    @State private var error: APIError?
    @State private var done = false

    var body: some View {
        NavigationStack {
            Form {
                SecureField("Mot de passe actuel", text: $current).textContentType(.password)
                SecureField("Nouveau mot de passe (8 caractères min.)", text: $new).textContentType(.newPassword)
                SecureField("Confirmation", text: $confirmation).textContentType(.newPassword)
                if !confirmation.isEmpty && confirmation != new {
                    Text("Les mots de passe ne correspondent pas.").foregroundStyle(DS.Palette.danger)
                }
                if let error { Text(error.fieldErrors.values.first ?? error.userMessage).foregroundStyle(DS.Palette.danger) }
                if done { Text("Mot de passe modifié.").foregroundStyle(DS.Palette.success) }
            }
            .navigationTitle("Mot de passe")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Enregistrer") {
                        Task {
                            do {
                                try await env.api.changePassword(PasswordChangeRequest(currentPassword: current, newPassword: new))
                                done = true
                                error = nil
                            } catch { self.error = .wrap(error) }
                        }
                    }
                    .disabled(current.isEmpty || new.count < 8 || new != confirmation)
                }
            }
        }
    }
}

private struct NotificationPreferencesForm: View {
    @Environment(AppEnvironment.self) private var env
    @Environment(\.dismiss) private var dismiss
    @State private var preferences: NotificationPreferences?
    @State private var error: APIError?

    var body: some View {
        NavigationStack {
            Form {
                if var prefs = preferences {
                    ForEach(prefs.categories.indices, id: \.self) { index in
                        Section(prefs.categories[index].label) {
                            Toggle("Notification push", isOn: Binding(get: { prefs.categories[index].push }, set: { prefs.categories[index].push = $0; preferences = prefs }))
                            Toggle("SMS", isOn: Binding(get: { prefs.categories[index].sms }, set: { prefs.categories[index].sms = $0; preferences = prefs }))
                            Toggle("E-mail", isOn: Binding(get: { prefs.categories[index].email }, set: { prefs.categories[index].email = $0; preferences = prefs }))
                        }
                    }
                } else if let error {
                    Text(error.userMessage)
                } else {
                    ProgressView()
                }
            }
            .tint(DS.Palette.accent)
            .navigationTitle("Notifications")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Annuler") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Enregistrer") {
                        guard let preferences else { return }
                        Task {
                            do {
                                _ = try await env.api.updateNotificationPreferences(preferences)
                                dismiss()
                            } catch { self.error = .wrap(error) }
                        }
                    }
                    .disabled(preferences == nil)
                }
            }
            .task {
                do { preferences = try await env.api.notificationPreferences() } catch { self.error = .wrap(error) }
            }
        }
    }
}
