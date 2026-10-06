import SwiftUI

struct NewClaimFlowView: View {
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            WithModel(NewClaimViewModel.init) { model in
                NewClaimSteps(model: model)
            }
            .navigationTitle("Déclarer un sinistre")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Fermer") { dismiss() }.accessibilityIdentifier("claim.close")
                }
            }
        }
        .interactiveDismissDisabled()
    }
}

private struct NewClaimSteps: View {
    @Bindable var model: NewClaimViewModel
    @Environment(AppEnvironment.self) private var env
    @Environment(\.dismiss) private var dismiss
    @State private var showPicker = false

    var body: some View {
        VStack(spacing: 0) {
            if model.step != .done {
                StepProgress(current: model.progressIndex, total: NewClaimViewModel.Step.allCases.count, title: model.step.title)
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
        }
        .screenBackground()
        .toolbar {
            if [.type, .capture].contains(model.step) {
                ToolbarItem(placement: .topBarLeading) {
                    Button("Retour") { model.back() }
                }
            }
        }
        .task { if model.beneficiaries.isEmpty { await model.loadOptions() } }
        .documentPicker(isPresented: $showPicker, title: String(localized: "Ajouter le justificatif"), baseName: "facture") { document in
            Task { await model.setReceipt(document) }
        }
        .alert("Reprendre la déclaration ?", isPresented: Binding(get: { model.pendingDraft != nil && model.step == .beneficiary }, set: { _ in })) {
            Button("Reprendre") { Task { await model.resumeDraft() } }
            Button("Recommencer", role: .destructive) { model.discardDraft() }
        } message: {
            Text("Une déclaration commencée le \(DateText.dateTime(model.pendingDraft?.updatedAt ?? .now)) n'a pas été envoyée.")
        }
    }

    @ViewBuilder private var content: some View {
        switch model.step {
        case .beneficiary: beneficiaryStep
        case .type: typeStep
        case .capture: captureStep
        case .upload: uploadStep
        case .ocr: ocrStep
        case .review: ClaimReviewForm(model: model)
        case .done: doneStep
        }
    }

    private var beneficiaryStep: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text("Pour qui sont ces soins ?").font(DS.Typography.title)
            if model.isLoadingOptions && model.beneficiaries.isEmpty {
                SkeletonCard(lines: 2)
            }
            ForEach(model.beneficiaries) { beneficiary in
                SelectableRow(
                    title: beneficiary.fullName, subtitle: beneficiary.relationLabel,
                    leading: AnyView(InitialsAvatar(name: beneficiary.fullName, size: 40)),
                    isSelected: model.beneficiaryId == beneficiary.id
                ) { model.selectBeneficiary(beneficiary.id) }
                .accessibilityIdentifier("claim.beneficiary.\(beneficiary.id)")
            }
        }
    }

    private var typeStep: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text("Quel type de prestation ?").font(DS.Typography.title)
            LazyVGrid(columns: [GridItem(.adaptive(minimum: 150), spacing: DS.Spacing.m)], spacing: DS.Spacing.m) {
                ForEach(model.claimTypes) { type in
                    Button { model.selectType(type.code) } label: {
                        VStack(spacing: DS.Spacing.s) {
                            Image(systemName: type.symbol ?? "doc.text")
                                .font(.title2)
                                .foregroundStyle(DS.Palette.accent)
                            Text(type.label).font(.subheadline.weight(.semibold)).multilineTextAlignment(.center)
                                .foregroundStyle(DS.Palette.textPrimary)
                        }
                        .frame(maxWidth: .infinity, minHeight: 96)
                        .padding(DS.Spacing.s)
                        .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
                        .overlay(RoundedRectangle(cornerRadius: DS.Radius.l).strokeBorder(model.typeCode == type.code ? DS.Palette.accent : .clear, lineWidth: 2))
                    }
                    .buttonStyle(.plain)
                    .accessibilityIdentifier("claim.type.\(type.code)")
                }
            }
        }
    }

    private var captureStep: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            Text("Photographiez la facture").font(DS.Typography.title)
            if let type = model.selectedType, !type.requiredDocuments.isEmpty {
                VStack(alignment: .leading, spacing: DS.Spacing.s) {
                    Text("Documents demandés").font(.subheadline.weight(.semibold))
                    ForEach(type.requiredDocuments, id: \.self) { Label($0, systemImage: "doc.text").font(.callout) }
                }
                .card()
            }
            VStack(alignment: .leading, spacing: DS.Spacing.s) {
                Label("Posez le document à plat, bien éclairé", systemImage: "sun.max")
                Label("Les 4 coins doivent être visibles", systemImage: "viewfinder")
                Label("Plusieurs pages ? Scannez-les à la suite", systemImage: "doc.on.doc")
            }
            .font(.callout)
            .foregroundStyle(DS.Palette.textSecondary)
            Button {
                showPicker = true
            } label: {
                Label("Ajouter le justificatif", systemImage: "camera.viewfinder")
            }
            .buttonStyle(.primary)
            .accessibilityIdentifier("claim.addReceipt")
        }
    }

    private var uploadStep: some View {
        VStack(spacing: DS.Spacing.l) {
            receiptPreview
            VStack(alignment: .leading, spacing: DS.Spacing.s) {
                Text("Envoi du justificatif…").font(.headline)
                ProgressView(value: model.uploadProgress)
                    .tint(DS.Palette.accent)
                    .accessibilityIdentifier("claim.uploadProgress")
                Text("\(Int(model.uploadProgress * 100)) %").font(.caption.monospacedDigit()).foregroundStyle(DS.Palette.textSecondary)
                Text("L'envoi reprend automatiquement si la connexion est interrompue.")
                    .font(.caption).foregroundStyle(DS.Palette.textSecondary)
            }
            .card()
            if model.error != nil, !model.isWorking {
                Button("Réessayer l'envoi") { Task { await model.upload() } }.buttonStyle(.primary)
                Button("Terminer plus tard") { model.saveDraft(); dismiss() }.buttonStyle(.secondary)
            }
        }
    }

    private var ocrStep: some View {
        VStack(spacing: DS.Spacing.l) {
            receiptPreview
            VStack(spacing: DS.Spacing.m) {
                ProgressView(value: model.ocr?.progress ?? 0.1)
                    .tint(DS.Palette.accent)
                Text(model.ocr?.message ?? String(localized: "Lecture automatique de votre justificatif…"))
                    .font(.headline)
                    .multilineTextAlignment(.center)
                Text("Cela prend généralement moins de 15 secondes.")
                    .font(.callout).foregroundStyle(DS.Palette.textSecondary)
                Button("Saisir manuellement") { model.startManualEntry() }
                    .font(.callout.weight(.semibold))
            }
            .card()
            .accessibilityIdentifier("claim.ocr")
        }
    }

    @ViewBuilder private var receiptPreview: some View {
        if let image = model.receipt?.preview {
            Image(uiImage: image)
                .resizable()
                .scaledToFit()
                .frame(maxHeight: 220)
                .clipShape(RoundedRectangle(cornerRadius: DS.Radius.m))
                .accessibilityLabel(Text("Aperçu du justificatif"))
        }
    }

    private var doneStep: some View {
        VStack(spacing: DS.Spacing.l) {
            Image(systemName: "checkmark.seal.fill")
                .font(.system(size: 64))
                .foregroundStyle(DS.Palette.success)
                .accessibilityHidden(true)
            Text("Déclaration envoyée").font(DS.Typography.title)
            if let number = model.submitted?.number {
                VStack(spacing: DS.Spacing.xs) {
                    Text("Numéro de sinistre").font(.callout).foregroundStyle(DS.Palette.textSecondary)
                    Text(number)
                        .font(.title2.monospacedDigit().weight(.bold))
                        .textSelection(.enabled)
                        .accessibilityIdentifier("claim.number")
                }
                .card()
            }
            Text("Vous serez notifié à chaque étape du traitement.")
                .font(.callout).foregroundStyle(DS.Palette.textSecondary).multilineTextAlignment(.center)
            Button("Suivre mon sinistre") {
                if let id = model.submitted?.id {
                    env.router.presentedSheet = nil
                    env.router.open(DeepLinkTarget(kind: .claim, id: id))
                }
            }
            .buttonStyle(.primary)
            .accessibilityIdentifier("claim.track")
            Button("Fermer") { dismiss() }.buttonStyle(.secondary)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, DS.Spacing.xl)
    }
}

