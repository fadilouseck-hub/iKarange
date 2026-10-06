import SwiftUI

struct RootView: View {
    @Environment(AppEnvironment.self) private var env
    @Environment(\.scenePhase) private var scenePhase
    @State private var backgroundedAt: Date?

    var body: some View {
        ZStack {
            switch env.session.state {
            case .launching:
                LaunchView()
            case .signedOut:
                AuthFlowView()
                    .transition(.opacity)
            case .locked:
                LockView()
            case .signedIn:
                MainTabView()
                    .transition(.opacity)
            }
        }
        .animation(.default, value: env.session.state)
        // Hide health data in the app switcher.
        .overlay {
            if scenePhase != .active && env.session.state != .signedOut {
                PrivacyShield()
            }
        }
        .task { await env.session.bootstrap() }
        .onChange(of: scenePhase) { _, phase in
            switch phase {
            case .background:
                backgroundedAt = .now
            case .active:
                if let backgroundedAt, Date.now.timeIntervalSince(backgroundedAt) > 60 { env.session.lock() }
                backgroundedAt = nil
            default:
                break
            }
        }
        .onChange(of: env.session.state) { _, state in
            if state == .signedOut { env.router.reset() }
            if state == .signedIn, !env.isMock { Task { await AppDelegate.requestPushAuthorization() } }
        }
    }
}

struct LaunchView: View {
    var body: some View {
        VStack(spacing: DS.Spacing.m) {
            BrandMark(size: 72)
            ProgressView()
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .screenBackground()
    }
}

struct PrivacyShield: View {
    var body: some View {
        ZStack {
            Rectangle().fill(.ultraThinMaterial)
            DS.Palette.background.opacity(0.85)
            BrandMark(size: 80)
        }
        .ignoresSafeArea()
        .accessibilityHidden(true)
    }
}

struct BrandMark: View {
    var size: CGFloat = 56

    var body: some View {
        HStack(spacing: size * 0.08) {
            ZStack {
                RoundedRectangle(cornerRadius: size * 0.28).fill(DS.Palette.teal)
                Image(systemName: "plus")
                    .font(.system(size: size * 0.5, weight: .black))
                    .foregroundStyle(DS.Palette.mint)
            }
            .frame(width: size, height: size)
            Text("ASSUR+")
                .font(.system(size: size * 0.42, weight: .heavy, design: .rounded))
                .foregroundStyle(DS.Palette.primary)
        }
        .accessibilityElement()
        .accessibilityLabel(Text("ASSUR+"))
    }
}

struct LockView: View {
    @Environment(AppEnvironment.self) private var env
    @State private var failed = false

    var body: some View {
        VStack(spacing: DS.Spacing.xl) {
            Spacer()
            BrandMark(size: 64)
            Text("Application verrouillée").font(DS.Typography.title)
            if failed {
                Text("Authentification échouée. Réessayez ou reconnectez-vous.")
                    .font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            }
            Spacer()
            Button {
                Task { await unlock() }
            } label: {
                Label("Déverrouiller avec \(env.biometrics.availableKind.label)", systemImage: env.biometrics.availableKind.symbol)
            }
            .buttonStyle(.primary)
            Button("Se déconnecter") { Task { await env.session.logout() } }
                .buttonStyle(.secondary)
        }
        .padding(DS.Spacing.xl)
        .screenBackground()
        .task { await unlock() }
    }

    private func unlock() async {
        if await env.biometrics.authenticate(reason: String(localized: "Déverrouiller ASSUR+")) {
            env.session.unlock()
        } else {
            failed = true
        }
    }
}
