import SwiftUI

struct SubscriptionFlowView: View {
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            WithModel(SubscriptionViewModel.init) { model in
                SubscriptionSteps(model: model)
            }
            .navigationTitle("Souscription")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Fermer") { dismiss() }
                }
            }
        }
        .interactiveDismissDisabled()
    }
}

private struct SubscriptionSteps: View {
    @Bindable var model: SubscriptionViewModel
    @Environment(AppEnvironment.self) private var env
    @State private var showDependentForm = false

    var body: some View {
        VStack(spacing: 0) {
            if model.step != .confirmation {
                StepProgress(current: model.progressIndex, total: SubscriptionViewModel.Step.allCases.count, title: model.step.title)
                    .padding([.horizontal, .top], DS.Spacing.l)
                    .padding(.bottom, DS.Spacing.s)
            }
            ScrollView {
                VStack(alignment: .leading, spacing: DS.Spacing.l) {
                    if let error = model.error {
                        MessageBanner(message: Message(level: .error, text: error.userMessage))
                    }
                    content
                }
                .padding(DS.Spacing.l)
            }
            .scrollDismissesKeyboard(.interactively)
            if [.guarantees, .premium].contains(model.step) { quoteBar }
            if model.step < .payment { footer }
        }
        .screenBackground()
        .toolbar {
            if model.step > .identification && model.step < .payment {
                ToolbarItem(placement: .topBarLeading) {
                    Button("Retour") { model.back() }.accessibilityIdentifier("subscription.back")
                }
            }
        }
        .task { await model.load() }
        .onChange(of: model.quoteRequest) { _, _ in
            if model.step >= .guarantees && model.step <= .premium { model.scheduleQuote() }
        }
        .sheet(isPresented: $showDependentForm) {
            DependentFormSheet { model.addDependent($0) }
        }
    }

    @ViewBuilder private var content: some View {
        switch model.step {
        case .identification: identification
        case .personal: personal
        case .health: health
        case .guarantees: guarantees
        case .premium: premium
        case .validation: validation
        case .payment:
            if let payment = model.payment {
                PaymentStepView(model: payment) { Task { await model.paymentSucceeded() } }
            }
        case .confirmation: confirmation
        }
    }

    private var footer: some View {
        Button(model.step == .validation ? "Valider et payer" : "Continuer") { Task { await model.next() } }
            .buttonStyle(.primary(loading: model.isCreatingPolicy))
            .disabled(!model.canContinue)
            .padding(DS.Spacing.l)
            .background(DS.Palette.background)
            .accessibilityIdentifier("subscription.next")
    }

    // MARK: Steps

