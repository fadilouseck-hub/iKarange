import SwiftUI
import UIKit

extension FeedbackCategory {
    var title: String {
        switch self {
        case .information: String(localized: "Information", bundle: .appLanguage)
        case .suggestion: String(localized: "Suggestion", bundle: .appLanguage)
        case .bug: String(localized: "Problème technique", bundle: .appLanguage)
        case .other: String(localized: "Autre", bundle: .appLanguage)
        }
    }

    var symbol: String {
        switch self {
        case .information: "info.circle"
        case .suggestion: "lightbulb"
        case .bug: "ladybug"
        case .other: "ellipsis.bubble"
        }
    }
}

@MainActor
@Observable
final class FeedbackViewModel {
    static let maxLength = 4000
    static let minLength = 5

    private let env: AppEnvironment
    var category: FeedbackCategory = .suggestion
    var message = ""
    private(set) var isSending = false
    private(set) var receipt: FeedbackReceipt?
    private(set) var error: APIError?

    init(env: AppEnvironment) { self.env = env }

    private var trimmed: String { message.trimmingCharacters(in: .whitespacesAndNewlines) }

    var canSend: Bool { !isSending && trimmed.count >= Self.minLength && trimmed.count <= Self.maxLength }

    var errorText: String? { error.map { $0.fieldErrors.values.first ?? $0.userMessage } }

    func send() async {
        guard canSend else { return }
        isSending = true
        defer { isSending = false }
        do {
            receipt = try await env.api.sendFeedback(FeedbackRequest(
                category: category, message: trimmed, platform: "ios", appVersion: AppConfig.version,
                osVersion: UIDevice.current.systemVersion, deviceModel: Self.deviceModel, locale: env.language.code))
            error = nil
        } catch {
            self.error = .wrap(error)
        }
    }

    /// Hardware identifier ("iPhone16,2"): helps reproduce a bug, identifies no one.
    static var deviceModel: String {
        var info = utsname()
        uname(&info)
        return withUnsafeBytes(of: &info.machine) { String(decoding: $0.prefix { $0 != 0 }, as: UTF8.self) }
    }
}

struct FeedbackView: View {
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            WithModel(FeedbackViewModel.init) { model in
                FeedbackContent(model: model)
            }
            .navigationTitle("Votre avis")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } }
            }
        }
    }
}

private struct FeedbackContent: View {
    @Bindable var model: FeedbackViewModel
    @Environment(\.dismiss) private var dismiss
    @FocusState private var isEditing: Bool

    var body: some View {
        if model.receipt != nil {
            EmptyStateView(
                title: String(localized: "Merci, message envoyé", bundle: .appLanguage),
                message: String(localized: "Notre équipe le lira avec attention. Pour une demande urgente sur votre dossier, contactez votre gestionnaire.", bundle: .appLanguage),
                symbol: "checkmark.seal.fill",
                actionTitle: String(localized: "Fermer", bundle: .appLanguage)
            ) { dismiss() }
            .frame(maxHeight: .infinity)
            .screenBackground()
            .accessibilityIdentifier("feedback.sent")
        } else {
            Form {
                Section("Type de message") {
                    Picker("Type de message", selection: $model.category) {
                        ForEach(FeedbackCategory.allCases) { category in
                            Label(category.title, systemImage: category.symbol).tag(category)
                        }
                    }
                    .pickerStyle(.inline)
                    .labelsHidden()
                    .accessibilityIdentifier("feedback.category")
                }
                Section {
                    TextField("Décrivez votre remarque, votre idée ou le problème rencontré…", text: $model.message, axis: .vertical)
                        .lineLimit(5...12)
                        .focused($isEditing)
                        .accessibilityIdentifier("feedback.message")
                } header: {
                    Text("Votre message")
                } footer: {
                    VStack(alignment: .leading, spacing: DS.Spacing.xs) {
                        Text("\(model.message.count) / \(FeedbackViewModel.maxLength)")
                            .foregroundStyle(model.message.count > FeedbackViewModel.maxLength ? DS.Palette.danger : DS.Palette.textSecondary)
                        Text("Envoyé avec la version de l'application, le modèle de l'appareil et la langue, pour nous aider à comprendre. N'indiquez pas d'informations médicales.")
                    }
                }
                if let text = model.errorText {
                    Text(text).foregroundStyle(DS.Palette.danger)
                }
            }
            // In the navigation bar so it stays reachable above the keyboard.
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    if model.isSending {
                        ProgressView()
                    } else {
                        Button("Envoyer") {
                            isEditing = false
                            Task { await model.send() }
                        }
                        .disabled(!model.canSend)
                        .accessibilityIdentifier("feedback.send")
                    }
                }
            }
            .scrollDismissesKeyboard(.interactively)
        }
    }
}
