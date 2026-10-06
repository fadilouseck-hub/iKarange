import SwiftUI

// Temporary stand-ins, replaced feature by feature.
private struct Placeholder: View {
    let title: String
    var body: some View {
        EmptyStateView(title: title, message: "Bientôt disponible.", symbol: "hammer").navigationTitle(title)
    }
}

struct NetworkView: View { var body: some View { Placeholder(title: "Réseau") } }
struct ProfileView: View {
    @Environment(AppEnvironment.self) private var env
    var body: some View {
        Button("Se déconnecter") { Task { await env.session.logout() } }.buttonStyle(.secondary).padding()
            .navigationTitle("Profil")
    }
}
struct SubscriptionFlowView: View { var body: some View { Placeholder(title: "Souscription") } }
struct SubscriptionEntryView: View { var body: some View { Placeholder(title: "Souscription") } }
struct FamilyView: View { var body: some View { Placeholder(title: "Famille") } }
struct PaymentHistoryView: View { var body: some View { Placeholder(title: "Paiements") } }
struct VaultView: View { var body: some View { Placeholder(title: "Coffre santé") } }
struct NotificationsView: View { var body: some View { Placeholder(title: "Notifications") } }
struct PaymentDetailView: View { let paymentId: String; var body: some View { Placeholder(title: "Paiement") } }