    private var identification: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text("Souscrivez en moins de 3 minutes").font(DS.Typography.title)
            if let user = model.user {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    InfoRow(label: String(localized: "Souscripteur"), value: user.fullName, emphasized: true)
                    InfoRow(label: String(localized: "Téléphone vérifié"), value: PhoneNumber.display(user.phone))
                }
                .card()
            }
            Text("Vous allez renseigner vos informations, répondre au questionnaire de santé, choisir vos garanties puis payer en ligne.")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary)
        }
    }

    private var personal: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            LabeledField(label: String(localized: "Date de naissance")) {
                DatePicker("Date de naissance", selection: $model.birthDate, in: ...Date.now, displayedComponents: .date)
                    .labelsHidden().frame(maxWidth: .infinity, alignment: .leading)
            }
            Picker("Sexe", selection: $model.gender) {
                ForEach(Gender.allCases) { Text($0.label).tag($0) }
            }
            .pickerStyle(.segmented)
            LabeledField(label: String(localized: "E-mail (facultatif)")) {
                TextField("nom@exemple.com", text: $model.email)
                    .keyboardType(.emailAddress).textInputAutocapitalization(.never).textContentType(.emailAddress)
            }
            LabeledField(label: String(localized: "Ville")) {
                TextField("Dakar", text: $model.city).textContentType(.addressCity)
            }
        }
    }

    private var health: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            Text("Vos réponses sont confidentielles et transmises uniquement à l'assureur.")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary)
            if model.questionnaire == nil { SkeletonCard(lines: 4) }
            ForEach(model.visibleQuestions) { question in
                QuestionView(question: question, answer: Binding(
                    get: { model.answers[question.id] ?? "" },
                    set: { model.answers[question.id] = $0 }))
            }
        }
    }

    private var guarantees: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            Text("Formule").font(.headline)
            ForEach(model.products) { product in
                ProductCard(product: product, isSelected: model.productId == product.id) { model.select(product: product) }
                    .accessibilityIdentifier("subscription.product.\(product.id)")
            }
            if let product = model.product {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    Text("Taux de couverture").font(.headline)
                    Picker("Taux de couverture", selection: Binding(get: { model.coverageRate ?? 0 }, set: { model.coverageRate = $0 })) {
                        ForEach(product.coverageRates, id: \.self) { Text(Percent.format($0)).tag($0) }
                    }
                    .pickerStyle(.segmented)
                    .accessibilityIdentifier("subscription.rate")
                }
                if product.territorialities.count > 1 {
                    VStack(alignment: .leading, spacing: DS.Spacing.s) {
                        Text("Territorialité").font(.headline)
                        Picker("Territorialité", selection: Binding(get: { model.territoriality ?? "" }, set: { model.territoriality = $0 })) {
                            ForEach(product.territorialities) { Text($0.label).tag($0.code) }
                        }
                        .pickerStyle(.segmented)
                    }
                }
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    SectionHeader(title: String(localized: "Ayants droit"), actionTitle: model.canAddDependent ? String(localized: "Ajouter") : nil) {
                        showDependentForm = true
                    }
                    if model.dependents.isEmpty {
                        Text("Ajoutez votre conjoint(e) ou vos enfants pour les couvrir.").font(.callout).foregroundStyle(DS.Palette.textSecondary)
                    }
                    ForEach(model.dependents) { member in
                        HStack {
                            InitialsAvatar(name: "\(member.firstName) \(member.lastName)", size: 36)
                            VStack(alignment: .leading) {
                                Text("\(member.firstName) \(member.lastName)").font(.subheadline.weight(.semibold))
                                Text("\(member.relation.label) · \(DateText.day(member.birthDate))").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                            }
                            Spacer()
                            Button(role: .destructive) { model.removeDependent(member.id) } label: { Image(systemName: "minus.circle") }
                                .accessibilityLabel(Text("Retirer \(member.firstName)"))
                        }
                    }
                }
                .card()
            }
        }
    }

    private var premium: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            if let quote = model.quote {
                ForEach(quote.messages, id: \.self) { MessageBanner(message: $0) }
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    Text("Détail de la prime").font(.headline)
                    ForEach(quote.perMember, id: \.self) { InfoRow(label: $0.label, value: Money.format($0.amount)) }
                    ForEach(quote.surcharges, id: \.self) { InfoRow(label: $0.label, value: "+ " + Money.format($0.amount)) }
                    ForEach(quote.fees, id: \.self) { InfoRow(label: $0.label, value: Money.format($0.amount)) }
                    Divider()
                    InfoRow(label: String(localized: "Total \(quote.periodLabel)"), value: Money.format(quote.totalPremium), emphasized: true)
                    Text("Devis valable jusqu'au \(DateText.day(quote.validUntil))").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                }
                .card()
            } else if let error = model.quoteError {
                ErrorStateView(error: error) { model.scheduleQuote() }
            } else {
                SkeletonCard(lines: 4)
            }
        }
    }

    private var validation: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            if let product = model.product, let quote = model.quote {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    Text("Récapitulatif").font(.headline)
                    InfoRow(label: String(localized: "Formule"), value: product.name)
                    InfoRow(label: String(localized: "Couverture"), value: Percent.format(model.coverageRate ?? 0))
                    InfoRow(label: String(localized: "Bénéficiaires"), value: "\(model.dependents.count + 1)")
                    InfoRow(label: String(localized: "Prime \(quote.periodLabel)"), value: Money.format(quote.totalPremium), emphasized: true)
                }
                .card()
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    Text("Conditions particulières").font(.headline)
                    ForEach(quote.specialConditions, id: \.self) { Text("• \($0)").font(.callout) }
                    Text("Version \(quote.conditionsVersion)").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                }
                .card()
                Toggle("J'ai lu et j'accepte les conditions particulières", isOn: $model.acceptedConditions)
                    .tint(DS.Palette.accent)
                    .accessibilityIdentifier("subscription.acceptConditions")
            }
        }
    }

    private var confirmation: some View {
        VStack(spacing: DS.Spacing.l) {
            Image(systemName: "checkmark.seal.fill").font(.system(size: 64)).foregroundStyle(DS.Palette.success)
                .accessibilityHidden(true)
            Text("Bienvenue chez ASSUR+ !").font(DS.Typography.title)
            if let policy = model.createdPolicy {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    InfoRow(label: String(localized: "N° de contrat"), value: policy.number, emphasized: true)
                        .accessibilityIdentifier("subscription.policyNumber")
                    InfoRow(label: String(localized: "Début de couverture"), value: DateText.day(policy.startDate))
                }
                .card()
            }
            Text("Votre contrat est émis et votre carte tiers-payant est disponible. Les conditions particulières sont consultables dans « Mon contrat ».")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            Button("Voir ma carte") {
                env.router.presentedSheet = nil
                env.router.selectedTab = .card
            }
            .buttonStyle(.primary)
            .accessibilityIdentifier("subscription.showCard")
            Button("Aller à l'accueil") { env.router.presentedSheet = nil }.buttonStyle(.secondary)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, DS.Spacing.xl)
    }

    /// Live premium while the user adjusts the coverage.
    private var quoteBar: some View {
        HStack {
            VStack(alignment: .leading, spacing: 0) {
                Text("Prime estimée").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                if let quote = model.quote {
                    Text(Money.format(quote.totalPremium))
                        .font(DS.Typography.amountSmall)
                        .contentTransition(.numericText())
                        .accessibilityIdentifier("subscription.total")
                    Text(quote.periodLabel).font(.caption2).foregroundStyle(DS.Palette.textSecondary)
                } else {
                    SkeletonBlock(height: 20, width: 120)
                }
            }
            Spacer()
            if model.isQuoting {
                ProgressView()
            } else if let quote = model.quote, !quote.eligible {
                StatusBadge(text: String(localized: "Non éligible"), tone: .danger)
            }
        }
        .padding(.horizontal, DS.Spacing.l)
        .padding(.vertical, DS.Spacing.m)
        .background(DS.Palette.surface)
        .overlay(alignment: .top) { Divider() }
        .animation(.default, value: model.quote?.totalPremium)
    }
}

