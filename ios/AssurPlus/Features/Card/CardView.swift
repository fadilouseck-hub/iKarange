import PassKit
import SwiftUI

struct CardView: View {
    var body: some View {
        WithModel(CardViewModel.init) { model in
            CardContent(model: model)
        }
        .navigationTitle("Carte tiers-payant")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct CardContent: View {
    @Bindable var model: CardViewModel
    @State private var brightness = ScreenBrightness()

    var body: some View {
        ScrollView {
            LoadableContent(value: model.card.value, isLoading: model.card.isLoading, error: model.card.error, retry: { Task { await model.load() } }) { card in
                if card.beneficiaries.isEmpty {
                    EmptyStateView(
                        title: String(localized: "Pas encore de carte"),
                        message: String(localized: "Votre carte tiers-payant sera disponible dès l'activation de votre contrat."),
                        symbol: "creditcard")
                } else {
                    VStack(spacing: DS.Spacing.l) {
                        if card.beneficiaries.count > 1 { switcher(card.beneficiaries) }
                        if let beneficiary = model.selected {
                            MemberCardView(beneficiary: beneficiary)
                            QRPanel(model: model)
                            walletButton
                        }
                        Text("Présentez ce QR code au prestataire de santé. Il se renouvelle automatiquement et ne contient aucune donnée personnelle lisible.")
                            .font(.footnote)
                            .foregroundStyle(DS.Palette.textSecondary)
                            .multilineTextAlignment(.center)
                    }
                }
            } placeholder: {
                VStack(spacing: DS.Spacing.m) {
                    SkeletonBlock(height: 200)
                    SkeletonBlock(height: 260)
                }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await Task { await model.load() }.value }
        .task { await model.load() }
        .task(id: model.selected?.id) { await model.runTokenRefreshLoop() }
        .onAppear { brightness.boost() }
        .onDisappear { brightness.restore() }
    }

    private func switcher(_ beneficiaries: [CardBeneficiary]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: DS.Spacing.s) {
                ForEach(beneficiaries) { beneficiary in
                    let isSelected = beneficiary.id == model.selected?.id
                    Button {
                        model.selectedId = beneficiary.id
                    } label: {
                        Text(beneficiary.fullName.split(separator: " ").first.map(String.init) ?? beneficiary.fullName)
                            .font(.subheadline.weight(.semibold))
                            .padding(.horizontal, DS.Spacing.m)
                            .padding(.vertical, DS.Spacing.s)
                            .foregroundStyle(isSelected ? DS.Palette.onPrimary : DS.Palette.textPrimary)
                            .background(isSelected ? DS.Palette.primary : DS.Palette.surface, in: Capsule())
                    }
                    .buttonStyle(.plain)
                    .accessibilityAddTraits(isSelected ? .isSelected : [])
                    .accessibilityLabel(Text("\(beneficiary.fullName), \(beneficiary.relationLabel)"))
                    .accessibilityIdentifier("card.beneficiary.\(beneficiary.id)")
                }
            }
        }
    }

    @ViewBuilder private var walletButton: some View {
        if model.canAddToWallet {
            VStack(spacing: DS.Spacing.s) {
                AddToWalletButton { Task { await model.addToWallet() } }
                    .frame(height: 48)
                    .disabled(model.isAddingToWallet)
                    .opacity(model.isAddingToWallet ? 0.5 : 1)
                    .accessibilityIdentifier("card.addToWallet")
                if let message = model.walletMessage {
                    MessageBanner(message: message).accessibilityIdentifier("card.walletMessage")
                }
            }
        }
    }
}

struct MemberCardView: View {
    let beneficiary: CardBeneficiary

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            HStack {
                Text("ASSUR+").font(.system(.headline, design: .rounded).weight(.heavy))
                Spacer()
                Text(beneficiary.insurerName).font(.caption.weight(.semibold)).opacity(0.85)
            }
            HStack(spacing: DS.Spacing.m) {
                photo
                VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                    Text(beneficiary.fullName).font(.title3.weight(.bold)).lineLimit(2).minimumScaleFactor(0.8)
                    Text(beneficiary.relationLabel).font(.caption).opacity(0.85)
                    if let birth = beneficiary.birthDate {
                        Text("Né(e) le \(DateText.short(birth.date))").font(.caption).opacity(0.85)
                    }
                }
            }
            HStack(alignment: .bottom) {
                field(String(localized: "N° assuré"), beneficiary.memberNumber)
                Spacer()
                field(String(localized: "Contrat"), beneficiary.policyNumber)
            }
            HStack(alignment: .bottom) {
                field(String(localized: "Formule"), beneficiary.formulaName)
                Spacer()
                field(String(localized: "Couverture"), Percent.format(beneficiary.coverageRate))
                Spacer()
                field(String(localized: "Valide jusqu'au"), DateText.short(beneficiary.validUntil.date))
            }
            if beneficiary.status.code != "active" {
                Text(beneficiary.status.label)
                    .font(.caption.weight(.bold))
                    .padding(.horizontal, DS.Spacing.s).padding(.vertical, DS.Spacing.xs)
                    .background(DS.Palette.danger, in: Capsule())
            }
        }
        .foregroundStyle(.white)
        .padding(DS.Spacing.l)
        .background {
            ZStack(alignment: .topTrailing) {
                LinearGradient(colors: [DS.Palette.teal, DS.Palette.tealMid], startPoint: .topLeading, endPoint: .bottomTrailing)
                Circle().fill(DS.Palette.mint.opacity(0.18)).frame(width: 220).offset(x: 90, y: -110)
            }
        }
        .clipShape(RoundedRectangle(cornerRadius: DS.Radius.l))
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("card.member")
    }

    @ViewBuilder private var photo: some View {
        if let url = beneficiary.photoURL {
            AsyncImage(url: url) { image in
                image.resizable().scaledToFill()
            } placeholder: {
                InitialsAvatar(name: beneficiary.fullName, size: 64)
            }
            .frame(width: 64, height: 64)
            .clipShape(RoundedRectangle(cornerRadius: DS.Radius.m))
        } else {
            Text(beneficiary.fullName.split(separator: " ").prefix(2).compactMap(\.first).map(String.init).joined())
                .font(.title2.weight(.bold))
                .frame(width: 64, height: 64)
                .background(.white.opacity(0.18), in: RoundedRectangle(cornerRadius: DS.Radius.m))
                .accessibilityHidden(true)
        }
    }

    private func field(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
            Text(label).font(.caption2).opacity(0.75)
            Text(value).font(.footnote.weight(.semibold).monospacedDigit())
        }
    }
}

