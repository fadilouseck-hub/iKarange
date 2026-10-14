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
        .task {
            await env.session.bootstrap()
            await env.performMockSignInIfRequested()
        }
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

/// Tenant logo: the vector mark (`BrandLogo` asset) next to the wordmark, with the tagline under it when set.
struct BrandMark: View {
    var size: CGFloat = 56
    var showsTagline = true

    var body: some View {
        HStack(spacing: size * 0.2) {
            Image("BrandLogo")
                .resizable()
                .scaledToFit()
                .frame(width: size, height: size)
            VStack(alignment: .leading, spacing: size * 0.04) {
                Wordmark(size: size * 0.5)
                if showsTagline, !Tenant.current.tagline.isEmpty {
                    Text(verbatim: Tenant.current.tagline.uppercased())
                        .font(.system(size: size * 0.14, weight: .medium))
                        .tracking(size * 0.03)
                        .foregroundStyle(DS.Palette.textSecondary)
                }
            }
        }
        .accessibilityElement()
        .accessibilityLabel(Text(verbatim: Tenant.current.displayName))
    }
}

/// The tenant wordmark; a trailing "+" is drawn in the bright brand colour, as in the logo.
struct Wordmark: View {
    var size: CGFloat
    var color: Color = DS.Palette.textPrimary

    var body: some View {
        let mark = Tenant.current.wordmark
        let base = mark.hasSuffix("+") ? String(mark.dropLast()) : mark
        (Text(verbatim: base).foregroundStyle(color)
            + Text(verbatim: mark.hasSuffix("+") ? "+" : "").foregroundStyle(DS.Palette.mint))
            .font(.system(size: size, weight: .heavy))
            .tracking(-size * 0.035)
            .lineLimit(1)
            .fixedSize()
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
        if await env.biometrics.authenticate(reason: String(localized: "Déverrouiller \(Tenant.current.displayName)", bundle: .appLanguage)) {
            env.session.unlock()
        } else {
            failed = true
        }
    }
}
