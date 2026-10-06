import Foundation

/// One method per endpoint of `docs/api/openapi.yaml`. Keep both in sync.
struct AssurAPI: Sendable {
    let client: APIClient

    // MARK: Auth

    func sendOTP(phone: String, purpose: OTPPurpose) async throws -> OTPChallenge {
        try await client.send(Endpoint(.post, "auth/otp/send", body: OTPSendRequest(phone: phone, purpose: purpose), requiresAuth: false))
    }

    func verifyOTP(requestId: String, code: String) async throws -> OTPVerification {
        try await client.send(Endpoint(.post, "auth/otp/verify", body: OTPVerifyRequest(otpRequestId: requestId, code: code), requiresAuth: false))
    }

    func login(_ request: LoginRequest) async throws -> AuthResponse {
        try await client.send(Endpoint(.post, "auth/login", body: request, requiresAuth: false))
    }

    func register(_ request: RegisterRequest) async throws -> AuthResponse {
        try await client.send(Endpoint(.post, "auth/register", body: request, requiresAuth: false))
    }

    func resetPassword(_ request: PasswordResetRequest) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "auth/password/reset", body: request, requiresAuth: false))
    }

    func logout(refreshToken: String) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "auth/logout", body: ["refreshToken": refreshToken]))
    }

    func legalDocuments() async throws -> [LegalDocument] {
        try await client.send(Endpoint(.get, "legal/documents", requiresAuth: false))
    }

    // MARK: Me

    func me() async throws -> Me { try await client.send(Endpoint(.get, "me")) }

    func updateMe(_ update: MeUpdate) async throws -> Me {
        try await client.send(Endpoint(.patch, "me", body: update))
    }

    func setPhoto(uploadId: String) async throws -> Me {
        try await client.send(Endpoint(.put, "me/photo", body: ["uploadId": uploadId]))
    }

    func changePassword(_ request: PasswordChangeRequest) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "me/password", body: request))
    }

    func notificationPreferences() async throws -> NotificationPreferences {
        try await client.send(Endpoint(.get, "me/notification-preferences"))
    }

    func updateNotificationPreferences(_ prefs: NotificationPreferences) async throws -> NotificationPreferences {
        try await client.send(Endpoint(.put, "me/notification-preferences", body: prefs))
    }

    func requestAccountDeletion(reason: String?) async throws -> ServerStatus {
        try await client.send(Endpoint(.post, "me/deletion-request", body: ["reason": reason ?? ""]))
    }

    func dashboard() async throws -> Dashboard { try await client.send(Endpoint(.get, "me/dashboard")) }

    // MARK: Card

    func card() async throws -> MemberCard { try await client.send(Endpoint(.get, "me/card")) }

    func qrToken(beneficiaryId: String) async throws -> QRToken {
        try await client.send(Endpoint(.get, "me/card/qr-token", query: [URLQueryItem(name: "beneficiaryId", value: beneficiaryId)]))
    }

    func walletPass(beneficiaryId: String) async throws -> Data {
        var endpoint = Endpoint<EmptyResponse>(.get, "me/card/wallet-pass", query: [URLQueryItem(name: "beneficiaryId", value: beneficiaryId)])
        endpoint.headers["Accept"] = "application/vnd.apple.pkpass"
        return try await client.sendRaw(endpoint)
    }

    // MARK: Subscription

    func products() async throws -> [Product] { try await client.send(Endpoint(.get, "products")) }

    func healthQuestionnaire(productId: String?) async throws -> HealthQuestionnaire {
        try await client.send(Endpoint(.get, "health-questionnaire", query: URLQueryItem.items([("productId", productId)])))
    }

    func quote(_ request: QuoteRequest) async throws -> Quote {
        try await client.send(Endpoint(.post, "quotes", body: request))
    }

    func createPolicy(quoteId: String, conditionsVersion: String) async throws -> CreatedPolicy {
        try await client.send(Endpoint(.post, "policies", body: PolicyCreateRequest(quoteId: quoteId, acceptedConditionsVersion: conditionsVersion)))
    }

    func policy(id: String) async throws -> PolicyDetail { try await client.send(Endpoint(.get, "policies/\(id)")) }

    func conditionsPDF(policyId: String) async throws -> Data {
        var endpoint = Endpoint<EmptyResponse>(.get, "policies/\(policyId)/conditions.pdf")
        endpoint.headers["Accept"] = "application/pdf"
        return try await client.sendRaw(endpoint)
    }

    func requestTermination(policyId: String, reason: String) async throws -> ServerStatus {
        try await client.send(Endpoint(.post, "policies/\(policyId)/termination-requests", body: TerminationRequest(reason: reason)))
    }

    // MARK: Family

    func dependents() async throws -> DependentsResponse { try await client.send(Endpoint(.get, "dependents")) }

    func requestDependentAddition(_ request: DependentAddRequest) async throws -> DependentRequest {
        try await client.send(Endpoint(.post, "dependents/requests", body: request))
    }

    func requestDependentRemoval(id: String, reason: String) async throws -> DependentRequest {
        try await client.send(Endpoint(.delete, "dependents/\(id)", body: DependentRemoveRequest(reason: reason)))
    }

    // MARK: Payments

    func paymentMethods() async throws -> [PaymentMethod] { try await client.send(Endpoint(.get, "payment-methods")) }

    func createPayment(_ request: PaymentCreateRequest) async throws -> Payment {
        try await client.send(Endpoint(.post, "payments", body: request))
    }

    func payment(id: String) async throws -> Payment { try await client.send(Endpoint(.get, "payments/\(id)")) }

    func payments() async throws -> [Payment] { try await client.send(Endpoint(.get, "payments")) }

    // MARK: Claims

    func claimTypes() async throws -> [ClaimType] { try await client.send(Endpoint(.get, "claim-types")) }

    func createClaim(beneficiaryId: String, typeCode: String) async throws -> Claim {
        try await client.send(Endpoint(.post, "claims", body: ClaimCreateRequest(beneficiaryId: beneficiaryId, typeCode: typeCode)))
    }

    func attachClaimDocument(claimId: String, _ attach: ClaimDocumentAttach) async throws -> ClaimDocument {
        try await client.send(Endpoint(.post, "claims/\(claimId)/documents", body: attach))
    }

    func ocr(claimId: String) async throws -> OCRResult { try await client.send(Endpoint(.get, "claims/\(claimId)/ocr")) }

    func updateClaim(id: String, _ update: ClaimUpdate) async throws -> Claim {
        try await client.send(Endpoint(.patch, "claims/\(id)", body: update))
    }

    func submitClaim(id: String) async throws -> Claim {
        try await client.send(Endpoint(.post, "claims/\(id)/submit", body: EmptyResponse()))
    }

    func claims() async throws -> [ClaimSummary] { try await client.send(Endpoint(.get, "claims")) }

    func claim(id: String) async throws -> Claim { try await client.send(Endpoint(.get, "claims/\(id)")) }

    // MARK: Vault

    func vaultDocuments(category: VaultCategory?) async throws -> [VaultDocument] {
        try await client.send(Endpoint(.get, "vault/documents", query: URLQueryItem.items([("category", category?.rawValue)])))
    }

    func createVaultDocument(_ request: VaultDocumentCreate) async throws -> VaultDocument {
        try await client.send(Endpoint(.post, "vault/documents", body: request))
    }

    func vaultFile(id: String) async throws -> Data {
        try await client.sendRaw(Endpoint<EmptyResponse>(.get, "vault/documents/\(id)/file"))
    }

    func deleteVaultDocument(id: String) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.delete, "vault/documents/\(id)"))
    }

    // MARK: Network

    func providers(type: ProviderType?, latitude: Double?, longitude: Double?, radiusKm: Int?, search: String?) async throws -> ProvidersResponse {
        let query = URLQueryItem.items([
            ("type", type?.rawValue),
            ("lat", latitude.map { String(format: "%.4f", $0) }),
            ("lng", longitude.map { String(format: "%.4f", $0) }),
            ("radius", radiusKm.map(String.init)),
            ("q", search),
        ])
        return try await client.send(Endpoint(.get, "providers", query: query))
    }

    func networkPDF(type: ProviderType?) async throws -> Data {
        try await client.sendRaw(Endpoint<EmptyResponse>(.get, "providers/network.pdf", query: URLQueryItem.items([("type", type?.rawValue)])))
    }

    // MARK: Notifications

    func notifications() async throws -> NotificationsResponse { try await client.send(Endpoint(.get, "notifications")) }

    func markNotificationRead(id: String) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "notifications/\(id)/read", body: EmptyResponse()))
    }

    func markAllNotificationsRead() async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "notifications/read-all", body: EmptyResponse()))
    }

    func registerDevice(_ registration: DeviceRegistration) async throws {
        _ = try await client.send(Endpoint<EmptyResponse>(.post, "devices", body: registration))
    }

    // MARK: Uploads (resumable)

    func createUpload(_ request: UploadCreateRequest) async throws -> UploadSession {
        try await client.send(Endpoint(.post, "uploads", body: request))
    }

    func uploadOffset(uploadId: String) async throws -> UploadSession {
        try await client.send(Endpoint(.get, "uploads/\(uploadId)"))
    }

    func uploadChunk(uploadId: String, offset: Int, data: Data) async throws -> UploadSession {
        var endpoint = Endpoint<UploadSession>(.patch, "uploads/\(uploadId)")
        endpoint.body = data
        endpoint.headers["Content-Type"] = "application/offset+octet-stream"
        endpoint.headers["Upload-Offset"] = String(offset)
        return try await client.send(endpoint)
    }
}
