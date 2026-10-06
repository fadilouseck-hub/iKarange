import SwiftUI

// MARK: - Buttons

struct PrimaryButtonStyle: ButtonStyle {
    @Environment(\.isEnabled) private var isEnabled
    var isLoading = false

    func makeBody(configuration: Configuration) -> some View {
        HStack(spacing: DS.Spacing.s) {
            if isLoading { ProgressView().tint(DS.Palette.onPrimary) }
            configuration.label
        }
        .font(.headline)
        .frame(maxWidth: .infinity, minHeight: 50)
        .padding(.horizontal, DS.Spacing.l)
        .foregroundStyle(DS.Palette.onPrimary)
        .background(DS.Palette.primary.opacity(isEnabled ? 1 : 0.4), in: RoundedRectangle(cornerRadius: DS.Radius.m))
        .opacity(configuration.isPressed ? 0.85 : 1)
    }
}

struct SecondaryButtonStyle: ButtonStyle {
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .frame(maxWidth: .infinity, minHeight: 50)
            .padding(.horizontal, DS.Spacing.l)
            .foregroundStyle(DS.Palette.primary)
            .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.m))
            .overlay(RoundedRectangle(cornerRadius: DS.Radius.m).strokeBorder(DS.Palette.primary.opacity(0.35)))
            .opacity(isEnabled ? (configuration.isPressed ? 0.75 : 1) : 0.4)
    }
}

extension ButtonStyle where Self == PrimaryButtonStyle {
    static var primary: PrimaryButtonStyle { PrimaryButtonStyle() }
    static func primary(loading: Bool) -> PrimaryButtonStyle { PrimaryButtonStyle(isLoading: loading) }
}

extension ButtonStyle where Self == SecondaryButtonStyle {
    static var secondary: SecondaryButtonStyle { SecondaryButtonStyle() }
}

// MARK: - Card

struct CardModifier: ViewModifier {
    var padding: CGFloat = DS.Spacing.l

    func body(content: Content) -> some View {
        content
            .padding(padding)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
            .overlay(RoundedRectangle(cornerRadius: DS.Radius.l).strokeBorder(DS.Palette.border.opacity(0.6)))
    }
}

extension View {
    func card(padding: CGFloat = DS.Spacing.l) -> some View { modifier(CardModifier(padding: padding)) }

    /// Standard screen background.
    func screenBackground() -> some View {
        background(DS.Palette.background.ignoresSafeArea())
    }
}

// MARK: - Status badge

struct StatusBadge: View {
    let text: String
    let tone: StatusTone
    var symbol: String?

    var body: some View {
        HStack(spacing: DS.Spacing.xs) {
            if let symbol { Image(systemName: symbol).imageScale(.small) }
            Text(text)
        }
        .font(.caption.weight(.semibold))
        .padding(.horizontal, DS.Spacing.s)
        .padding(.vertical, DS.Spacing.xs)
        .foregroundStyle(tone.foreground)
        .background(tone.background, in: Capsule())
        .accessibilityElement(children: .combine)
        .accessibilityLabel(Text("Statut : \(text)"))
    }
}

extension StatusBadge {
    init(_ status: ServerStatus) { self.init(text: status.label, tone: status.tone) }
    init(_ status: ClaimStatus) { self.init(text: status.label, tone: status.tone, symbol: status.symbol) }
}

// MARK: - Rows

struct InfoRow: View {
    let label: String
    let value: String
    var emphasized = false

    var body: some View {
        ViewThatFits(in: .horizontal) {
            HStack(alignment: .firstTextBaseline) {
                Text(label).foregroundStyle(DS.Palette.textSecondary)
                Spacer(minLength: DS.Spacing.m)
                Text(value)
                    .multilineTextAlignment(.trailing)
                    .fontWeight(emphasized ? .semibold : .regular)
                    .foregroundStyle(DS.Palette.textPrimary)
            }
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                Text(label).foregroundStyle(DS.Palette.textSecondary)
                Text(value).fontWeight(emphasized ? .semibold : .regular).foregroundStyle(DS.Palette.textPrimary)
            }
        }
        .font(.callout)
        .accessibilityElement(children: .combine)
    }
}

struct SectionHeader: View {
    let title: String
    var actionTitle: String?
    var action: (() -> Void)?

    var body: some View {
        HStack {
            Text(title).font(.headline).foregroundStyle(DS.Palette.textPrimary)
                .accessibilityAddTraits(.isHeader)
            Spacer()
            if let actionTitle, let action {
                Button(actionTitle, action: action).font(.callout.weight(.semibold)).tint(DS.Palette.accent)
            }
        }
    }
}

struct MessageBanner: View {
    let message: Message

    var body: some View {
        HStack(alignment: .top, spacing: DS.Spacing.s) {
            Image(systemName: symbol).foregroundStyle(message.level.tone.foreground)
            Text(message.text).font(.callout).foregroundStyle(DS.Palette.textPrimary)
            Spacer(minLength: 0)
        }
        .padding(DS.Spacing.m)
        .background(message.level.tone.background, in: RoundedRectangle(cornerRadius: DS.Radius.m))
        .accessibilityElement(children: .combine)
    }

    private var symbol: String {
        switch message.level {
        case .info: "info.circle.fill"
        case .warning: "exclamationmark.triangle.fill"
        case .error: "xmark.octagon.fill"
        case .success: "checkmark.circle.fill"
        }
    }
}

/// Horizontal progress through a multi-step flow.
struct StepProgress: View {
    let current: Int
    let total: Int
    let title: String

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            HStack {
                Text(title).font(.subheadline.weight(.semibold))
                Spacer()
                Text("Étape \(current) sur \(total)").font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            GeometryReader { proxy in
                ZStack(alignment: .leading) {
                    Capsule().fill(DS.Palette.surfaceMuted)
                    Capsule().fill(DS.Palette.accent)
                        .frame(width: proxy.size.width * CGFloat(current) / CGFloat(max(total, 1)))
                }
            }
            .frame(height: 6)
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(Text("\(title), étape \(current) sur \(total)"))
    }
}

/// Labelled amount used in dashboards and settlements.
struct AmountTile: View {
    let label: String
    let amount: Int
    var tone: StatusTone = .neutral

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.xs) {
            Text(label).font(.caption).foregroundStyle(DS.Palette.textSecondary)
            Text(Money.format(amount))
                .font(DS.Typography.amountSmall)
                .foregroundStyle(tone == .neutral ? DS.Palette.textPrimary : tone.foreground)
                .minimumScaleFactor(0.7)
                .lineLimit(1)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }
}

struct LabeledField<Content: View>: View {
    let label: String
    var error: String?
    @ViewBuilder var content: Content

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.xs) {
            Text(label).font(.subheadline.weight(.medium)).foregroundStyle(DS.Palette.textSecondary)
            content
                .padding(.horizontal, DS.Spacing.m)
                .frame(minHeight: 48)
                .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                .overlay(RoundedRectangle(cornerRadius: DS.Radius.m)
                    .strokeBorder(error == nil ? DS.Palette.border : DS.Palette.danger, lineWidth: error == nil ? 1 : 1.5))
            if let error {
                Text(error).font(.caption).foregroundStyle(DS.Palette.danger)
            }
        }
    }
}