struct SelectableRow: View {
    let title: String
    var subtitle: String?
    var leading: AnyView?
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: DS.Spacing.m) {
                leading
                VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                    Text(title).font(.headline).foregroundStyle(DS.Palette.textPrimary)
                    if let subtitle { Text(subtitle).font(.caption).foregroundStyle(DS.Palette.textSecondary) }
                }
                Spacer()
                Image(systemName: isSelected ? "checkmark.circle.fill" : "chevron.right")
                    .foregroundStyle(isSelected ? DS.Palette.accent : DS.Palette.textSecondary)
            }
            .padding(DS.Spacing.m)
            .background(DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.l))
            .overlay(RoundedRectangle(cornerRadius: DS.Radius.l).strokeBorder(isSelected ? DS.Palette.accent : DS.Palette.border.opacity(0.6), lineWidth: isSelected ? 2 : 1))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

/// Pre-filled fields with each confidence score; low-confidence values are highlighted for review.
private struct ClaimReviewForm: View {
    @Bindable var model: NewClaimViewModel

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.l) {
            Text("Vérifiez les informations").font(DS.Typography.title)
            if model.lowConfidenceCount > 0 {
                MessageBanner(message: Message(level: .warning, text: String(localized: "\(model.lowConfidenceCount) information(s) à vérifier en priorité (surlignées).")))
            } else if model.ocr?.state == .done {
                MessageBanner(message: Message(level: .success, text: String(localized: "Lecture réussie. Corrigez si besoin avant d'envoyer.")))
            }

            VStack(spacing: DS.Spacing.m) {
                ForEach($model.fields) { $field in
                    ConfidenceField(label: field.label, value: $field.value, confidence: field.confidence, isLow: model.isLowConfidence(field.confidence), keyboard: field.key == "total" ? .numberPad : .default, identifier: "claim.field.\(field.key)")
                }
            }

            VStack(alignment: .leading, spacing: DS.Spacing.m) {
                SectionHeader(title: String(localized: "Actes et médicaments"), actionTitle: String(localized: "Ajouter")) { model.addLine() }
                if model.lines.isEmpty {
                    Text("Aucune ligne détectée.").font(.callout).foregroundStyle(DS.Palette.textSecondary)
                }
                ForEach($model.lines) { $line in
                    VStack(alignment: .leading, spacing: DS.Spacing.s) {
                        HStack {
                            TextField("Acte ou médicament", text: $line.label).font(.subheadline.weight(.semibold))
                            if let confidence = line.confidence { ConfidenceBadge(confidence: confidence, isLow: model.isLowConfidence(confidence)) }
                            Button(role: .destructive) { model.removeLine(line.id) } label: { Image(systemName: "trash") }
                                .accessibilityLabel(Text("Supprimer la ligne"))
                        }
                        HStack(spacing: DS.Spacing.s) {
                            MiniField(label: String(localized: "Qté"), text: $line.quantity)
                            MiniField(label: String(localized: "Prix unitaire"), text: $line.unitPrice)
                            MiniField(label: String(localized: "Montant"), text: $line.amount)
                        }
                    }
                    .padding(DS.Spacing.m)
                    .background(model.isLowConfidence(line.confidence) ? DS.Palette.warningSoft : DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                }
            }

            Text("Les montants pris en charge et le reste à charge seront calculés par votre assureur après analyse.")
                .font(.caption).foregroundStyle(DS.Palette.textSecondary)

            Button("Envoyer la déclaration") { Task { await model.submit() } }
                .buttonStyle(.primary(loading: model.isWorking))
                .disabled(!model.canSubmit)
                .accessibilityIdentifier("claim.submit")
        }
    }
}

