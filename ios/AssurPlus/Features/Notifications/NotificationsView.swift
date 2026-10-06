import SwiftUI

struct NotificationsView: View {
    var body: some View {
        WithModel({ env in
            RemoteResource(cache: env.cache, key: .notifications) { try await env.api.notifications() }
        }) { resource in
            NotificationsContent(resource: resource)
        }
        .navigationTitle("Notifications")
    }
}

private struct NotificationsContent: View {
    let resource: RemoteResource<NotificationsResponse>
    @Environment(AppEnvironment.self) private var env

    var body: some View {
        ScrollView {
            LoadableContent(value: resource.value, isLoading: resource.isLoading, error: resource.error, retry: { Task { await resource.load() } }) { response in
                if response.notifications.isEmpty {
                    EmptyStateView(title: String(localized: "Aucune notification"), message: String(localized: "Vous serez informé ici de l'avancement de vos sinistres, paiements et contrat."), symbol: "bell")
                } else {
                    LazyVStack(spacing: DS.Spacing.s) {
                        ForEach(response.notifications) { notification in
                            Button { open(notification) } label: { row(notification) }
                                .buttonStyle(.plain)
                                .accessibilityIdentifier("notification.\(notification.id)")
                        }
                    }
                }
            } placeholder: {
                VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 2); SkeletonCard(lines: 2) }
            }
            .padding(DS.Spacing.l)
        }
        .screenBackground()
        .refreshable { await resource.refresh() }
        .task { await resource.load() }
        .toolbar {
            if (resource.value?.unreadCount ?? 0) > 0 {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Tout lire") { Task { await markAllRead() } }
                }
            }
        }
    }

    private func row(_ notification: AppNotification) -> some View {
        HStack(alignment: .top, spacing: DS.Spacing.m) {
            Image(systemName: symbol(for: notification.category))
                .foregroundStyle(DS.Palette.accent)
                .frame(width: 36, height: 36)
                .background(DS.Palette.accentSoft, in: Circle())
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                HStack {
                    Text(notification.title).font(.subheadline.weight(notification.read ? .regular : .bold))
                    Spacer()
                    if !notification.read {
                        Circle().fill(DS.Palette.accent).frame(width: 8, height: 8).accessibilityLabel(Text("Non lue"))
                    }
                }
                Text(notification.body).font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.leading)
                Text(DateText.relative(notification.createdAt)).font(.caption2).foregroundStyle(DS.Palette.textSecondary)
            }
        }
        .card(padding: DS.Spacing.m)
        .accessibilityElement(children: .combine)
    }

    private func symbol(for category: String) -> String {
        switch category {
        case "claim": "doc.text.magnifyingglass"
        case "reimbursement": "banknote"
        case "payment": "creditcard"
        case "contract": "doc.badge.clock"
        case "welcome", "subscription": "sparkles"
        default: "bell"
        }
    }

    /// Marks it read, then opens the related screen.
    private func open(_ notification: AppNotification) {
        if !notification.read, let value = resource.value {
            let updated = value.notifications.map { item -> AppNotification in
                var copy = item
                if copy.id == notification.id { copy.read = true }
                return copy
            }
            resource.update(NotificationsResponse(notifications: updated, unreadCount: max(value.unreadCount - 1, 0)))
            Task { try? await env.api.markNotificationRead(id: notification.id) }
        }
        if let target = notification.target, target.kind != .notifications {
            env.router.open(target)
        }
    }

    private func markAllRead() async {
        guard let value = resource.value else { return }
        resource.update(NotificationsResponse(notifications: value.notifications.map { var copy = $0; copy.read = true; return copy }, unreadCount: 0))
        try? await env.api.markAllNotificationsRead()
    }
}
