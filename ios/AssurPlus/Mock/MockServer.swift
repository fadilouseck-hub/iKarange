import Foundation

/// In-process fake backend for the MockAPI configuration and UI tests. It implements the OpenAPI contract
/// on top of the JSON fixtures and keeps state for the session (registration, quotes, payments, claims…).
///
/// Everything "business" in here (quote amounts, statuses progressing) is FAKE server behaviour, kept
/// out of the app on purpose. Test credentials: see `ios/README.md`. OTP code is always `123456`.
actor MockServer: HTTPTransport {
    static let baseURL = URL(string: "https://mock.assurplus.local/v1")!
    static let otpCode = "123456"

    struct Household {
        var policy: PolicyDetail?
        var limits: Limits?
        var card: MemberCard
        var dependents: DependentsResponse
        var claims: [Claim]
        var payments: [Payment]
        var vault: [VaultDocument]
        var notifications: [AppNotification]
        var preferences: NotificationPreferences
    }

    struct Account {
        var me: Me
        var password: String?
        var householdId: String
        var beneficiaryId: String
    }

    private let latency: Duration
    private let qrLifetime: TimeInterval
    private let catalog: MockCatalogFixture
    private let providers: [Provider]

    private var accounts: [String: Account] = [:] // by user id
    private var households: [String: Household] = [:]
    private var accessTokens: [String: String] = [:]
    private var refreshTokens: [String: String] = [:]
    private var otpRequests: [String: (phone: String, purpose: OTPPurpose)] = [:]
    private var verifications: [String: String] = [:] // token → phone
    private var quotes: [String: (request: QuoteRequest, quote: Quote)] = [:]
    private var pendingPolicies: [String: (userId: String, quoteId: String, number: String)] = [:]
    private var paymentPolls: [String: Int] = [:]
    private var ocrPolls: [String: Int] = [:]
    private var claimPolls: [String: Int] = [:]
    private var uploads: [String: (size: Int, data: Data)] = [:]
    private var counter = 1000

    init(latency: Duration = .milliseconds(350), qrLifetime: TimeInterval = 60, bundle: Bundle = .main) {
        self.latency = latency
        self.qrLifetime = qrLifetime
        // Fixtures ship with the app; failing to decode them is a programming error caught by unit tests.
        let account = try! MockFixtures.account(bundle: bundle)
        catalog = try! MockFixtures.catalog(bundle: bundle)
        providers = (try? MockFixtures.providers(bundle: bundle).providers) ?? []

        households["hh_demo"] = Household(
            policy: account.policy, limits: account.limits, card: account.card, dependents: account.dependents,
            claims: account.claims, payments: account.payments, vault: account.vault,
            notifications: account.notifications, preferences: catalog.notificationPreferences)
        let beneficiaryIds = ["usr_principal": "ben_awa", "usr_dependent": "ben_moussa"]
        for user in account.users {
            accounts[user.me.id] = Account(
                me: user.me, password: user.password, householdId: "hh_demo",
                beneficiaryId: beneficiaryIds[user.me.id] ?? "ben_awa")
        }
    }

    // MARK: Transport

    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        try await Task.sleep(for: latency)
        let url = request.url ?? Self.baseURL
        let path = url.path.replacingOccurrences(of: Self.baseURL.path + "/", with: "")
        let parts = path.split(separator: "/").map(String.init)
        let method = HTTPMethod(rawValue: request.httpMethod ?? "GET") ?? .get
        let query = Dictionary(
            (URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []).map { ($0.name, $0.value ?? "") },
            uniquingKeysWith: { $1 })
        do {
            let reply = try route(method: method, parts: parts, query: query, request: request)
            return (reply.body, HTTPURLResponse(url: url, statusCode: reply.status, httpVersion: "HTTP/1.1", headerFields: ["Content-Type": reply.contentType])!)
        } catch let failure as Failure {
            let body = try JSONSerialization.data(withJSONObject: ["error": ["code": failure.code, "message": failure.message, "fields": failure.fields]])
            return (body, HTTPURLResponse(url: url, statusCode: failure.status, httpVersion: "HTTP/1.1", headerFields: ["Content-Type": "application/json"])!)
        }
    }

    private struct Reply {
        var status = 200
        var body: Data
        var contentType = "application/json"
    }

    private struct Failure: Error {
        let status: Int
        let code: String
        let message: String
        var fields: [String: String] = [:]
    }

    private func json(_ value: some Encodable, status: Int = 200) throws -> Reply {
        Reply(status: status, body: try JSONCoding.encoder().encode(value))
    }

    private var empty: Reply { Reply(status: 204, body: Data()) }

    private func body<T: Decodable>(_ type: T.Type, _ request: URLRequest) throws -> T {
        guard let data = request.httpBody, let value = try? JSONCoding.decoder().decode(T.self, from: data) else {
            throw Failure(status: 400, code: "bad_request", message: "Requête invalide.")
        }
        return value
    }

    private func nextId(_ prefix: String) -> String {
        counter += 1
        return "\(prefix)_\(counter)"
    }

    // MARK: Routing

    private func route(method: HTTPMethod, parts: [String], query: [String: String], request: URLRequest) throws -> Reply {
        switch (method, parts.first) {
        case (_, "auth"): return try routeAuth(method: method, parts: Array(parts.dropFirst()), request: request)
        case (.get, "legal"): return try json(catalog.legal)
        default: break
        }

        let userId = try authenticate(request)
        let (template, ids) = Self.template(for: parts)
        switch (method, template) {
        case (.get, "me"): return try json(account(userId).me)
        case (.patch, "me"):
            let update = try body(MeUpdate.self, request)
            try mutateAccount(userId) {
                if let email = update.email { $0.me.email = email.isEmpty ? nil : email }
                if let address = update.address { $0.me.address = address }
                if let city = update.city { $0.me.city = city }
                if let language = update.preferredLanguage { $0.me.preferredLanguage = language }
            }
            return try json(account(userId).me)
        case (.put, "me/photo"):
            return try json(account(userId).me)
        case (.post, "me/password"):
            let change = try body(PasswordChangeRequest.self, request)
            guard try account(userId).password == change.currentPassword else {
                throw Failure(status: 422, code: "invalid_password", message: "Le mot de passe actuel est incorrect.", fields: ["currentPassword": "Mot de passe incorrect."])
            }
            try mutateAccount(userId) { $0.password = change.newPassword }
            return empty
        case (.get, "me/notification-preferences"): return try json(household(userId).preferences)
        case (.put, "me/notification-preferences"):
            let prefs = try body(NotificationPreferences.self, request)
            try mutateHousehold(userId) { $0.preferences = prefs }
            return try json(prefs)
        case (.post, "me/deletion-request"):
            return try json(ServerStatus(code: "requested", label: "Demande enregistrée — traitement sous 30 jours"))
        case (.get, "me/dashboard"): return try json(dashboard(userId))
        case (.get, "me/card"): return try json(card(userId))
        case (.get, "me/card/qr-token"):
            let token = "AP1." + UUID().uuidString.replacingOccurrences(of: "-", with: "").lowercased() + ".sig"
            return try json(QRToken(token: token, expiresAt: Date.now.addingTimeInterval(qrLifetime)))
        case (.get, "me/card/wallet-pass"):
            return Reply(body: Data("MOCK-PKPASS".utf8), contentType: "application/vnd.apple.pkpass")

        case (.get, "products"): return try json(catalog.products)
        case (.get, "health-questionnaire"): return try json(catalog.questionnaire)
        case (.post, "quotes"): return try json(makeQuote(try body(QuoteRequest.self, request)), status: 201)
        case (.post, "policies"): return try json(createPolicy(userId, try body(PolicyCreateRequest.self, request)), status: 201)
        case (.get, "policies/:id"):
            guard let policy = try household(userId).policy else { throw notFound }
            return try json(policy)
        case (.get, "policies/:id/conditions.pdf"):
            guard let policy = try household(userId).policy else { throw notFound }
            return Reply(body: conditionsPDF(policy), contentType: "application/pdf")
        case (.post, "policies/:id/termination-requests"):
            let status = ServerStatus(code: "requested", label: "Demande de résiliation envoyée")
            return try json(status, status: 201)

        case (.get, "dependents"): return try json(household(userId).dependents)
        case (.post, "dependents/requests"):
            let add = try body(DependentAddRequest.self, request)
            let pending = DependentRequest(
                id: nextId("dreq"), kind: .add, fullName: "\(add.firstName) \(add.lastName)",
                status: ServerStatus(code: "pending", label: "En attente de validation"), createdAt: .now,
                message: "Votre gestionnaire vérifiera les pièces justificatives.")
            try mutateHousehold(userId) { $0.dependents = DependentsResponse(dependents: $0.dependents.dependents, requests: [pending] + $0.dependents.requests, canRequestChanges: true, info: $0.dependents.info) }
            return try json(pending, status: 201)
        case (.delete, "dependents/:id"):
            let id = ids[0]
            let removal = try body(DependentRemoveRequest.self, request)
            guard let dependent = try household(userId).dependents.dependents.first(where: { $0.id == id }) else { throw notFound }
            let pending = DependentRequest(
                id: nextId("dreq"), kind: .remove, fullName: dependent.fullName,
                status: ServerStatus(code: "pending", label: "En attente de validation"), createdAt: .now,
                message: removal.reason)
            try mutateHousehold(userId) { $0.dependents = DependentsResponse(dependents: $0.dependents.dependents, requests: [pending] + $0.dependents.requests, canRequestChanges: true, info: $0.dependents.info) }
            return try json(pending, status: 202)

        case (.get, "payment-methods"): return try json(catalog.paymentMethods)
        case (.post, "payments"): return try json(createPayment(userId, try body(PaymentCreateRequest.self, request)), status: 201)
        case (.get, "payments"): return try json(household(userId).payments)
        case (.get, "payments/:id"):
            let id = ids[0]
            return try json(pollPayment(userId, id: id))

        case (.get, "claim-types"): return try json(catalog.claimTypes)
        case (.get, "claims"): return try json(visibleClaims(userId).map(summary))
        case (.post, "claims"): return try json(createClaim(userId, try body(ClaimCreateRequest.self, request)), status: 201)
        case (.get, "claims/:id"):
            let id = ids[0]
            return try json(pollClaim(userId, id: id))
        case (.patch, "claims/:id"):
            let id = ids[0]
            return try json(updateClaim(userId, id: id, try body(ClaimUpdate.self, request)))
        case (.post, "claims/:id/documents"):
            let id = ids[0]
            return try json(attachDocument(userId, claimId: id, try body(ClaimDocumentAttach.self, request)), status: 201)
        case (.get, "claims/:id/ocr"):
            let id = ids[0]
            return try json(pollOCR(id))
        case (.post, "claims/:id/submit"):
            let id = ids[0]
            return try json(submitClaim(userId, id: id))

        case (.get, "vault/documents"):
            let docs = try household(userId).vault
            return try json(query["category"].map { category in docs.filter { $0.category.rawValue == category } } ?? docs)
        case (.post, "vault/documents"):
            let create = try body(VaultDocumentCreate.self, request)
            guard let upload = uploads[create.uploadId] else { throw Failure(status: 422, code: "upload_missing", message: "Fichier introuvable, veuillez réessayer.") }
            let doc = VaultDocument(
                id: nextId("vlt"), category: create.category, title: create.title, fileName: "document.jpg",
                mimeType: "image/jpeg", size: upload.size, createdAt: .now, beneficiaryName: try account(userId).me.fullName)
            try mutateHousehold(userId) { $0.vault.insert(doc, at: 0) }
            return try json(doc, status: 201)
        case (.get, "vault/documents/:id/file"):
            let id = ids[0]
            guard let doc = try household(userId).vault.first(where: { $0.id == id }) else { throw notFound }
            return Reply(body: MockDocuments.pdf(title: doc.title, lines: [doc.category.label, DateText.day(doc.createdAt)]), contentType: "application/pdf")
        case (.delete, "vault/documents/:id"):
            let id = ids[0]
            try mutateHousehold(userId) { $0.vault.removeAll { $0.id == id } }
            return empty

        case (.get, "providers"): return try json(ProvidersResponse(providers: searchProviders(query)))
        case (.get, "providers/network.pdf"):
            let list = searchProviders(query).map { "\($0.name) — \($0.type.label) — \($0.address), \($0.city) — \($0.phone ?? "")" }
            return Reply(body: MockDocuments.pdf(title: "Réseau de soins conventionné", lines: list), contentType: "application/pdf")

        case (.get, "notifications"):
            let list = try household(userId).notifications
            return try json(NotificationsResponse(notifications: list, unreadCount: list.filter { !$0.read }.count))
        case (.post, "notifications/read-all"):
            try mutateHousehold(userId) { $0.notifications = $0.notifications.map { var n = $0; n.read = true; return n } }
            return empty
        case (.post, "notifications/:id/read"):
            let id = ids[0]
            try mutateHousehold(userId) { $0.notifications = $0.notifications.map { var n = $0; if n.id == id { n.read = true }; return n } }
            return empty
        case (.post, "devices"): return empty

        case (.post, "uploads"):
            let create = try body(UploadCreateRequest.self, request)
            guard create.size <= 15_000_000 else { throw Failure(status: 413, code: "too_large", message: "Le fichier dépasse 15 Mo.") }
            let id = nextId("upl")
            uploads[id] = (create.size, Data())
            return try json(UploadSession(uploadId: id, chunkSize: 256 * 1024, offset: 0), status: 201)
        case (.get, "uploads/:id"):
            let id = ids[0]
            guard let upload = uploads[id] else { throw notFound }
            return try json(UploadSession(uploadId: id, chunkSize: 256 * 1024, offset: upload.data.count))
        case (.patch, "uploads/:id"):
            let id = ids[0]
            guard var upload = uploads[id] else { throw notFound }
            let offset = Int(request.value(forHTTPHeaderField: "Upload-Offset") ?? "") ?? -1
            guard offset == upload.data.count else {
                throw Failure(status: 409, code: "offset_mismatch", message: "Reprise du téléversement…")
            }
            upload.data.append(request.httpBody ?? Data())
            uploads[id] = upload
            return try json(UploadSession(uploadId: id, chunkSize: 256 * 1024, offset: upload.data.count))

        default:
            throw notFound
        }
    }

    private static let staticSegments: Set<String> = [
        "me", "dashboard", "card", "qr-token", "wallet-pass", "photo", "password", "notification-preferences",
        "deletion-request", "products", "health-questionnaire", "quotes", "policies", "conditions.pdf",
        "termination-requests", "dependents", "requests", "payment-methods", "payments", "claim-types", "claims",
        "documents", "ocr", "submit", "vault", "file", "providers", "network.pdf", "notifications", "read-all",
        "read", "devices", "uploads",
    ]

    /// `["claims", "clm_1", "ocr"]` → (`"claims/:id/ocr"`, `["clm_1"]`).
    static func template(for parts: [String]) -> (String, [String]) {
        var ids: [String] = []
        let template = parts.map { part -> String in
            if staticSegments.contains(part) { return part }
            ids.append(part)
            return ":id"
        }
        return (template.joined(separator: "/"), ids)
    }

    private var notFound: Failure { Failure(status: 404, code: "not_found", message: "Élément introuvable.") }

    // MARK: Auth

    private func routeAuth(method: HTTPMethod, parts: [String], request: URLRequest) throws -> Reply {
        switch (method, parts.joined(separator: "/")) {
        case (.post, "otp/send"):
            let send = try body(OTPSendRequest.self, request)
            guard let phone = PhoneNumber.e164(send.phone) else {
                throw Failure(status: 422, code: "invalid_phone", message: "Numéro de téléphone invalide.", fields: ["phone": "Numéro sénégalais à 9 chiffres attendu."])
            }
            let exists = accounts.values.contains { $0.me.phone == phone }
            if send.purpose == .register, exists {
                throw Failure(status: 409, code: "phone_taken", message: "Un compte existe déjà avec ce numéro. Connectez-vous.")
            }
            if send.purpose != .register, !exists {
                throw Failure(status: 404, code: "unknown_phone", message: "Aucun compte n'est associé à ce numéro.")
            }
            let id = nextId("otp")
            otpRequests[id] = (phone, send.purpose)
            let digits = PhoneNumber.nationalDigits(phone) ?? ""
            return try json(OTPChallenge(otpRequestId: id, expiresIn: 300, resendAfter: 30, maskedPhone: "+221 \(digits.prefix(2)) *** ** \(digits.suffix(2))"))
        case (.post, "otp/verify"):
            let verify = try body(OTPVerifyRequest.self, request)
            guard let otp = otpRequests[verify.otpRequestId] else {
                throw Failure(status: 410, code: "otp_expired", message: "Ce code a expiré. Demandez-en un nouveau.")
            }
            guard verify.code == Self.otpCode else {
                throw Failure(status: 422, code: "otp_invalid", message: "Code incorrect.", fields: ["code": "Code incorrect."])
            }
            let token = "ver_" + UUID().uuidString
            verifications[token] = otp.phone
            return try json(OTPVerification(verificationToken: token))
        case (.post, "login"):
            let login = try body(LoginRequest.self, request)
            let phone = PhoneNumber.e164(login.phone) ?? login.phone
            let match: Account?
            if let token = login.verificationToken {
                match = verifications[token] == phone ? accounts.values.first { $0.me.phone == phone } : nil
            } else {
                match = accounts.values.first { $0.me.phone == phone && $0.password == login.password }
            }
            guard let match else {
                throw Failure(status: 401, code: "invalid_credentials", message: "Numéro ou mot de passe incorrect.")
            }
            return try json(issueTokens(for: match))
        case (.post, "register"):
            let register = try body(RegisterRequest.self, request)
            guard let phone = verifications[register.verificationToken] else {
                throw Failure(status: 422, code: "verification_required", message: "Vérifiez d'abord votre numéro de téléphone.")
            }
            let userId = nextId("usr")
            let householdId = nextId("hh")
            households[householdId] = Household(
                policy: nil, limits: nil, card: MemberCard(beneficiaries: []),
                dependents: DependentsResponse(dependents: [], requests: [], canRequestChanges: false, info: nil),
                claims: [], payments: [], vault: [],
                notifications: [AppNotification(id: nextId("ntf"), category: "welcome", title: "Bienvenue sur ASSUR+", body: "Votre compte est créé. Souscrivez une formule pour obtenir votre carte.", createdAt: .now, read: false, target: DeepLinkTarget(kind: .subscription, id: nil))],
                preferences: catalog.notificationPreferences)
            let me = Me(
                id: userId, firstName: register.firstName, lastName: register.lastName, phone: phone, email: register.email,
                birthDate: register.birthDate, gender: register.gender, address: register.address, city: register.city,
                photoURL: nil, role: .principal, permissions: Permission.allCases.map(\.rawValue), memberNumber: nil,
                hasActivePolicy: false, preferredLanguage: "fr")
            let account = Account(me: me, password: register.password, householdId: householdId, beneficiaryId: nextId("ben"))
            accounts[userId] = account
            return try json(issueTokens(for: account), status: 201)
        case (.post, "refresh"):
            let refresh = try body([String: String].self, request)
            guard let token = refresh["refreshToken"], let userId = refreshTokens.removeValue(forKey: token), let account = accounts[userId] else {
                throw Failure(status: 401, code: "invalid_refresh", message: "Session expirée.")
            }
            return try json(issueTokens(for: account).tokens)
        case (.post, "logout"):
            return empty
        case (.post, "password/reset"):
            let reset = try body(PasswordResetRequest.self, request)
            guard let phone = verifications.removeValue(forKey: reset.verificationToken),
                  let userId = accounts.first(where: { $0.value.me.phone == phone })?.key else {
                throw Failure(status: 422, code: "verification_required", message: "Vérification expirée, recommencez.")
            }
            accounts[userId]?.password = reset.newPassword
            return empty
        default:
            throw notFound
        }
    }

    private func issueTokens(for account: Account) -> AuthResponse {
        let access = "acc_" + UUID().uuidString
        let refresh = "ref_" + UUID().uuidString
        accessTokens[access] = account.me.id
        refreshTokens[refresh] = account.me.id
        return AuthResponse(tokens: AuthTokens(accessToken: access, refreshToken: refresh, expiresIn: 900), user: account.me)
    }

    private func authenticate(_ request: URLRequest) throws -> String {
        let header = request.value(forHTTPHeaderField: "Authorization") ?? ""
        guard header.hasPrefix("Bearer "), let userId = accessTokens[String(header.dropFirst(7))] else {
            throw Failure(status: 401, code: "unauthorized", message: "Authentification requise.")
        }
        return userId
    }

    // MARK: State helpers

    private func account(_ userId: String) throws -> Account {
        guard let account = accounts[userId] else { throw notFound }
        return account
    }

    private func household(_ userId: String) throws -> Household {
        guard let household = households[try account(userId).householdId] else { throw notFound }
        return household
    }

    private func mutateAccount(_ userId: String, _ change: (inout Account) -> Void) throws {
        guard var account = accounts[userId] else { throw notFound }
        change(&account)
        accounts[userId] = account
    }

    private func mutateHousehold(_ userId: String, _ change: (inout Household) -> Void) throws {
        let id = try account(userId).householdId
        guard var household = households[id] else { throw notFound }
        change(&household)
        households[id] = household
    }

    private func isPrincipal(_ userId: String) -> Bool { accounts[userId]?.me.role == .principal }

    // MARK: Dashboard & card

    private func dashboard(_ userId: String) throws -> Dashboard {
        let account = try account(userId)
        let household = try household(userId)
        let claims = try visibleClaims(userId)
        var alerts: [Message] = []
        if household.policy == nil {
            alerts.append(Message(level: .info, text: "Vous n'avez pas encore de contrat actif. Souscrivez en moins de 3 minutes."))
        }
        if claims.contains(where: { $0.status == .documentsRequested }) {
            alerts.append(Message(level: .warning, text: "Un document complémentaire est demandé pour l'un de vos sinistres."))
        }
        let dependents = isPrincipal(userId) ? household.dependents.dependents.map {
            DependentSummary(id: $0.id, fullName: $0.fullName, relationLabel: $0.relationLabel, status: $0.status)
        } : []
        let limits = isPrincipal(userId) ? household.limits : household.dependents.dependents.first { $0.id == account.beneficiaryId }?.limits ?? household.limits
        return Dashboard(
            fullName: account.me.fullName, memberNumber: account.me.memberNumber, policy: household.policy?.summary,
            limits: limits, dependents: dependents, recentClaims: Array(claims.prefix(3)).map(summary),
            unreadNotifications: household.notifications.filter { !$0.read }.count, alerts: alerts)
    }

    private func card(_ userId: String) throws -> MemberCard {
        let account = try account(userId)
        let card = try household(userId).card
        guard !isPrincipal(userId) else { return card }
        return MemberCard(beneficiaries: card.beneficiaries.filter { $0.id == account.beneficiaryId })
    }

    // MARK: Subscription (fake pricing — the real rules live on the server)

    private func makeQuote(_ request: QuoteRequest) throws -> Quote {
        guard let product = catalog.products.first(where: { $0.id == request.productId }) else { throw notFound }
        let base = Double(product.premiumFrom ?? 100_000)
        let rateFactor = 1 + Double(request.coverageRate - 70) / 100 * 1.5
        let zoneFactor = request.territoriality == "CIMA" ? 1.2 : 1
        let age = Calendar.current.dateComponents([.year], from: request.birthDate.date, to: .now).year ?? 30
        let ageFactor = age < 30 ? 1.0 : age < 45 ? 1.15 : age < 60 ? 1.4 : 1.8
        func round(_ value: Double) -> Int { Int((value / 500).rounded()) * 500 }

        let principal = round(base * rateFactor * zoneFactor * ageFactor)
        var perMember = [Quote.Line(label: "Assuré principal", amount: principal)]
        for member in request.dependents {
            let factor = member.relation == .child ? 0.35 : 0.8
            perMember.append(Quote.Line(label: "\(member.firstName) (\(member.relation.label))", amount: round(base * rateFactor * zoneFactor * factor)))
        }
        let basePremium = perMember.reduce(0) { $0 + $1.amount }
        let answers = Dictionary(request.answers.map { ($0.questionId, $0.value) }, uniquingKeysWith: { $1 })
        var messages: [Message] = []
        var surcharges: [Quote.Line] = []
        var eligible = true
        if answers["q_chronic"] == "true" {
            surcharges.append(Quote.Line(label: "Surprime santé (fictive)", amount: round(Double(basePremium) * 0.15)))
            messages.append(Message(level: .warning, text: "Une surprime s'applique selon vos réponses au questionnaire de santé."))
        }
        if answers["q_incurable"] == "true" {
            eligible = false
            messages.append(Message(level: .error, text: "Selon vos réponses, une étude médicale est nécessaire avant toute souscription. Un conseiller vous contactera."))
        }
        if age > 70 {
            eligible = false
            messages.append(Message(level: .error, text: "L'âge limite de souscription en ligne est dépassé (règle fictive)."))
        }
        if eligible, messages.isEmpty {
            messages.append(Message(level: .success, text: "Vous êtes éligible à cette formule."))
        }
        let fees = [Quote.Line(label: "Frais de dossier (fictifs)", amount: 5_000)]
        let total = basePremium + surcharges.reduce(0) { $0 + $1.amount } + fees.reduce(0) { $0 + $1.amount }
        let quote = Quote(
            id: nextId("qte"), eligible: eligible, messages: messages, basePremium: basePremium, surcharges: surcharges,
            fees: fees, totalPremium: total, periodLabel: "par an", perMember: perMember,
            validUntil: .now.addingTimeInterval(7 * 86_400), conditionsVersion: "CP-DEMO-1",
            specialConditions: [
                "Formule \(product.name), taux de couverture \(request.coverageRate) %.",
                "Territorialité : \(product.territorialities.first { $0.code == request.territoriality }?.label ?? request.territoriality).",
                "Conditions particulières de démonstration — le texte réel est fourni par l'assureur.",
            ])
        quotes[quote.id] = (request, quote)
        return quote
    }

    private func createPolicy(_ userId: String, _ create: PolicyCreateRequest) throws -> CreatedPolicy {
        guard let entry = quotes[create.quoteId] else { throw Failure(status: 410, code: "quote_expired", message: "Ce devis a expiré, veuillez recalculer.") }
        guard entry.quote.eligible else { throw Failure(status: 422, code: "not_eligible", message: "Ce devis ne permet pas la souscription.") }
        let id = nextId("pol")
        let number = "POL-2026-\(String(format: "%06d", counter))"
        pendingPolicies[id] = (userId, create.quoteId, number)
        let start = LocalDay(date: Calendar.current.date(byAdding: .day, value: 1, to: .now) ?? .now)
        return CreatedPolicy(id: id, number: number, status: ServerStatus(code: "pending_payment", label: "En attente de paiement"), amountDue: entry.quote.totalPremium, startDate: start)
    }

    private func activatePolicy(_ policyId: String) {
        guard let pending = pendingPolicies.removeValue(forKey: policyId),
              let entry = quotes[pending.quoteId],
              let product = catalog.products.first(where: { $0.id == entry.request.productId }),
              var account = accounts[pending.userId] else { return }
        let start = LocalDay(date: Calendar.current.date(byAdding: .day, value: 1, to: .now) ?? .now)
        let end = LocalDay(year: start.year + 1, month: start.month, day: max(start.day - 1, 1))
        let summary = PolicySummary(
            id: policyId, number: pending.number, productName: "Santé", formulaName: product.name, startDate: start, endDate: end,
            status: ServerStatus(code: "active", label: "Actif"), coverageRate: entry.request.coverageRate,
            territoriality: product.territorialities.first { $0.code == entry.request.territoriality }?.label)
        account.me.hasActivePolicy = true
        account.me.memberNumber = "ASP-\(String(format: "%06d", counter))"
        accounts[pending.userId] = account

        var members = [PolicyMember(id: account.beneficiaryId, fullName: account.me.fullName, relationLabel: "Assuré(e) principal(e)", birthDate: account.me.birthDate)]
        var beneficiaries = [CardBeneficiary(
            id: account.beneficiaryId, fullName: account.me.fullName, relationLabel: "Assuré(e) principal(e)",
            memberNumber: account.me.memberNumber ?? "", policyNumber: pending.number, insurerName: "Assureur Démo SA",
            formulaName: product.name, coverageRate: entry.request.coverageRate, validUntil: end, photoURL: nil,
            status: ServerStatus(code: "active", label: "Actif"), birthDate: account.me.birthDate)]
        var dependents: [Dependent] = []
        for member in entry.request.dependents {
            let id = nextId("ben")
            members.append(PolicyMember(id: id, fullName: "\(member.firstName) \(member.lastName)", relationLabel: member.relation.label, birthDate: member.birthDate))
            beneficiaries.append(CardBeneficiary(
                id: id, fullName: "\(member.firstName) \(member.lastName)", relationLabel: member.relation.label,
                memberNumber: "ASP-\(String(format: "%06d", counter + 1))", policyNumber: pending.number, insurerName: "Assureur Démo SA",
                formulaName: product.name, coverageRate: entry.request.coverageRate, validUntil: end, photoURL: nil,
                status: ServerStatus(code: "active", label: "Actif"), birthDate: member.birthDate))
            dependents.append(Dependent(
                id: id, firstName: member.firstName, lastName: member.lastName, relation: member.relation,
                relationLabel: member.relation.label, birthDate: member.birthDate, status: ServerStatus(code: "active", label: "Actif"),
                guarantees: product.guarantees.map(\.label), limits: nil, pendingRequest: nil))
        }
        let detail = PolicyDetail(
            summary: summary, insurerName: "Assureur Démo SA", premiumLabel: Money.format(entry.quote.totalPremium) + " / an",
            members: members, guarantees: product.guarantees, exclusions: product.exclusions, waitingPeriods: product.waitingPeriods,
            deductibleLabel: product.deductibleLabel,
            renewal: RenewalInfo(renewalDate: LocalDay(year: end.year, month: end.month, day: end.day), tacitRenewal: true, terminationDeadline: nil, canRequestTermination: false, info: "Contrat à tacite reconduction."),
            conditionsVersion: entry.quote.conditionsVersion, pendingTermination: nil)
        let limit = 1_500_000
        try? mutateHousehold(pending.userId) {
            $0.policy = detail
            $0.limits = Limits(annualLimit: limit, consumed: 0, reimbursed: 0, remaining: limit)
            $0.card = MemberCard(beneficiaries: beneficiaries)
            $0.dependents = DependentsResponse(dependents: dependents, requests: [], canRequestChanges: true, info: nil)
            $0.notifications.insert(AppNotification(id: "ntf_act_\(policyId)", category: "contract", title: "Contrat activé", body: "Votre contrat \(pending.number) est actif. Votre carte tiers-payant est disponible.", createdAt: .now, read: false, target: DeepLinkTarget(kind: .card, id: nil)), at: 0)
        }
    }

    // MARK: Payments

    private func createPayment(_ userId: String, _ create: PaymentCreateRequest) throws -> Payment {
        guard let method = catalog.paymentMethods.first(where: { $0.code == create.method }) else {
            throw Failure(status: 422, code: "invalid_method", message: "Moyen de paiement indisponible.")
        }
        if method.requiresPhone, create.phone.flatMap(PhoneNumber.e164) == nil {
            throw Failure(status: 422, code: "invalid_phone", message: "Numéro de téléphone invalide.", fields: ["phone": "Numéro invalide."])
        }
        let amount: Int
        let policyNumber: String?
        if let pending = pendingPolicies[create.policyId], let quote = quotes[pending.quoteId]?.quote {
            amount = quote.totalPremium
            policyNumber = pending.number
        } else if let policy = try household(userId).policy, policy.summary.id == create.policyId {
            amount = 245_000
            policyNumber = policy.summary.number
        } else {
            throw notFound
        }
        let id = nextId("pay")
        let payment = Payment(
            id: id, reference: "TX-2026-\(String(format: "%07d", counter))", amount: amount, methodLabel: method.label,
            status: ServerStatus(code: "pending", label: "En attente de validation"), createdAt: .now, policyNumber: policyNumber,
            purposeLabel: create.purpose == "subscription" ? "Souscription — première cotisation" : "Cotisation",
            checkoutURL: URL(string: "https://checkout.mock.assurplus.local/\(id)"),
            appURL: method.kind == .wave ? URL(string: "wave://mock/\(id)") : nil)
        paymentPolls[id] = 0
        try mutateHousehold(userId) { $0.payments.insert(payment, at: 0) }
        pendingPaymentPolicy[id] = create.policyId
        return payment
    }

    private var pendingPaymentPolicy: [String: String] = [:]

    /// Pending → processing → succeeded after a few polls (simulates the provider webhook).
    private func pollPayment(_ userId: String, id: String) throws -> Payment {
        guard var payment = try household(userId).payments.first(where: { $0.id == id }) else { throw notFound }
        guard !payment.isFinal else { return payment }
        let polls = (paymentPolls[id] ?? 0) + 1
        paymentPolls[id] = polls
        let status = polls >= 3 ? ServerStatus(code: "succeeded", label: "Réussi") : ServerStatus(code: "processing", label: "En cours de confirmation")
        payment = Payment(
            id: payment.id, reference: payment.reference, amount: payment.amount, methodLabel: payment.methodLabel, status: status,
            createdAt: payment.createdAt, policyNumber: payment.policyNumber, purposeLabel: payment.purposeLabel,
            checkoutURL: payment.checkoutURL, appURL: payment.appURL)
        let updated = payment
        try mutateHousehold(userId) { $0.payments = $0.payments.map { $0.id == id ? updated : $0 } }
        if payment.isSuccessful, let policyId = pendingPaymentPolicy.removeValue(forKey: id) {
            activatePolicy(policyId)
        }
        return payment
    }

    // MARK: Claims

    private func visibleClaims(_ userId: String) throws -> [Claim] {
        let claims = try household(userId).claims
        guard !isPrincipal(userId) else { return claims }
        let beneficiaryId = try account(userId).beneficiaryId
        return claims.filter { $0.beneficiaryId == beneficiaryId }
    }

    private func summary(_ claim: Claim) -> ClaimSummary {
        ClaimSummary(
            id: claim.id, number: claim.number, status: claim.status, typeLabel: claim.typeLabel,
            beneficiaryName: claim.beneficiaryName, providerName: claim.fields.first { $0.key == "provider" }?.value,
            amount: claim.fields.first { $0.key == "total" }.flatMap { Int($0.value) }, createdAt: claim.createdAt,
            updatedAt: claim.timeline.last?.date ?? claim.createdAt)
    }

    private func findClaim(_ userId: String, _ id: String) throws -> Claim {
        guard let claim = try visibleClaims(userId).first(where: { $0.id == id }) else { throw notFound }
        return claim
    }

    private func saveClaim(_ userId: String, _ claim: Claim) throws {
        try mutateHousehold(userId) { household in
            if let index = household.claims.firstIndex(where: { $0.id == claim.id }) {
                household.claims[index] = claim
            } else {
                household.claims.insert(claim, at: 0)
            }
        }
    }

    private func createClaim(_ userId: String, _ create: ClaimCreateRequest) throws -> Claim {
        guard let type = catalog.claimTypes.first(where: { $0.code == create.typeCode }) else { throw notFound }
        let beneficiary = try card(userId).beneficiaries.first { $0.id == create.beneficiaryId }
        guard let beneficiary else {
            throw Failure(status: 403, code: "forbidden", message: "Vous ne pouvez pas déclarer de sinistre pour ce bénéficiaire.")
        }
        let claim = Claim(
            id: nextId("clm"), number: nil, status: .draft, typeCode: type.code, typeLabel: type.label,
            beneficiaryId: beneficiary.id, beneficiaryName: beneficiary.fullName, createdAt: .now, submittedAt: nil,
            fields: [], lines: [], documents: [], timeline: [ClaimEvent(status: .draft, date: .now, message: nil)],
            documentRequests: [], settlement: nil, rejectionReason: nil)
        try saveClaim(userId, claim)
        return claim
    }

    private func attachDocument(_ userId: String, claimId: String, _ attach: ClaimDocumentAttach) throws -> ClaimDocument {
        let claim = try findClaim(userId, claimId)
        guard uploads[attach.uploadId] != nil else { throw Failure(status: 422, code: "upload_missing", message: "Fichier introuvable, veuillez réessayer.") }
        let document = ClaimDocument(id: nextId("doc"), kind: attach.kind, fileName: "justificatif.jpg", createdAt: .now)
        var requests = claim.documentRequests
        var status = claim.status
        var timeline = claim.timeline
        if let requestId = attach.requestId, let index = requests.firstIndex(where: { $0.id == requestId }) {
            requests[index] = DocumentRequest(id: requestId, label: requests[index].label, fulfilled: true)
            if requests.allSatisfy(\.fulfilled), status == .documentsRequested {
                status = .inReview
                timeline.append(ClaimEvent(status: .inReview, date: .now, message: "Pièces reçues, analyse reprise."))
            }
        }
        if attach.kind == "receipt" { ocrPolls[claimId] = 0 }
        try saveClaim(userId, Claim(
            id: claim.id, number: claim.number, status: status, typeCode: claim.typeCode, typeLabel: claim.typeLabel,
            beneficiaryId: claim.beneficiaryId, beneficiaryName: claim.beneficiaryName, createdAt: claim.createdAt,
            submittedAt: claim.submittedAt, fields: claim.fields, lines: claim.lines, documents: claim.documents + [document],
            timeline: timeline, documentRequests: requests, settlement: claim.settlement, rejectionReason: claim.rejectionReason))
        return document
    }

    private func pollOCR(_ claimId: String) -> OCRResult {
        let polls = (ocrPolls[claimId] ?? 0) + 1
        ocrPolls[claimId] = polls
        guard polls >= 3 else {
            return OCRResult(state: .processing, progress: Double(polls) / 3, fields: [], lines: [], reviewThreshold: catalog.ocr.reviewThreshold, message: "Lecture du justificatif…")
        }
        return catalog.ocr
    }

    private func updateClaim(_ userId: String, id: String, _ update: ClaimUpdate) throws -> Claim {
        let claim = try findClaim(userId, id)
        guard claim.status == .draft else { throw Failure(status: 409, code: "not_editable", message: "Ce sinistre ne peut plus être modifié.") }
        let template = catalog.ocr.fields
        let fields = update.fields.map { key, value in
            OCRField(key: key, label: template.first { $0.key == key }?.label ?? key, value: value, confidence: template.first { $0.key == key }?.confidence)
        }.sorted { a, b in (template.firstIndex { $0.key == a.key } ?? 99) < (template.firstIndex { $0.key == b.key } ?? 99) }
        let updated = Claim(
            id: claim.id, number: nil, status: .draft, typeCode: claim.typeCode, typeLabel: claim.typeLabel,
            beneficiaryId: claim.beneficiaryId, beneficiaryName: claim.beneficiaryName, createdAt: claim.createdAt,
            submittedAt: nil, fields: fields, lines: update.lines, documents: claim.documents, timeline: claim.timeline,
            documentRequests: [], settlement: nil, rejectionReason: nil)
        try saveClaim(userId, updated)
        return updated
    }

    private func submitClaim(_ userId: String, id: String) throws -> Claim {
        let claim = try findClaim(userId, id)
        guard claim.status == .draft else { return claim }
        guard !claim.documents.isEmpty else {
            throw Failure(status: 422, code: "document_required", message: "Ajoutez au moins un justificatif avant de soumettre.")
        }
        let submitted = Claim(
            id: claim.id, number: "SIN-2026-\(String(format: "%06d", counter))", status: .submitted, typeCode: claim.typeCode,
            typeLabel: claim.typeLabel, beneficiaryId: claim.beneficiaryId, beneficiaryName: claim.beneficiaryName,
            createdAt: claim.createdAt, submittedAt: .now, fields: claim.fields, lines: claim.lines, documents: claim.documents,
            timeline: claim.timeline + [ClaimEvent(status: .submitted, date: .now, message: "Déclaration reçue. Vous serez notifié à chaque étape.")],
            documentRequests: [], settlement: nil, rejectionReason: nil)
        counter += 1
        claimPolls[id] = 0
        try saveClaim(userId, submitted)
        try mutateHousehold(userId) {
            $0.notifications.insert(AppNotification(id: nextId("ntf"), category: "claim", title: "Sinistre reçu", body: "Votre déclaration \(submitted.number ?? "") a bien été reçue.", createdAt: .now, read: false, target: DeepLinkTarget(kind: .claim, id: id)), at: 0)
        }
        return submitted
    }

    /// A freshly submitted claim moves to "En analyse" on a later fetch, so status tracking is visible.
    private func pollClaim(_ userId: String, id: String) throws -> Claim {
        let claim = try findClaim(userId, id)
        guard claim.status == .submitted, let polls = claimPolls[id] else { return claim }
        claimPolls[id] = polls + 1
        guard polls >= 1 else { return claim }
        claimPolls[id] = nil
        let reviewed = Claim(
            id: claim.id, number: claim.number, status: .inReview, typeCode: claim.typeCode, typeLabel: claim.typeLabel,
            beneficiaryId: claim.beneficiaryId, beneficiaryName: claim.beneficiaryName, createdAt: claim.createdAt,
            submittedAt: claim.submittedAt, fields: claim.fields, lines: claim.lines, documents: claim.documents,
            timeline: claim.timeline + [ClaimEvent(status: .inReview, date: .now, message: "Votre dossier est en cours d'analyse.")],
            documentRequests: [], settlement: nil, rejectionReason: nil)
        try saveClaim(userId, reviewed)
        return reviewed
    }

    // MARK: Providers

    private func searchProviders(_ query: [String: String]) -> [Provider] {
        var list = providers
        if let type = query["type"], !type.isEmpty { list = list.filter { $0.type.rawValue == type } }
        if let text = query["q"]?.lowercased(), !text.isEmpty {
            list = list.filter { $0.name.lowercased().contains(text) || $0.city.lowercased().contains(text) || ($0.specialty?.lowercased().contains(text) ?? false) }
        }
        guard let lat = query["lat"].flatMap(Double.init), let lng = query["lng"].flatMap(Double.init) else {
            return list.sorted { $0.name < $1.name }
        }
        let radius = query["radius"].flatMap(Double.init).map { $0 * 1000 }
        return list.map { provider in
            Provider(
                id: provider.id, name: provider.name, type: provider.type, specialty: provider.specialty, address: provider.address,
                city: provider.city, phone: provider.phone, latitude: provider.latitude, longitude: provider.longitude,
                distanceMeters: Int(Self.distance(lat, lng, provider.latitude, provider.longitude)),
                tiersPayant: provider.tiersPayant, openingHours: provider.openingHours)
        }
        .filter { radius == nil || Double($0.distanceMeters ?? 0) <= radius! }
        .sorted { ($0.distanceMeters ?? 0) < ($1.distanceMeters ?? 0) }
    }

    private static func distance(_ lat1: Double, _ lng1: Double, _ lat2: Double, _ lng2: Double) -> Double {
        let r = 6_371_000.0
        let dLat = (lat2 - lat1) * .pi / 180
        let dLng = (lng2 - lng1) * .pi / 180
        let a = sin(dLat / 2) * sin(dLat / 2) + cos(lat1 * .pi / 180) * cos(lat2 * .pi / 180) * sin(dLng / 2) * sin(dLng / 2)
        return r * 2 * atan2(sqrt(a), sqrt(1 - a))
    }

    private func conditionsPDF(_ policy: PolicyDetail) -> Data {
        var lines = [
            "Contrat n° \(policy.summary.number)",
            "Assureur : \(policy.insurerName)",
            "Formule : \(policy.summary.formulaName) — couverture \(policy.summary.coverageRate) %",
            "Période : du \(DateText.day(policy.summary.startDate)) au \(DateText.day(policy.summary.endDate))",
            "",
            "Bénéficiaires :",
        ]
        lines += policy.members.map { "• \($0.fullName) — \($0.relationLabel)" }
        lines += ["", "Garanties :"] + policy.guarantees.map { "• \($0.label) — \($0.limitLabel ?? "")" }
        lines += ["", "Exclusions :"] + policy.exclusions.map { "• \($0)" }
        return MockDocuments.pdf(title: "Conditions particulières", lines: lines)
    }
}