private struct QRPanel: View {
    let model: CardViewModel

    var body: some View {
        TimelineView(.periodic(from: .now, by: 1)) { context in
            let token = model.currentToken(at: context.date)
            VStack(spacing: DS.Spacing.m) {
                ZStack {
                    if let token, let image = StyledQRCache.image(for: token.token) {
                        Image(uiImage: image)
                            .resizable()
                            .interpolation(.high)
                            .scaledToFit()
                            .accessibilityLabel(Text("QR code de la carte tiers-payant"))
                            .accessibilityValue(Text(token.token))
                            .accessibilityIdentifier("card.qr")
                    } else {
                        RoundedRectangle(cornerRadius: DS.Radius.m).fill(DS.Palette.surfaceMuted)
                        VStack(spacing: DS.Spacing.s) {
                            if model.qrError == nil {
                                ProgressView()
                                Text("Génération du QR code…").font(.footnote)
                            } else {
                                Image(systemName: "wifi.slash").font(.title)
                                Text("QR code indisponible hors ligne. Reconnectez-vous pour le renouveler.")
                                    .font(.footnote).multilineTextAlignment(.center)
                            }
                        }
                        .foregroundStyle(DS.Palette.textSecondary)
                        .padding()
                    }
                }
                .frame(width: 240, height: 240)
                .padding(DS.Spacing.m)
                .background(.white, in: RoundedRectangle(cornerRadius: DS.Radius.l))

                if let token {
                    let seconds = max(Int(token.expiresAt.timeIntervalSince(context.date)), 0)
                    Label("Renouvellement dans \(seconds) s", systemImage: "arrow.triangle.2.circlepath")
                        .font(.caption.monospacedDigit())
                        .foregroundStyle(DS.Palette.textSecondary)
                        .accessibilityIdentifier("card.qrCountdown")
                }
            }
            .frame(maxWidth: .infinity)
            .card()
        }
    }
}

/// Apple's official "Add to Apple Wallet" button.
struct AddToWalletButton: UIViewRepresentable {
    let action: () -> Void

    func makeUIView(context: Context) -> PKAddPassButton {
        let button = PKAddPassButton(addPassButtonStyle: .black)
        button.addTarget(context.coordinator, action: #selector(Coordinator.tapped), for: .touchUpInside)
        return button
    }

    func updateUIView(_ uiView: PKAddPassButton, context: Context) {
        context.coordinator.action = action
    }

    func makeCoordinator() -> Coordinator { Coordinator(action: action) }

    final class Coordinator: NSObject {
        var action: () -> Void
        init(action: @escaping () -> Void) { self.action = action }
        @objc func tapped() { action() }
    }
}

/// Raises screen brightness while the card is shown so scanners read the QR, then restores it.
@MainActor
final class ScreenBrightness {
    private var previous: CGFloat?

    private var screen: UIScreen? {
        (UIApplication.shared.connectedScenes.first { $0.activationState == .foregroundActive } as? UIWindowScene)?.screen
    }

    func boost() {
        guard let screen, previous == nil else { return }
        previous = screen.brightness
        screen.brightness = max(screen.brightness, 0.9)
    }

    func restore() {
        guard let screen, let previous else { return }
        screen.brightness = previous
        self.previous = nil
    }
}
