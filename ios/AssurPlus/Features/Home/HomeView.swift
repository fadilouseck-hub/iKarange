import SwiftUI

struct HomeView: View {
    var body: some View {
        WithModel({ env in
            RemoteResource(cache: env.cache, key: .dashboard) { try await env.api.dashboard() }
        }) { dashboard in
            HomeContent(dashboard: dashboard)
        }
    }
}

private struct HomeContent: View {
    let dashboard: RemoteResource<Dashboard>
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        ScrollView {
            LoadableContent(value: dashboard.value, isLoading: dashboard.isLoading, error: dashboard.error, retry: reload) { data in
                DashboardSections(data: data)
            } placeholder: {
                VStack(spacing: DS.Spacing.m) {
                    SkeletonCard(lines: 2)
                    SkeletonCard(lines: 4)
                    SkeletonCard(lines: 3)
                }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await dashboard.refresh() }
        .navigationTitle(greeting)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink(value: Route.notifications) {
                    Image(systemName: "bell")
                        .overlay(alignment: .topTrailing) {
                            if let unread = dashboard.value?.unreadNotifications, unread > 0 {
                                Text("\(min(unread, 9))")
                                    .font(.caption2.bold())
                                    .foregroundStyle(.white)
                                    .padding(4)
                                    .background(DS.Palette.danger, in: Circle())
                                    .offset(x: 8, y: -8)
                            }
                        }
                }
                .accessibilityLabel(Text("Notifications, \(dashboard.value?.unreadNotifications ?? 0) non lues"))
                .accessibilityIdentifier("home.notifications")
            }
        }
        .task { await dashboard.load() }
        // Back from a flow that changed data (claim, subscription): refresh.
        .onChange(of: env.router.presentedSheet) { _, sheet in
            if sheet == nil { Task { await dashboard.load(); await env.session.refreshUser() } }
        }
    }

    private var greeting: String {
        let name = env.session.user?.firstName ?? dashboard.value?.fullName ?? ""
        return String(localized: "Bonjour \(name)", bundle: .appLanguage)
    }

    private func reload() { Task { await dashboard.load() } }
}

private struct DashboardSections: View {
    let data: Dashboard
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            ForEach(data.alerts, id: \.self) { MessageBanner(message: $0) }

            if let policy = data.policy {
                PolicyCard(policy: policy, memberNumber: data.memberNumber)
            } else if env.session.can(.subscriptionCreate) {
                SubscribeCard()
            }

            if let limits = data.limits { LimitsCard(limits: limits) }

            QuickActions(hasPolicy: data.policy != nil)

            if !data.dependents.isEmpty, env.session.can(.familyView) {
                VStack(alignment: .leading, spacing: DS.Spacing.m) {
                    SectionHeader(title: String(localized: "Ma famille", bundle: .appLanguage), actionTitle: String(localized: "Gérer", bundle: .appLanguage)) {
                        env.router.homePath.append(Route.family)
                    }
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: DS.Spacing.m) {
                            ForEach(data.dependents) { DependentChip(dependent: $0) }
                        }
                    }
                }
            }

            if env.session.can(.claimsView) {
                VStack(alignment: .leading, spacing: DS.Spacing.m) {
                    SectionHeader(title: String(localized: "Derniers sinistres", bundle: .appLanguage), actionTitle: data.recentClaims.isEmpty ? nil : String(localized: "Tout voir", bundle: .appLanguage)) {
                        env.router.selectedTab = .claims
                    }
                    if data.recentClaims.isEmpty {
                        Text("Aucun sinistre déclaré pour le moment.")
                            .font(.callout).foregroundStyle(DS.Palette.textSecondary)
                            .card()
                    } else {
                        VStack(spacing: 0) {
                            ForEach(data.recentClaims) { claim in
                                Button {
                                    env.router.selectedTab = .claims
                                    env.router.claimsPath.append(Route.claim(id: claim.id))
                                } label: {
                                    ClaimRow(claim: claim)
                                }
                                .buttonStyle(.plain)
                                if claim.id != data.recentClaims.last?.id { Divider() }
                            }
                        }
                        .card(padding: DS.Spacing.m)
                    }
                }
            }
        }
    }
}

private struct PolicyCard: View {
    let policy: PolicySummary
    let memberNumber: String?

