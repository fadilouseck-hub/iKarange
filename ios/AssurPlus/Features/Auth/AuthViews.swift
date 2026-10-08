import SwiftUI

enum AuthRoute: Hashable { case login, register, forgotPassword }

struct AuthFlowView: View {
    @State private var path: [AuthRoute] = []

    var body: some View {
        NavigationStack(path: $path) {
            WelcomeView(path: $path)
                .navigationDestination(for: AuthRoute.self) { route in
                    switch route {
                    case .login: LoginView(path: $path)
                    case .register: RegisterView()
                    case .forgotPassword: ForgotPasswordView()
                    }
                }
        }
    }
}

struct WelcomeView: View {
    @Binding var path: [AuthRoute]
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        VStack(spacing: DS.Spacing.xl) {
            Spacer()
            BrandMark(size: 72)
            VStack(spacing: DS.Spacing.s) {
                Text("Votre santé, simplement assurée")
                    .font(DS.Typography.title)
                    .multilineTextAlignment(.center)
                Text("Carte tiers-payant, remboursements et réseau de soins dans votre poche.")
                    .font(.callout)
                    .foregroundStyle(DS.Palette.textSecondary)
                    .multilineTextAlignment(.center)
            }
            VStack(alignment: .leading, spacing: DS.Spacing.m) {
                FeatureLine(symbol: "qrcode", text: "Présentez votre carte digitale chez le prestataire")
                FeatureLine(symbol: "camera.viewfinder", text: "Déclarez un sinistre en photographiant la facture")
                FeatureLine(symbol: "map", text: "Trouvez une pharmacie ou une clinique conventionnée")
            }
            .card()
            Spacer()
            VStack(spacing: DS.Spacing.m) {
                Button("Se connecter") { path.append(.login) }
                    .buttonStyle(.primary)
                    .accessibilityIdentifier("welcome.login")
                Button("Créer un compte") { path.append(.register) }
                    .buttonStyle(.secondary)
                    .accessibilityIdentifier("welcome.register")
            }
            Text("Copyright MCE Group").font(.caption).foregroundStyle(DS.Palette.textSecondary)
        }
        .padding(DS.Spacing.xl)
        .screenBackground()
        .toolbar(.hidden, for: .navigationBar)
    }
}

private struct FeatureLine: View {
    let symbol: String
    let text: String

    var body: some View {
        HStack(spacing: DS.Spacing.m) {
            Image(systemName: symbol)
                .frame(width: 28)
                .foregroundStyle(DS.Palette.accent)
                .accessibilityHidden(true)
            Text(text).font(.callout)
        }
    }
}

/// `+221` prefix and formatted national number.
struct PhoneField: View {
    @Binding var phone: String
    var error: String?
    /// Raw field text, reformatted on every change (a transforming Binding would not refresh the field
    /// once the formatted value stops changing, letting extra digits show).
    @State private var text = ""

    var body: some View {
        LabeledField(label: String(localized: "Numéro de téléphone"), error: error) {
            HStack(spacing: DS.Spacing.s) {
                Text("🇸🇳 +221").foregroundStyle(DS.Palette.textSecondary).accessibilityHidden(true)
                TextField("77 123 45 67", text: $text)
                    .onAppear { text = phone }
                    .onChange(of: text) { _, newValue in
                        let formatted = PhoneNumber.formatInput(newValue)
                        if formatted != newValue { text = formatted }
                        phone = formatted
                    }
                    .keyboardType(.phonePad)
                    .textContentType(.telephoneNumber)
                    .accessibilityLabel(Text("Numéro de téléphone, indicatif plus 221"))
                    .accessibilityIdentifier("auth.phone")
            }
        }
    }
}

struct LoginView: View {
    @Binding var path: [AuthRoute]

    var body: some View {
        WithModel({ LoginViewModel(api: $0.api, session: $0.session) }) { model in
            LoginForm(model: model, path: $path)
        }
        .navigationTitle("Connexion")
        .navigationBarTitleDisplayMode(.inline)
        .screenBackground()
    }
}

private struct LoginForm: View {
    @Bindable var model: LoginViewModel
    @Binding var path: [AuthRoute]

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: DS.Spacing.l) {
                Text("Heureux de vous revoir").font(DS.Typography.title)
                Picker("Méthode", selection: $model.mode) {
                    ForEach(LoginViewModel.Mode.allCases) { Text($0.label).tag($0) }
                }
                .pickerStyle(.segmented)

                PhoneField(phone: $model.otp.phone, error: model.error?.fieldErrors["phone"])

                if model.mode == .password {
                    LabeledField(label: String(localized: "Mot de passe")) {
                        SecureField("Votre mot de passe", text: $model.password)
                            .textContentType(.password)
                            .accessibilityIdentifier("auth.password")
                    }
                    Button("Mot de passe oublié ?") { path.append(.forgotPassword) }
                        .font(.callout.weight(.semibold))
                } else {
                    Text("Nous vous enverrons un code à 6 chiffres par SMS.")
                        .font(.callout).foregroundStyle(DS.Palette.textSecondary)
                }

                if let error = model.error, error.fieldErrors.isEmpty {
                    MessageBanner(message: Message(level: .error, text: error.userMessage))
                }

                Button(model.mode == .password ? "Se connecter" : "Recevoir le code") {
                    Task { await model.submit() }
                }
                .buttonStyle(.primary(loading: model.isLoading))
                .disabled(!model.canSubmit)
                .accessibilityIdentifier("auth.submit")
            }
            .padding(DS.Spacing.xl)
        }
        .scrollDismissesKeyboard(.interactively)
        .navigationDestination(isPresented: $model.showOTPEntry) {
            OTPEntryView(model: model.otp) { await model.completeOTP() }
        }
    }
}

