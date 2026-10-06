import SwiftUI

struct MainTabView: View {
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        @Bindable var router = env.router
        TabView(selection: $router.selectedTab) {
            NavigationStack(path: $router.homePath) {
                HomeView()
                    .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
            }
            .tabItem { Label("Accueil", systemImage: "house") }
            .tag(AppTab.home)

            if env.session.can(.cardView) {
                NavigationStack { CardView() }
                    .tabItem { Label("Carte", systemImage: "qrcode") }
                    .tag(AppTab.card)
            }

            if env.session.can(.claimsView) {
                NavigationStack(path: $router.claimsPath) {
                    ClaimsListView()
                        .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
                }
                .tabItem { Label("Sinistres", systemImage: "doc.text.magnifyingglass") }
                .tag(AppTab.claims)
            }

            NavigationStack { NetworkView() }
                .tabItem { Label("Réseau", systemImage: "map") }
                .tag(AppTab.network)

            NavigationStack(path: $router.profilePath) {
                ProfileView()
                    .navigationDestination(for: Route.self) { RouteDestination(route: $0) }
            }
            .tabItem { Label("Profil", systemImage: "person.crop.circle") }
            .tag(AppTab.profile)
        }
        .sheet(item: $router.presentedSheet) { sheet in
            switch sheet {
            case .subscription: SubscriptionFlowView()
            case .newClaim: NewClaimFlowView()
            }
        }
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