    var body: some View {
        NavigationLink(value: Route.policy) {
            VStack(alignment: .leading, spacing: DS.Spacing.m) {
                HStack(alignment: .top) {
                    VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                        Text("Formule \(policy.formulaName)").font(.title3.weight(.bold))
                        Text(policy.productName).font(.callout).opacity(0.85)
                    }
                    Spacer()
                    Text(policy.status.label)
                        .font(.caption.weight(.semibold))
                        .padding(.horizontal, DS.Spacing.s).padding(.vertical, DS.Spacing.xs)
                        .background(.white.opacity(0.18), in: Capsule())
                }
                Divider().overlay(.white.opacity(0.3))
                HStack {
                    LabelValue(label: String(localized: "Contrat", bundle: .appLanguage), value: policy.number)
                    Spacer()
                    LabelValue(label: String(localized: "Couverture", bundle: .appLanguage), value: Percent.format(policy.coverageRate))
                }
                HStack {
                    LabelValue(label: String(localized: "Début", bundle: .appLanguage), value: DateText.day(policy.startDate))
                    Spacer()
                    LabelValue(label: String(localized: "Fin", bundle: .appLanguage), value: DateText.day(policy.endDate))
                }
                if let memberNumber {
                    LabelValue(label: String(localized: "N° d'assuré", bundle: .appLanguage), value: memberNumber)
                }
            }
            .foregroundStyle(.white)
            .padding(DS.Spacing.l)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(
                LinearGradient(colors: [DS.Palette.teal, DS.Palette.tealMid], startPoint: .topLeading, endPoint: .bottomTrailing),
                in: RoundedRectangle(cornerRadius: DS.Radius.l))
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("home.policyCard")
        .accessibilityHint(Text("Ouvre le détail du contrat"))
    }
}

private struct LabelValue: View {
    let label: String
    let value: String

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
            Text(label).font(.caption).opacity(0.75)
            Text(value).font(.callout.weight(.semibold))
        }
        .accessibilityElement(children: .combine)
    }
}

private struct SubscribeCard: View {
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Image(systemName: "shield.lefthalf.filled.badge.checkmark")
                .font(.largeTitle)
                .foregroundStyle(DS.Palette.accent)
                .accessibilityHidden(true)
            Text("Protégez-vous et votre famille").font(.title3.weight(.bold))
            Text("Choisissez une formule, obtenez votre devis et payez par Wave, Orange Money ou carte. Moins de 3 minutes.")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary)
            Button("Souscrire maintenant") { env.router.presentedSheet = .subscription }
                .buttonStyle(.primary)
                .accessibilityIdentifier("home.subscribe")
        }
        .card()
    }
}

struct LimitsCard: View {
    let limits: Limits

    private var consumedRatio: Double {
        guard limits.annualLimit > 0 else { return 0 }
        return min(max(Double(limits.consumed) / Double(limits.annualLimit), 0), 1)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text("Plafond disponible").font(.subheadline).foregroundStyle(DS.Palette.textSecondary)
            Text(Money.format(limits.remaining))
                .font(DS.Typography.amount)
                .foregroundStyle(DS.Palette.textPrimary)
                .accessibilityIdentifier("home.remainingLimit")
            ProgressView(value: consumedRatio)
                .tint(consumedRatio > 0.85 ? DS.Palette.warning : DS.Palette.accent)
                .accessibilityLabel(Text("Plafond consommé"))
                .accessibilityValue(Text("\(Int(consumedRatio * 100)) %"))
            Text("sur un plafond annuel de \(Money.format(limits.annualLimit))")
                .font(.caption).foregroundStyle(DS.Palette.textSecondary)
            HStack(spacing: DS.Spacing.m) {
                AmountTile(label: String(localized: "Consommé", bundle: .appLanguage), amount: limits.consumed)
                AmountTile(label: String(localized: "Remboursé", bundle: .appLanguage), amount: limits.reimbursed, tone: .success)
            }
        }
        .card()
    }
}

private struct QuickActions: View {
    let hasPolicy: Bool
    @Environment(AppEnvironment.self) private var env

    private struct Action: Identifiable {
        let id: String
        let title: String
        let symbol: String
        let perform: () -> Void
    }

