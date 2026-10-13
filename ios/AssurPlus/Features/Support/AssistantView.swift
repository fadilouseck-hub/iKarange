import SwiftUI

@MainActor
@Observable
final class AssistantViewModel {
    struct Entry: Identifiable, Equatable {
        enum Kind: Equatable { case user, assistant, failure }
        let id = UUID()
        let kind: Kind
        let text: String
    }

    /// Turns sent back to the server with each question (it keeps no history).
    static let historyLimit = 12
    static let maxLength = 1000

    private let env: AppEnvironment
    private(set) var info: AssistantInfo?
    private(set) var loadError: APIError?
    private(set) var entries: [Entry] = []
    private(set) var isAnswering = false
    var draft = ""

    init(env: AppEnvironment) { self.env = env }

    var canSend: Bool { !isAnswering && !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty }

    func load() async {
        guard info == nil else { return }
        do {
            info = try await env.api.assistantInfo()
            loadError = nil
        } catch {
            let wrapped = APIError.wrap(error)
            // A backend that does not have the assistant yet answers 404 / 501: say so instead of "cannot load".
            if case .server(let status, _, _, _) = wrapped, status == 404 || status == 501 {
                info = AssistantInfo(available: false, suggestions: [], disclaimer: "")
            } else {
                loadError = wrapped
            }
        }
    }

    func send() async {
        let question = String(draft.trimmingCharacters(in: .whitespacesAndNewlines).prefix(Self.maxLength))
        draft = ""
        await ask(question)
    }

    func ask(_ question: String) async {
        guard !question.isEmpty, !isAnswering else { return }
        entries.append(Entry(kind: .user, text: question))
        isAnswering = true
        defer { isAnswering = false }
        do {
            let reply = try await env.api.askAssistant(history)
            entries.append(Entry(kind: .assistant, text: reply.message.content))
        } catch {
            entries.append(Entry(kind: .failure, text: APIError.wrap(error).userMessage))
        }
    }

    /// Answered exchanges plus the pending question; a question whose answer failed is left out.
    private var history: [AssistantMessage] {
        var messages: [AssistantMessage] = []
        for (index, entry) in entries.enumerated() {
            switch entry.kind {
            case .user:
                let next = index + 1 < entries.count ? entries[index + 1].kind : nil
                if next != .failure { messages.append(AssistantMessage(role: .user, content: entry.text)) }
            case .assistant:
                messages.append(AssistantMessage(role: .assistant, content: entry.text))
            case .failure:
                break
            }
        }
        return Array(messages.suffix(Self.historyLimit))
    }
}

struct AssistantView: View {
    /// Opens the feedback form instead (assistant unavailable, or the member wants to reach the team).
    let openFeedback: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            WithModel(AssistantViewModel.init) { model in
                AssistantContent(model: model, openFeedback: openFeedback)
            }
            .navigationTitle("Assistant")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Fermer") { dismiss() } }
            }
        }
    }
}

private struct AssistantContent: View {
    @Bindable var model: AssistantViewModel
    let openFeedback: () -> Void
    @FocusState private var isEditing: Bool
    private let bottomAnchor = "assistant.bottom"

    var body: some View {
        Group {
            if let info = model.info {
                if info.available { chat(info) } else { unavailable }
            } else if let error = model.loadError {
                ErrorStateView(error: error) { Task { await model.load() } }.frame(maxHeight: .infinity)
            } else {
                ProgressView().frame(maxWidth: .infinity, maxHeight: .infinity)
            }
        }
        .screenBackground()
        .task { await model.load() }
    }

    private var unavailable: some View {
        EmptyStateView(
            title: String(localized: "Assistant indisponible", bundle: .appLanguage),
            message: String(localized: "L'assistant n'est pas encore activé. Vous pouvez nous écrire, nous vous lirons avec attention.", bundle: .appLanguage),
            symbol: "bubble.left.and.exclamationmark.bubble.right",
            actionTitle: String(localized: "Envoyer un message", bundle: .appLanguage),
            action: openFeedback)
            .frame(maxHeight: .infinity)
            .accessibilityIdentifier("assistant.unavailable")
    }

