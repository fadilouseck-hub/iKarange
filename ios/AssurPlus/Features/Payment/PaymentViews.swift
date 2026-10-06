import SwiftUI

/// Method choice, provider hand-off and server confirmation. Used by the subscription flow.
struct PaymentStepView: View {
    @Bindable var model: PaymentViewModel
    var onSuccess: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            VStack(alignment: .leading, spacing: DS.Spacing.xs) {
                Text("Montant à payer").font(.callout).foregroundStyle(DS.Palette.textSecondary)
                Text(Money.format(model.amount)).font(DS.Typography.amount).accessibilityIdentifier("payment.amount")
            }
            .card()

            if let error = model.error {
                MessageBanner(message: Message(level: .error, text: error.userMessage))
            }

            switch model.phase {
            case .choosing: chooser
            case .launching, .confirming: waiting
            case .finished: result
            }
        }
        .task { if model.methods.isEmpty { await model.loadMethods() } }
    }

    private var chooser: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text("Moyen de paiement").font(.headline)
            if model.isLoadingMethods && model.methods.isEmpty { SkeletonCard(lines: 2) }
            ForEach(model.methods) { method in
                SelectableRow(
                    title: method.label, subtitle: method.help,
                    leading: AnyView(PaymentMethodIcon(kind: method.kind)),
                    isSelected: model.selectedMethod == method.code
                ) { model.selectedMethod = method.code }
                .disabled(!method.enabled)
                .accessibilityIdentifier("payment.method.\(method.code)")
            }
            if model.method?.requiresPhone == true {
                PhoneField(phone: $model.phone, error: model.error?.fieldErrors["phone"])
            }
            Button("Payer \(Money.format(model.amount))") { Task { await model.pay() } }
                .buttonStyle(.primary)
                .disabled(!model.canPay)
                .accessibilityIdentifier("payment.pay")
            Label("Paiement sécurisé. Le statut est confirmé par l'opérateur avant activation.", systemImage: "lock.shield")
                .font(.caption).foregroundStyle(DS.Palette.textSecondary)
        }
    }

    private var waiting: some View {
        VStack(spacing: DS.Spacing.m) {
            ProgressView().controlSize(.large)
            Text(model.phase == .launching ? "Ouverture de \(model.method?.label ?? "l'opérateur")…" : "Confirmation du paiement en cours…")
                .font(.headline)
                .multilineTextAlignment(.center)
            Text("Validez le paiement dans l'application de l'opérateur, puis revenez ici. Ne fermez pas ASSUR+.")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            if let reference = model.payment?.reference {
                Text("Référence \(reference)").font(.caption.monospaced()).foregroundStyle(DS.Palette.textSecondary)
            }
        }
        .frame(maxWidth: .infinity)
        .card()
        .accessibilityIdentifier("payment.waiting")
    }

    @ViewBuilder private var result: some View {
        if let payment = model.payment {
            VStack(alignment: .leading, spacing: DS.Spacing.m) {
                PaymentSummary(payment: payment)
                if payment.isSuccessful {
                    Button("Continuer") { onSuccess() }
                        .buttonStyle(.primary)
                        .accessibilityIdentifier("payment.continue")
                } else if payment.isFinal {
                    Button("Réessayer avec un autre moyen") { model.retry() }.buttonStyle(.primary)
                } else {
                    MessageBanner(message: Message(level: .info, text: String(localized: "Paiement toujours en attente de confirmation. Vous serez notifié dès sa validation.")))
                    Button("Vérifier à nouveau") { Task { await model.pollUntilFinal() } }.buttonStyle(.secondary)
                }
            }
        }
    }
}

struct PaymentMethodIcon: View {
    let kind: PaymentMethod.Kind