private struct QuestionView: View {
    let question: HealthQuestionnaire.Question
    @Binding var answer: String

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.s) {
            Text(question.label).font(.subheadline.weight(.semibold))
            if let help = question.help { Text(help).font(.caption).foregroundStyle(DS.Palette.textSecondary) }
            switch question.kind {
            case .boolean:
                Picker(question.label, selection: $answer) {
                    Text("Oui").tag("true")
                    Text("Non").tag("false")
                }
                .pickerStyle(.segmented)
                .accessibilityIdentifier("question.\(question.id)")
            case .singleChoice:
                Picker(question.label, selection: $answer) {
                    Text("Choisir…").tag("")
                    ForEach(question.options ?? []) { Text($0.label).tag($0.code) }
                }
                .pickerStyle(.menu)
            case .number:
                TextField("", text: $answer).keyboardType(.numberPad).textFieldStyle(.roundedBorder)
            case .text:
                TextField("", text: $answer, axis: .vertical).textFieldStyle(.roundedBorder)
            }
        }
        .card()
    }
}

private struct ProductCard: View {
    let product: Product
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: DS.Spacing.s) {
                HStack {
                    Text(product.name).font(.title3.weight(.bold))
                    if product.highlight == true { StatusBadge(text: String(localized: "Recommandée"), tone: .success) }
                    Spacer()
                    Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                        .font(.title2)
                        .foregroundStyle(isSelected ? DS.Palette.accent : DS.Palette.border)
                }
                if let tagline = product.tagline { Text(tagline).font(.callout).foregroundStyle(DS.Palette.textSecondary) }
                if let from = product.premiumFrom {
                    Text("À partir de \(Money.format(from)) / an").font(.footnote.weight(.semibold))
                }
                ForEach(product.guarantees) { guarantee in
                    Label {
                        Text("\(guarantee.label)\(guarantee.limitLabel.map { " — \($0)" } ?? "")").font(.caption)
                    } icon: {
                        Image(systemName: "checkmark").foregroundStyle(DS.Palette.success)
                    }
                }
            }
            .foregroundStyle(DS.Palette.textPrimary)
            .padding(DS.Spacing.l)
            .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
            .overlay(RoundedRectangle(cornerRadius: DS.Radius.l).strokeBorder(isSelected ? DS.Palette.accent : DS.Palette.border, lineWidth: isSelected ? 2 : 1))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

/// Adds a beneficiary to the quote (identity, relation, birth date).
struct DependentFormSheet: View {
    let onSave: (QuoteMember) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var firstName = ""
    @State private var lastName = ""
    @State private var relation: Relation = .child
    @State private var gender: Gender = .female
    @State private var birthDate = Calendar.current.date(byAdding: .year, value: -5, to: .now) ?? .now

    var body: some View {
        NavigationStack {
            Form {
                Picker("Lien", selection: $relation) {
                    ForEach(Relation.allCases) { Text($0.label).tag($0) }
                }
                TextField("Prénom", text: $firstName).textContentType(.givenName)
                TextField("Nom", text: $lastName).textContentType(.familyName)
                Picker("Sexe", selection: $gender) {
                    ForEach(Gender.allCases) { Text($0.label).tag($0) }
                }
                DatePicker("Date de naissance", selection: $birthDate, in: ...Date.now, displayedComponents: .date)
            }
            .navigationTitle("Ajouter un ayant droit")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Annuler") { dismiss() } }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Ajouter") {
                        onSave(QuoteMember(firstName: firstName.trimmingCharacters(in: .whitespaces), lastName: lastName.trimmingCharacters(in: .whitespaces), relation: relation, birthDate: LocalDay(date: birthDate), gender: gender))
                        dismiss()
                    }
                    .disabled(firstName.trimmingCharacters(in: .whitespaces).isEmpty || lastName.trimmingCharacters(in: .whitespaces).isEmpty)
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
}
