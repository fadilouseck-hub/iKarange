import SwiftUI

struct EmptyStateView: View {
    let title: String
    let message: String
    var symbol = "tray"
    var actionTitle: String?
    var action: (() -> Void)?

    var body: some View {
        VStack(spacing: DS.Spacing.m) {
            Image(systemName: symbol)
                .font(.system(size: 40))
                .foregroundStyle(DS.Palette.accent)
                .accessibilityHidden(true)
            Text(title).font(.headline).multilineTextAlignment(.center)
            Text(message).font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            if let actionTitle, let action {
                Button(actionTitle, action: action).buttonStyle(.secondary).frame(maxWidth: 260)
            }
        }
        .padding(DS.Spacing.xl)
        .frame(maxWidth: .infinity)
    }
}

struct ErrorStateView: View {
    let error: APIError
    let retry: () -> Void

    var body: some View {
        VStack(spacing: DS.Spacing.m) {
            Image(systemName: error == .offline ? "wifi.slash" : "exclamationmark.triangle")
                .font(.system(size: 40))
                .foregroundStyle(DS.Palette.warning)
                .accessibilityHidden(true)
            Text(error == .offline ? "Vous êtes hors ligne" : "Impossible de charger")
                .font(.headline)
            Text(error.userMessage).font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            Button("Réessayer", action: retry).buttonStyle(.secondary).frame(maxWidth: 220)
        }
        .padding(DS.Spacing.xl)
        .frame(maxWidth: .infinity)
    }
}

/// Non-blocking strip shown above cached content when a refresh failed.
struct StaleDataBanner: View {
    let error: APIError
    let retry: () -> Void

    var body: some View {
        HStack(spacing: DS.Spacing.s) {
            Image(systemName: error == .offline ? "wifi.slash" : "exclamationmark.arrow.circlepath")
            Text(error == .offline ? "Hors ligne — données enregistrées" : "Mise à jour impossible")
                .font(.footnote.weight(.medium))
            Spacer()
            Button("Réessayer", action: retry).font(.footnote.weight(.semibold))
        }
        .padding(.horizontal, DS.Spacing.m)
        .padding(.vertical, DS.Spacing.s)
        .foregroundStyle(DS.Palette.warning)
        .background(DS.Palette.warningSoft, in: RoundedRectangle(cornerRadius: DS.Radius.m))
    }
}

/// Grey placeholder blocks with a gentle pulse, used instead of blocking spinners.
struct SkeletonBlock: View {
    var height: CGFloat = 16
    var width: CGFloat?
    @State private var dimmed = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        RoundedRectangle(cornerRadius: DS.Radius.s)
            .fill(DS.Palette.surfaceMuted)
            .frame(width: width, height: height)
            .opacity(dimmed ? 0.5 : 1)
            .onAppear {
                guard !reduceMotion else { return }
                withAnimation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true)) { dimmed = true }
            }
    }
}

struct SkeletonCard: View {
    var lines = 3

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            SkeletonBlock(height: 20, width: 160)
            ForEach(0..<lines, id: \.self) { index in
                SkeletonBlock(height: 14, width: index == lines - 1 ? 120 : nil)
            }
        }
        .card()
        .accessibilityElement()
        .accessibilityLabel(Text("Chargement"))
    }
}

/// Standard handling of "cached value + refresh state": skeleton on first load, full-screen error only
/// when nothing is cached, otherwise content with a stale banner.
struct LoadableContent<Value, Content: View, Placeholder: View>: View {
    let value: Value?
    let isLoading: Bool
    let error: APIError?
    let retry: () -> Void
    @ViewBuilder var content: (Value) -> Content
    @ViewBuilder var placeholder: () -> Placeholder

    var body: some View {
        if let value {
            VStack(spacing: DS.Spacing.m) {
                if let error, !isLoading { StaleDataBanner(error: error, retry: retry) }
                content(value)
            }
        } else if let error, !isLoading {
            ErrorStateView(error: error, retry: retry)
        } else {
            placeholder()
        }
    }
}