struct OTPEntryView: View {
    @Bindable var model: OTPViewModel
    let onComplete: () async -> Void
    @FocusState private var focused: Bool

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: DS.Spacing.l) {
                Text("Vérification").font(DS.Typography.title)
                Text("Saisissez le code envoyé au \(model.challenge?.maskedPhone ?? PhoneNumber.display(model.phone)).")
                    .font(.callout).foregroundStyle(DS.Palette.textSecondary)

                CodeBoxes(code: model.code)
                    .overlay {
                        TextField("", text: Binding(get: { model.code }, set: { model.setCode($0) }))
                            .keyboardType(.numberPad)
                            .textContentType(.oneTimeCode)
                            .focused($focused)
                            .foregroundStyle(.clear)
                            .tint(.clear)
                            .accessibilityLabel(Text("Code de vérification à 6 chiffres"))
                            .accessibilityIdentifier("auth.otp")
                    }
                    .onTapGesture { focused = true }

                if let error = model.error {
                    Text(error.fieldErrors["code"] ?? error.userMessage).font(.callout).foregroundStyle(DS.Palette.danger)
                }

                Button("Valider") { Task { await onComplete() } }
                    .buttonStyle(.primary(loading: model.isVerifying))
                    .disabled(!model.codeIsComplete || model.isVerifying)
                    .accessibilityIdentifier("auth.otp.submit")

                TimelineView(.periodic(from: .now, by: 1)) { context in
                    let remaining = Int((model.resendAvailableAt ?? .distantPast).timeIntervalSince(context.date).rounded(.up))
                    Button(remaining > 0 ? "Renvoyer le code (\(remaining) s)" : "Renvoyer le code") {
                        Task { await model.send() }
                    }
                    .disabled(remaining > 0 || model.isSending)
                    .font(.callout.weight(.semibold))
                    .frame(maxWidth: .infinity)
                }
            }
            .padding(DS.Spacing.xl)
        }
        .screenBackground()
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { focused = true }
        .onChange(of: model.code) { _, code in
            if code.count == 6 { Task { await onComplete() } }
        }
    }
}

private struct CodeBoxes: View {
    let code: String

    var body: some View {
        HStack(spacing: DS.Spacing.s) {
            ForEach(0..<6, id: \.self) { index in
                let digit = index < code.count ? String(Array(code)[index]) : ""
                Text(digit)
                    .font(.title2.monospacedDigit().weight(.semibold))
                    .frame(maxWidth: .infinity, minHeight: 56)
                    .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                    .overlay(RoundedRectangle(cornerRadius: DS.Radius.m)
                        .strokeBorder(index == code.count ? DS.Palette.accent : DS.Palette.border, lineWidth: index == code.count ? 2 : 1))
            }
        }
        .accessibilityHidden(true)
    }
}

struct RegisterView: View {
    var body: some View {
        WithModel({ RegisterViewModel(api: $0.api, session: $0.session) }) { model in
            RegisterSteps(model: model)
        }
        .navigationTitle("Créer un compte")
        .navigationBarTitleDisplayMode(.inline)
        .screenBackground()
    }
}

private struct RegisterSteps: View {
    @Bindable var model: RegisterViewModel
    @Environment(\.openURL) private var openURL