    private var actions: [Action] {
        let router = env.router
        var list: [Action] = []
        if hasPolicy, env.session.can(.cardView) {
            list.append(Action(id: "card", title: String(localized: "Ma carte", bundle: .appLanguage), symbol: "qrcode") { router.selectedTab = .card })
        }
        if hasPolicy, env.session.can(.claimsCreate) {
            list.append(Action(id: "claim", title: String(localized: "Déclarer", bundle: .appLanguage), symbol: "camera.viewfinder") { router.presentedSheet = .newClaim })
        }
        if hasPolicy, env.session.can(.familyView) {
            list.append(Action(id: "family", title: String(localized: "Famille", bundle: .appLanguage), symbol: "person.3") { router.homePath.append(Route.family) })
        }
        if hasPolicy, env.session.can(.policyView) {
            list.append(Action(id: "policy", title: String(localized: "Contrat", bundle: .appLanguage), symbol: "doc.text") { router.homePath.append(Route.policy) })
        }
        if env.session.can(.paymentsView) {
            list.append(Action(id: "payments", title: String(localized: "Paiements", bundle: .appLanguage), symbol: "creditcard") { router.homePath.append(Route.payments) })
        }
        if env.session.can(.vaultView) {
            list.append(Action(id: "vault", title: String(localized: "Coffre santé", bundle: .appLanguage), symbol: "lock.doc") { router.homePath.append(Route.vault) })
        }
        return list
    }

    var body: some View {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 100), spacing: DS.Spacing.m)], spacing: DS.Spacing.m) {
            ForEach(actions) { action in
                Button(action: action.perform) {
                    VStack(spacing: DS.Spacing.s) {
                        Image(systemName: action.symbol)
                            .font(.title2)
                            .foregroundStyle(DS.Palette.accent)
                            .frame(width: 48, height: 48)
                            .background(DS.Palette.accentSoft, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                        Text(action.title)
                            .font(.footnote.weight(.medium))
                            .foregroundStyle(DS.Palette.textPrimary)
                            .multilineTextAlignment(.center)
                            .lineLimit(2)
                    }
                    .frame(maxWidth: .infinity, minHeight: 96)
                    .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("home.action.\(action.id)")
            }
        }
    }
}

private struct DependentChip: View {
    let dependent: DependentSummary

    var body: some View {
        HStack(spacing: DS.Spacing.s) {
            InitialsAvatar(name: dependent.fullName, size: 36)
            VStack(alignment: .leading, spacing: 0) {
                Text(dependent.fullName).font(.subheadline.weight(.semibold)).lineLimit(1)
                Text(dependent.relationLabel).font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
        }
        .padding(DS.Spacing.m)
        .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
        .accessibilityElement(children: .combine)
    }
}

struct InitialsAvatar: View {
    let name: String
    var size: CGFloat = 44

    private var initials: String {
        name.split(separator: " ").prefix(2).compactMap(\.first).map(String.init).joined().uppercased()
    }

    var body: some View {
        Text(initials)
            .font(.system(size: size * 0.38, weight: .bold, design: .rounded))
            .foregroundStyle(DS.Palette.teal)
            .frame(width: size, height: size)
            .background(DS.Palette.mint.opacity(0.35), in: Circle())
            .accessibilityHidden(true)
    }
}

struct ClaimRow: View {
    let claim: ClaimSummary

    var body: some View {
        HStack(spacing: DS.Spacing.m) {
            Image(systemName: claim.status.symbol)
                .foregroundStyle(claim.status.tone.foreground)
                .frame(width: 36, height: 36)
                .background(claim.status.tone.background, in: Circle())
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                Text(claim.typeLabel).font(.subheadline.weight(.semibold))
                Text("\(claim.beneficiaryName) · \(DateText.short(claim.createdAt))")
                    .font(.caption).foregroundStyle(DS.Palette.textSecondary)
                StatusBadge(claim.status)
            }
            Spacer(minLength: DS.Spacing.s)
            VStack(alignment: .trailing, spacing: DS.Spacing.xxs) {
                if let amount = claim.amount {
                    Text(Money.format(amount)).font(.subheadline.weight(.semibold).monospacedDigit())
                }
                if let number = claim.number {
                    Text(number).font(.caption2).foregroundStyle(DS.Palette.textSecondary)
                }
            }
        }
        .padding(.vertical, DS.Spacing.s)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
    }
}
