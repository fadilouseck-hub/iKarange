import SwiftUI

struct MainTabView: View {
    @Environment(AppEnvironment.self) private var env

    private var tabs: [AppTab] {
        AppTab.allCases.filter { tab in
            switch tab {
            case .card: env.session.can(.cardView)
            case .claims: env.session.can(.claimsView)
            default: true
            }
        }
    }

    var body: some View {
        @Bindable var router = env.router
        TabView(selection: $router.selectedTab) {
            NavigationStack(path: $router.homePath) {
                HomeView()
                    .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
            }
            .floatingTabContent()
            .tag(AppTab.home)

            if env.session.can(.cardView) {
                NavigationStack { CardView() }
                    .floatingTabContent()
                    .tag(AppTab.card)
            }

            if env.session.can(.claimsView) {
                NavigationStack(path: $router.claimsPath) {
                    ClaimsListView()
                        .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
                }
                .floatingTabContent()
                .tag(AppTab.claims)
            }

            NavigationStack { NetworkView() }
                .floatingTabContent()
                .tag(AppTab.network)

            NavigationStack(path: $router.profilePath) {
                ProfileView()
                    .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
            }
            .floatingTabContent()
            .tag(AppTab.profile)
        }
        // Floating capsule menu (replaces the system tab bar); content scrolls underneath it.
        .safeAreaInset(edge: .bottom, spacing: 0) {
            FloatingTabBar(tabs: tabs, selection: $router.selectedTab, userName: env.session.user?.fullName ?? "")
        }
        .sheet(item: $router.presentedSheet) { sheet in
            switch sheet {
            case .subscription: SubscriptionFlowView()
            case .newClaim: NewClaimFlowView()
            }
        }
    }
}

extension AppTab {
    var title: LocalizedStringKey {
        switch self {
        case .home: "Accueil"
        case .card: "Carte"
        case .claims: "Sinistres"
        case .network: "Réseau"
        case .profile: "Profil"
        }
    }

    var symbol: String {
        switch self {
        case .home: "house.fill"
        case .card: "qrcode"
        case .claims: "doc.text.magnifyingglass"
        case .network: "map.fill"
        case .profile: "person.crop.circle.fill"
        }
    }
}

private extension View {
    /// Hides the system tab bar; the floating menu is drawn by `MainTabView`.
    func floatingTabContent() -> some View {
        toolbar(.hidden, for: .tabBar)
    }
}

/// Icon-only capsule floating above the content: deep brand colour, glowing accent on the selected tab,
/// initials avatar for Profile.
struct FloatingTabBar: View {
    let tabs: [AppTab]
    @Binding var selection: AppTab
    let userName: String
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Namespace private var highlight

    var body: some View {
        HStack(spacing: 0) {
            ForEach(tabs, id: \.self) { tab in
                let isSelected = selection == tab
                Button {
                    withAnimation(reduceMotion ? nil : .spring(response: 0.35, dampingFraction: 0.8)) { selection = tab }
                } label: {
                    ZStack {
                        if isSelected {
                            Circle()
                                .fill(DS.Palette.mint.opacity(0.18))
                                .frame(width: 48, height: 48)
                                .matchedGeometryEffect(id: "highlight", in: highlight)
                        }
                        icon(for: tab, selected: isSelected)
                    }
                    .frame(maxWidth: .infinity, minHeight: 56)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityLabel(Text(tab.title))
                .accessibilityAddTraits(isSelected ? [.isSelected, .isButton] : .isButton)
                .accessibilityIdentifier("tab.\(tab.rawValue)")
            }
        }
        .padding(.horizontal, DS.Spacing.s)
        .padding(.vertical, DS.Spacing.xs)
        .background {
            Capsule()
                .fill(
                    LinearGradient(colors: [DS.Palette.tealMid.opacity(0.96), DS.Palette.teal.opacity(0.98)],
                                   startPoint: .top, endPoint: .bottom))
                .background(.ultraThinMaterial, in: Capsule())
                .overlay(Capsule().strokeBorder(.white.opacity(0.12), lineWidth: 1))
                .shadow(color: .black.opacity(0.28), radius: 18, y: 8)
        }
        .padding(.horizontal, DS.Spacing.xl)
        .padding(.bottom, DS.Spacing.s)
        .ignoresSafeArea(.keyboard)
    }

    @ViewBuilder private func icon(for tab: AppTab, selected: Bool) -> some View {
        if tab == .profile, !userName.isEmpty {
            Text(initials)
                .font(.system(size: 13, weight: .bold, design: .rounded))
                .foregroundStyle(DS.Palette.teal)
                .frame(width: 32, height: 32)
                .background(Circle().fill(DS.Palette.mint))
                .overlay(Circle().strokeBorder(.white.opacity(selected ? 0.9 : 0), lineWidth: 2))
                .accessibilityHidden(true)
        } else {
            Image(systemName: tab.symbol)
                .font(.system(size: 22, weight: .semibold))
                .foregroundStyle(selected ? DS.Palette.mint : .white.opacity(0.62))
                .shadow(color: selected ? DS.Palette.mint.opacity(0.7) : .clear, radius: 8)
                .accessibilityHidden(true)
        }
    }

    private var initials: String {
        userName.split(separator: " ").prefix(2).compactMap(\.first).map(String.init).joined().uppercased()
    }
}

struct RouteDestination: View {
    let route: Route

    var body: some View {
        switch route {
        case .policy: PolicyView()
        case .family: FamilyView()
        case .payments: PaymentHistoryView()
        case .vault: VaultView()
        case .notifications: NotificationsView()
        case .claim(let id): ClaimDetailView(claimId: id)
        case .payment(let id): PaymentDetailView(paymentId: id)
        }
    }
}
