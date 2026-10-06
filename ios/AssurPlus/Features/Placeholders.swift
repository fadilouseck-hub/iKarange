import SwiftUI

// Temporary stand-ins, replaced feature by feature.
private struct Placeholder: View {
    let title: String
    var body: some View {
        EmptyStateView(title: title, message: "Bientôt disponible.", symbol: "hammer").navigationTitle(title)
    }
}

struct HomeView: View { var body: some View { Placeholder(title: "Accueil") } }
struct CardView: View { var body: some View { Placeholder(title: "Carte") } }
struct ClaimsListView: View { var body: some View { Placeholder(title: "Sinistres") } }
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
struct NewClaimFlowView: View { var body: some View { Placeholder(title: "Déclarer") } }
struct PolicyView: View { var body: some View { Placeholder(title: "Contrat") } }
struct FamilyView: View { var body: some View { Placeholder(title: "Famille") } }
struct PaymentHistoryView: View { var body: some View { Placeholder(title: "Paiements") } }
struct VaultView: View { var body: some View { Placeholder(title: "Coffre santé") } }
struct NotificationsView: View { var body: some View { Placeholder(title: "Notifications") } }
struct ClaimDetailView: View { let claimId: String; var body: some View { Placeholder(title: "Sinistre") } }
struct PaymentDetailView: View { let paymentId: String; var body: some View { Placeholder(title: "Paiement") } }
