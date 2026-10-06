import SwiftUI
import Observation

enum AppTab: String, Hashable, CaseIterable {
    case home, card, claims, network, profile
}

/// Destinations pushed on a tab's navigation stack.
enum Route: Hashable {
    case policy
    case family
    case payments
    case vault
    case notifications
    case claim(id: String)
    case payment(id: String)
}

/// Owns tab selection and navigation stacks so push notifications and URLs can open any screen.
@MainActor
@Observable
final class Router {
    var selectedTab: AppTab = .home
    var homePath = NavigationPath()
    var claimsPath = NavigationPath()
    var profilePath = NavigationPath()
    var presentedSheet: Sheet?

    enum Sheet: Identifiable, Hashable {
        case subscription
        case newClaim
        var id: Self { self }
    }

    func open(_ target: DeepLinkTarget) {
        presentedSheet = nil
        switch target.kind {
        case .claim:
            selectedTab = .claims
            claimsPath = NavigationPath()
            if let id = target.id { claimsPath.append(Route.claim(id: id)) }
        case .payment:
            selectedTab = .home
            homePath = NavigationPath()
            homePath.append(Route.payments)
            if let id = target.id { homePath.append(Route.payment(id: id)) }
        case .policy:
            selectedTab = .home
            homePath = NavigationPath()
            homePath.append(Route.policy)
        case .dependents:
            selectedTab = .home
            homePath = NavigationPath()
            homePath.append(Route.family)
        case .card:
            selectedTab = .card
        case .vault:
            selectedTab = .home
            homePath = NavigationPath()
            homePath.append(Route.vault)
        case .notifications:
            selectedTab = .home
            homePath = NavigationPath()
            homePath.append(Route.notifications)
        case .subscription:
            presentedSheet = .subscription
        }
    }

    /// `assurplus://claims/<id>`, `assurplus://card`, … Payment return URLs are handled by the payment flow.
    func open(url: URL) {
        guard url.scheme == "assurplus", let host = url.host() else { return }
        let id = url.pathComponents.dropFirst().first
        let kind: DeepLinkTarget.Kind? = switch host {
        case "claims": .claim
        case "payments": .payment
        case "policy": .policy
        case "family": .dependents
        case "card": .card
        case "vault": .vault
        case "notifications": .notifications
        case "subscribe": .subscription
        default: nil
        }
        if let kind { open(DeepLinkTarget(kind: kind, id: id)) }
    }

    func reset() {
        selectedTab = .home
        homePath = NavigationPath()
        claimsPath = NavigationPath()
        profilePath = NavigationPath()
        presentedSheet = nil
    }
}