    var body: some View {
        switch model.step {
        case .phone:
            ScrollView {
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    StepProgress(current: 1, total: 3, title: String(localized: "Votre numéro"))
                    Text("Votre numéro de téléphone servira d'identifiant. Nous allons le vérifier par SMS.")
                        .font(.callout).foregroundStyle(DS.Palette.textSecondary)
                    PhoneField(phone: $model.otp.phone, error: model.otp.error?.fieldErrors["phone"])
                    if let error = model.otp.error, error.fieldErrors.isEmpty {
                        MessageBanner(message: Message(level: .error, text: error.userMessage))
                    }
                    Button("Recevoir le code") { Task { await model.sendCode() } }
                        .buttonStyle(.primary(loading: model.otp.isSending))
                        .disabled(!model.otp.phoneIsValid || model.otp.isSending)
                        .accessibilityIdentifier("register.sendCode")
                }
                .padding(DS.Spacing.xl)
            }
        case .otp:
            OTPEntryView(model: model.otp) { await model.verifyCode() }
        case .details:
            detailsForm
        }
    }

    private var detailsForm: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: DS.Spacing.l) {
                StepProgress(current: 3, total: 3, title: String(localized: "Vos informations"))
                LabeledField(label: String(localized: "Prénom"), error: model.error?.fieldErrors["firstName"]) {
                    TextField("Prénom", text: $model.firstName).textContentType(.givenName)
                        .accessibilityIdentifier("register.firstName")
                }
                LabeledField(label: String(localized: "Nom"), error: model.error?.fieldErrors["lastName"]) {
                    TextField("Nom", text: $model.lastName).textContentType(.familyName)
                        .accessibilityIdentifier("register.lastName")
                }
                LabeledField(label: String(localized: "Date de naissance")) {
                    DatePicker("Date de naissance", selection: $model.birthDate, in: ...Date.now, displayedComponents: .date)
                        .labelsHidden()
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
                Picker("Sexe", selection: $model.gender) {
                    ForEach(Gender.allCases) { Text($0.label).tag($0) }
                }
                .pickerStyle(.segmented)
                LabeledField(label: String(localized: "E-mail (facultatif)"), error: model.emailError) {
                    TextField("nom@exemple.com", text: $model.email)
                        .keyboardType(.emailAddress).textContentType(.emailAddress).textInputAutocapitalization(.never)
                }
                LabeledField(label: String(localized: "Ville")) {
                    TextField("Dakar", text: $model.city).textContentType(.addressCity)
                }

                Toggle("Créer un mot de passe", isOn: $model.usePassword)
                    .tint(DS.Palette.accent)
                    .accessibilityIdentifier("register.usePassword")
                if model.usePassword {
                    LabeledField(label: String(localized: "Mot de passe (8 caractères min.)"), error: model.passwordError) {
                        SecureField("Mot de passe", text: $model.password).textContentType(.newPassword)
                            .accessibilityIdentifier("register.password")
                    }
                    LabeledField(label: String(localized: "Confirmation")) {
                        SecureField("Confirmez", text: $model.passwordConfirmation).textContentType(.newPassword)
                            .accessibilityIdentifier("register.passwordConfirmation")
                    }
                } else {
                    Text("Vous vous connecterez avec un code reçu par SMS.")
                        .font(.footnote).foregroundStyle(DS.Palette.textSecondary)
                }

                Toggle(isOn: $model.acceptedCGU) {
                    VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                        Text("J'accepte les conditions générales d'utilisation")
                        if let cgu = model.cgu {
                            Button("Lire les CGU (version \(cgu.version))") { openURL(cgu.url) }
                                .font(.footnote.weight(.semibold))
                        }
                    }
                }
                .tint(DS.Palette.accent)
                .accessibilityIdentifier("register.cgu")

                if let error = model.error, error.fieldErrors.isEmpty {
                    MessageBanner(message: Message(level: .error, text: error.userMessage))
                }
                Button("Créer mon compte") { Task { await model.submit() } }
                    .buttonStyle(.primary(loading: model.isLoading))
                    .disabled(!model.canSubmitDetails)
                    .accessibilityIdentifier("register.submit")
            }
            .padding(DS.Spacing.xl)
        }
        .scrollDismissesKeyboard(.interactively)
    }
}

struct ForgotPasswordView: View {
    var body: some View {
        WithModel({ ForgotPasswordViewModel(api: $0.api, session: $0.session) }) { model in
            ForgotSteps(model: model)
        }
        .navigationTitle("Mot de passe oublié")
        .navigationBarTitleDisplayMode(.inline)
        .screenBackground()
    }
}

private struct ForgotSteps: View {
    @Bindable var model: ForgotPasswordViewModel

    var body: some View {
        switch model.step {
        case .phone:
            ScrollView {
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    Text("Saisissez votre numéro : vous recevrez un code pour choisir un nouveau mot de passe.")
                        .font(.callout).foregroundStyle(DS.Palette.textSecondary)
                    PhoneField(phone: $model.otp.phone, error: model.otp.error?.fieldErrors["phone"])
                    if let error = model.otp.error, error.fieldErrors.isEmpty {
                        MessageBanner(message: Message(level: .error, text: error.userMessage))
                    }
                    Button("Recevoir le code") { Task { await model.sendCode() } }
                        .buttonStyle(.primary(loading: model.otp.isSending))
                        .disabled(!model.otp.phoneIsValid)
                }
                .padding(DS.Spacing.xl)
            }
        case .otp:
            OTPEntryView(model: model.otp) { await model.verifyCode() }
        case .newPassword:
            ScrollView {
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    LabeledField(label: String(localized: "Nouveau mot de passe (8 caractères min.)")) {
                        SecureField("Mot de passe", text: $model.password).textContentType(.newPassword)
                    }
                    LabeledField(label: String(localized: "Confirmation"), error: !model.confirmation.isEmpty && model.confirmation != model.password ? String(localized: "Les mots de passe ne correspondent pas.") : nil) {
                        SecureField("Confirmez", text: $model.confirmation).textContentType(.newPassword)
                    }
                    if let error = model.error {
                        MessageBanner(message: Message(level: .error, text: error.userMessage))
                    }
                    Button("Enregistrer et se connecter") { Task { await model.save() } }
                        .buttonStyle(.primary(loading: model.isLoading))
                        .disabled(!model.canSave)
                }
                .padding(DS.Spacing.xl)
            }
        }
    }
}