    private func chat(_ info: AssistantInfo) -> some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: DS.Spacing.m) {
                    intro(info)
                    ForEach(model.entries) { entry in
                        Bubble(entry: entry).id(entry.id)
                    }
                    if model.isAnswering {
                        HStack(spacing: DS.Spacing.s) {
                            ProgressView()
                            Text("L'assistant rédige sa réponse…").font(.callout).foregroundStyle(DS.Palette.textSecondary)
                        }
                        .accessibilityIdentifier("assistant.answering")
                    }
                    Color.clear.frame(height: 1).id(bottomAnchor)
                }
                .padding(DS.Spacing.l)
            }
            .scrollDismissesKeyboard(.interactively)
            .onChange(of: model.entries.count) { withAnimation { proxy.scrollTo(bottomAnchor, anchor: .bottom) } }
            .onChange(of: model.isAnswering) { withAnimation { proxy.scrollTo(bottomAnchor, anchor: .bottom) } }
            .safeAreaInset(edge: .bottom) { inputBar }
        }
    }

    private func intro(_ info: AssistantInfo) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Label {
                Text("Posez une question sur l'application ou votre couverture.").font(.headline)
            } icon: {
                Image(systemName: "sparkles").foregroundStyle(DS.Palette.accent)
            }
            Text(info.disclaimer).font(.footnote).foregroundStyle(DS.Palette.textSecondary)
            if model.entries.isEmpty {
                ForEach(Array(info.suggestions.enumerated()), id: \.offset) { index, suggestion in
                    Button {
                        Task { await model.ask(suggestion) }
                    } label: {
                        Text(suggestion)
                            .font(.callout)
                            .multilineTextAlignment(.leading)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(DS.Spacing.m)
                            .background(DS.Palette.accentSoft, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                    }
                    .buttonStyle(.plain)
                    .accessibilityIdentifier("assistant.suggestion.\(index)")
                }
            }
        }
        .card()
    }

    private var inputBar: some View {
        HStack(alignment: .bottom, spacing: DS.Spacing.s) {
            TextField("Votre question", text: $model.draft, axis: .vertical)
                .lineLimit(1...4)
                .focused($isEditing)
                .padding(.horizontal, DS.Spacing.m)
                .padding(.vertical, DS.Spacing.s)
                .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
                .overlay(RoundedRectangle(cornerRadius: DS.Radius.l).strokeBorder(DS.Palette.border))
                .accessibilityIdentifier("assistant.input")
            Button {
                Task { await model.send() }
            } label: {
                Image(systemName: "arrow.up.circle.fill").font(.system(size: 34))
            }
            .tint(DS.Palette.accent)
            .disabled(!model.canSend)
            .accessibilityLabel(Text("Envoyer"))
            .accessibilityIdentifier("assistant.send")
        }
        .padding(.horizontal, DS.Spacing.l)
        .padding(.vertical, DS.Spacing.s)
        .background(.bar)
    }
}

private struct Bubble: View {
    let entry: AssistantViewModel.Entry

    var body: some View {
        HStack {
            if entry.kind == .user { Spacer(minLength: DS.Spacing.xxl) }
            Text(entry.text)
                .font(.callout)
                .textSelection(.enabled)
                .foregroundStyle(foreground)
                .padding(DS.Spacing.m)
                .background(background, in: RoundedRectangle(cornerRadius: DS.Radius.l))
                .accessibilityIdentifier("assistant.message.\(entry.kind)")
            if entry.kind != .user { Spacer(minLength: DS.Spacing.xxl) }
        }
    }

    private var foreground: Color {
        switch entry.kind {
        case .user: DS.Palette.onPrimary
        case .assistant: DS.Palette.textPrimary
        case .failure: DS.Palette.danger
        }
    }

    private var background: Color {
        switch entry.kind {
        case .user: DS.Palette.primary
        case .assistant: DS.Palette.surface
        case .failure: DS.Palette.dangerSoft
        }
    }
}