    var body: some View {
        let (symbol, color): (String, Color) = switch kind {
        case .wave: ("water.waves", Color(hex: 0x1DC8FF))
        case .orangeMoney: ("iphone.gen3", Color(hex: 0xFF7900))
        case .card: ("creditcard", DS.Palette.teal)
        case .other: ("banknote", DS.Palette.textSecondary)
        }
        Image(systemName: symbol)
            .font(.title3)
            .foregroundStyle(.white)
            .frame(width: 40, height: 40)
            .background(color, in: RoundedRectangle(cornerRadius: DS.Radius.s))
            .accessibilityHidden(true)
    }
}

struct PaymentSummary: View {
    let payment: Payment

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            HStack {
                Text(payment.purposeLabel).font(.headline)
                Spacer()
                StatusBadge(payment.status).accessibilityIdentifier("payment.status")
            }
            InfoRow(label: String(localized: "Montant"), value: Money.format(payment.amount), emphasized: true)
            InfoRow(label: String(localized: "Moyen"), value: payment.methodLabel)
            InfoRow(label: String(localized: "Référence"), value: payment.reference)
            InfoRow(label: String(localized: "Date"), value: DateText.dateTime(payment.createdAt))
            if let policy = payment.policyNumber {
                InfoRow(label: String(localized: "Contrat"), value: policy)
            }
        }
        .card()
    }
}

struct PaymentHistoryView: View {
    var body: some View {
        WithModel({ env in
            RemoteResource(cache: env.cache, key: .payments) { try await env.api.payments() }
        }) { payments in
            ScrollView {
                LoadableContent(value: payments.value, isLoading: payments.isLoading, error: payments.error, retry: { Task { await payments.load() } }) { list in
                    if list.isEmpty {
                        EmptyStateView(title: String(localized: "Aucun paiement"), message: String(localized: "Vos paiements de cotisation apparaîtront ici."), symbol: "creditcard")
                    } else {
                        LazyVStack(spacing: DS.Spacing.m) {
                            ForEach(list) { payment in
                                NavigationLink(value: Route.payment(id: payment.id)) {
                                    HStack(spacing: DS.Spacing.m) {
                                        VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                                            Text(payment.purposeLabel).font(.subheadline.weight(.semibold))
                                            Text("\(payment.methodLabel) · \(DateText.short(payment.createdAt))")
                                                .font(.caption).foregroundStyle(DS.Palette.textSecondary)
                                            Text(payment.reference).font(.caption2.monospaced()).foregroundStyle(DS.Palette.textSecondary)
                                        }
                                        Spacer()
                                        VStack(alignment: .trailing, spacing: DS.Spacing.xs) {
                                            Text(Money.format(payment.amount)).font(.subheadline.weight(.semibold).monospacedDigit())
                                            StatusBadge(payment.status)
                                        }
                                    }
                                    .card(padding: DS.Spacing.m)
                                }
                                .buttonStyle(.plain)
                            }
                        }
                    }
                } placeholder: {
                    VStack(spacing: DS.Spacing.m) { SkeletonCard(lines: 2); SkeletonCard(lines: 2) }
                }
                .padding(DS.Spacing.l)
            }
            .screenBackground()
            .refreshable { await payments.refresh() }
            .task { await payments.load() }
        }
        .navigationTitle("Paiements")
    }
}

struct PaymentDetailView: View {
    let paymentId: String

    var body: some View {
        WithModel({ env in
            RemoteResource { try await env.api.payment(id: paymentId) }
        }) { payment in
            ScrollView {
                LoadableContent(value: payment.value, isLoading: payment.isLoading, error: payment.error, retry: { Task { await payment.load() } }) { value in
                    PaymentSummary(payment: value)
                } placeholder: {
                    SkeletonCard(lines: 5)
                }
                .padding(DS.Spacing.l)
            }
            .screenBackground()
            .refreshable { await payment.refresh() }
            .task { await payment.load() }
        }
        .navigationTitle("Paiement")
        .navigationBarTitleDisplayMode(.inline)
    }
}
