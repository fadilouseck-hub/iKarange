import SwiftUI

struct ClaimsListView: View {
    var body: some View {
        WithModel({ env in
            RemoteResource(cache: env.cache, key: .claims) { try await env.api.claims() }
        }) { claims in
            ClaimsListContent(claims: claims)
        }
        .navigationTitle("Sinistres")
    }
}

private struct ClaimsListContent: View {
    let claims: RemoteResource<[ClaimSummary]>
    @Environment(AppEnvironment.self) private var env
    @State private var filter: Filter = .all

    enum Filter: String, CaseIterable, Identifiable {
        case all, ongoing, finished
        var id: Self { self }
        var label: String {
            switch self {
            case .all: String(localized: "Tous")
            case .ongoing: String(localized: "En cours")
            case .finished: String(localized: "Terminés")
            }
        }

        func includes(_ status: ClaimStatus) -> Bool {
            switch self {
            case .all: true
            case .ongoing: ![.paid, .closed, .rejected].contains(status)
            case .finished: [.paid, .closed, .rejected].contains(status)
            }
        }
    }

    var body: some View {
        ScrollView {
            VStack(spacing: DS.Spacing.m) {
                if env.cache.load(ClaimDraft.self, key: .claimDraft) != nil {
                    Button { env.router.presentedSheet = .newClaim } label: {
                        MessageBanner(message: Message(level: .info, text: String(localized: "Une déclaration est en brouillon. Touchez pour la reprendre.")))
                    }
                    .buttonStyle(.plain)
                }
                Picker("Filtre", selection: $filter) {
                    ForEach(Filter.allCases) { Text($0.label).tag($0) }
                }
                .pickerStyle(.segmented)

                LoadableContent(value: claims.value, isLoading: claims.isLoading, error: claims.error, retry: reload) { list in
                    let visible = list.filter { filter.includes($0.status) }
                    if visible.isEmpty {
                        EmptyStateView(
                            title: String(localized: "Aucun sinistre"),
                            message: String(localized: "Photographiez une facture pour déclarer vos frais de santé."),
                            symbol: "doc.text.magnifyingglass")
                    } else {
                        LazyVStack(spacing: 0) {
                            ForEach(visible) { claim in
                                NavigationLink(value: Route.claim(id: claim.id)) { ClaimRow(claim: claim) }
                                    .buttonStyle(.plain)
                                    .accessibilityIdentifier("claims.row.\(claim.id)")
                                if claim.id != visible.last?.id { Divider() }
                            }
                        }
                        .card(padding: DS.Spacing.m)
                    }
                } placeholder: {
                    VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 2); SkeletonCard(lines: 2); SkeletonCard(lines: 2) }
                }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await claims.refresh() }
        .task { await claims.load() }
        .onChange(of: env.router.presentedSheet) { _, sheet in if sheet == nil { reload() } }
        .toolbar {
            if env.session.can(.claimsCreate) {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        env.router.presentedSheet = .newClaim
                    } label: {
                        Label("Déclarer", systemImage: "plus.circle.fill")
                    }
                    .accessibilityIdentifier("claims.new")
                }
            }
        }
    }

    private func reload() { Task { await claims.load() } }
}