private struct ConfidenceField: View {
    let label: String
    @Binding var value: String
    let confidence: Double?
    let isLow: Bool
    var keyboard: UIKeyboardType = .default
    var identifier = ""

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.xs) {
            HStack {
                Text(label).font(.subheadline.weight(.medium)).foregroundStyle(DS.Palette.textSecondary)
                Spacer()
                if let confidence { ConfidenceBadge(confidence: confidence, isLow: isLow) }
            }
            TextField(label, text: $value)
                .keyboardType(keyboard)
                .accessibilityIdentifier(identifier)
                .padding(.horizontal, DS.Spacing.m)
                .frame(minHeight: 48)
                .background(isLow ? DS.Palette.warningSoft : DS.Palette.surface, in: RoundedRectangle(cornerRadius: DS.Radius.m))
                .overlay(RoundedRectangle(cornerRadius: DS.Radius.m).strokeBorder(isLow ? DS.Palette.warning : DS.Palette.border, lineWidth: isLow ? 1.5 : 1))
        }
        .accessibilityElement(children: .contain)
        .accessibilityHint(isLow ? Text("Confiance faible, à vérifier") : Text(""))
    }
}

private struct ConfidenceBadge: View {
    let confidence: Double
    let isLow: Bool

    var body: some View {
        Label(Percent.confidence(confidence), systemImage: isLow ? "exclamationmark.triangle.fill" : "checkmark.circle")
            .font(.caption2.weight(.semibold).monospacedDigit())
            .foregroundStyle(isLow ? DS.Palette.warning : DS.Palette.success)
            .accessibilityLabel(Text("Confiance \(Percent.confidence(confidence))"))
    }
}

private struct MiniField: View {
    let label: String
    @Binding var text: String

    var body: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label).font(.caption2).foregroundStyle(DS.Palette.textSecondary)
            TextField(label, text: $text)
                .keyboardType(.numberPad)
                .font(.callout.monospacedDigit())
                .padding(DS.Spacing.s)
                .background(DS.Palette.surfaceMuted, in: RoundedRectangle(cornerRadius: DS.Radius.s))
        }
    }
}
