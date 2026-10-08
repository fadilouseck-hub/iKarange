package sn.assurplus.app.core.network

import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.HttpMethod.DELETE
import sn.assurplus.app.core.network.HttpMethod.GET
import sn.assurplus.app.core.network.HttpMethod.PATCH
import sn.assurplus.app.core.network.HttpMethod.POST
import sn.assurplus.app.core.network.HttpMethod.PUT
import java.util.Locale

/** One method per endpoint of `docs/api/openapi.yaml` (same as the iOS `AssurAPI`). Keep both in sync. */
class AssurApi(val client: APIClient) {

    // Auth

    suspend fun sendOTP(phone: String, purpose: OTPPurpose): OTPChallenge =
        client.send(Endpoint.json(POST, "auth/otp/send", OTPSendRequest(phone, purpose), requiresAuth = false))

    suspend fun verifyOTP(requestId: String, code: String): OTPVerification =
        client.send(Endpoint.json(POST, "auth/otp/verify", OTPVerifyRequest(requestId, code), requiresAuth = false))

    suspend fun login(request: LoginRequest): AuthResponse =
        client.send(Endpoint.json(POST, "auth/login", request, requiresAuth = false))

    suspend fun register(request: RegisterRequest): AuthResponse =
        client.send(Endpoint.json(POST, "auth/register", request, requiresAuth = false))

    suspend fun resetPassword(request: PasswordResetRequest) {
        client.send<EmptyResponse>(Endpoint.json(POST, "auth/password/reset", request, requiresAuth = false))
    }

    suspend fun logout(refreshToken: String) {
        client.send<EmptyResponse>(Endpoint.json(POST, "auth/logout", mapOf("refreshToken" to refreshToken)))
    }

    suspend fun legalDocuments(): List<LegalDocument> = client.send(Endpoint(GET, "legal/documents", requiresAuth = false))

    // Me

    suspend fun me(): Me = client.send(Endpoint(GET, "me"))

    suspend fun updateMe(update: MeUpdate): Me = client.send(Endpoint.json(PATCH, "me", update))

    suspend fun setPhoto(uploadId: String): Me = client.send(Endpoint.json(PUT, "me/photo", mapOf("uploadId" to uploadId)))

    suspend fun changePassword(request: PasswordChangeRequest) {
        client.send<EmptyResponse>(Endpoint.json(POST, "me/password", request))
    }

    suspend fun notificationPreferences(): NotificationPreferences = client.send(Endpoint(GET, "me/notification-preferences"))

    suspend fun updateNotificationPreferences(prefs: NotificationPreferences): NotificationPreferences =
        client.send(Endpoint.json(PUT, "me/notification-preferences", prefs))

    suspend fun requestAccountDeletion(reason: String?): ServerStatus =
        client.send(Endpoint.json(POST, "me/deletion-request", mapOf("reason" to (reason ?: ""))))

    suspend fun dashboard(): Dashboard = client.send(Endpoint(GET, "me/dashboard"))

    // Card

    suspend fun card(): MemberCard = client.send(Endpoint(GET, "me/card"))

    suspend fun qrToken(beneficiaryId: String): QRToken =
        client.send(Endpoint(GET, "me/card/qr-token", query = listOf("beneficiaryId" to beneficiaryId)))

    /** `TODO(backend)`: signed "Save to Google Wallet" link for the beneficiary's card. */
    suspend fun googleWalletLink(beneficiaryId: String): WalletLink =
        client.send(Endpoint(GET, "me/card/google-wallet", query = listOf("beneficiaryId" to beneficiaryId)))

    // Subscription

    suspend fun products(): List<Product> = client.send(Endpoint(GET, "products"))

    suspend fun healthQuestionnaire(productId: String?): HealthQuestionnaire =
        client.send(Endpoint(GET, "health-questionnaire", query = Endpoint.query("productId" to productId)))

    suspend fun quote(request: QuoteRequest): Quote = client.send(Endpoint.json(POST, "quotes", request))

    suspend fun createPolicy(quoteId: String, conditionsVersion: String): CreatedPolicy =
        client.send(Endpoint.json(POST, "policies", PolicyCreateRequest(quoteId, conditionsVersion)))

    suspend fun policy(id: String): PolicyDetail = client.send(Endpoint(GET, "policies/$id"))

    suspend fun conditionsPDF(policyId: String): ByteArray =
        client.sendRaw(Endpoint(GET, "policies/$policyId/conditions.pdf", headers = mapOf("Accept" to "application/pdf")))

    suspend fun requestTermination(policyId: String, reason: String): ServerStatus =
        client.send(Endpoint.json(POST, "policies/$policyId/termination-requests", TerminationRequest(reason)))

    // Family

    suspend fun dependents(): DependentsResponse = client.send(Endpoint(GET, "dependents"))

    suspend fun requestDependentAddition(request: DependentAddRequest): DependentRequest =
        client.send(Endpoint.json(POST, "dependents/requests", request))

    suspend fun requestDependentRemoval(id: String, reason: String): DependentRequest =
        client.send(Endpoint.json(DELETE, "dependents/$id", DependentRemoveRequest(reason)))

    // Payments

    suspend fun paymentMethods(): List<PaymentMethod> = client.send(Endpoint(GET, "payment-methods"))

    suspend fun createPayment(request: PaymentCreateRequest): Payment = client.send(Endpoint.json(POST, "payments", request))

    suspend fun payment(id: String): Payment = client.send(Endpoint(GET, "payments/$id"))

    suspend fun payments(): List<Payment> = client.send(Endpoint(GET, "payments"))

    // Claims

    suspend fun claimTypes(): List<ClaimType> = client.send(Endpoint(GET, "claim-types"))

    suspend fun createClaim(beneficiaryId: String, typeCode: String): Claim =
        client.send(Endpoint.json(POST, "claims", ClaimCreateRequest(beneficiaryId, typeCode)))

    suspend fun attachClaimDocument(claimId: String, attach: ClaimDocumentAttach): ClaimDocument =
        client.send(Endpoint.json(POST, "claims/$claimId/documents", attach))

    suspend fun ocr(claimId: String): OCRResult = client.send(Endpoint(GET, "claims/$claimId/ocr"))

    suspend fun updateClaim(id: String, update: ClaimUpdate): Claim = client.send(Endpoint.json(PATCH, "claims/$id", update))

    suspend fun submitClaim(id: String): Claim = client.send(Endpoint.json(POST, "claims/$id/submit", EmptyResponse()))

    suspend fun claims(): List<ClaimSummary> = client.send(Endpoint(GET, "claims"))

    suspend fun claim(id: String): Claim = client.send(Endpoint(GET, "claims/$id"))

    // Vault

    suspend fun vaultDocuments(category: VaultCategory?): List<VaultDocument> =
        client.send(Endpoint(GET, "vault/documents", query = Endpoint.query("category" to category?.raw)))

    suspend fun createVaultDocument(request: VaultDocumentCreate): VaultDocument =
        client.send(Endpoint.json(POST, "vault/documents", request))

    suspend fun vaultFile(id: String): ByteArray = client.sendRaw(Endpoint(GET, "vault/documents/$id/file"))

    suspend fun deleteVaultDocument(id: String) {
        client.send<EmptyResponse>(Endpoint(DELETE, "vault/documents/$id"))
    }

    // Network

    suspend fun providers(type: ProviderType?, latitude: Double?, longitude: Double?, radiusKm: Int?, search: String?): ProvidersResponse =
        client.send(
            Endpoint(
                GET, "providers",
                query = Endpoint.query(
                    "type" to type?.raw,
                    "lat" to latitude?.let { String.format(Locale.US, "%.4f", it) },
                    "lng" to longitude?.let { String.format(Locale.US, "%.4f", it) },
                    "radius" to radiusKm?.toString(),
                    "q" to search,
                ),
            )
        )

    suspend fun networkPDF(type: ProviderType?): ByteArray =
        client.sendRaw(Endpoint(GET, "providers/network.pdf", query = Endpoint.query("type" to type?.raw)))

    // Notifications

    suspend fun notifications(): NotificationsResponse = client.send(Endpoint(GET, "notifications"))

    suspend fun markNotificationRead(id: String) {
        client.send<EmptyResponse>(Endpoint.json(POST, "notifications/$id/read", EmptyResponse()))
    }

    suspend fun markAllNotificationsRead() {
        client.send<EmptyResponse>(Endpoint.json(POST, "notifications/read-all", EmptyResponse()))
    }

    suspend fun registerDevice(registration: DeviceRegistration) {
        client.send<EmptyResponse>(Endpoint.json(POST, "devices", registration))
    }

    // Uploads (resumable)

    suspend fun createUpload(request: UploadCreateRequest): UploadSession = client.send(Endpoint.json(POST, "uploads", request))

    suspend fun uploadOffset(uploadId: String): UploadSession = client.send(Endpoint(GET, "uploads/$uploadId"))

    suspend fun uploadChunk(uploadId: String, offset: Long, data: ByteArray): UploadSession = client.send(
        Endpoint(
            PATCH, "uploads/$uploadId", body = data,
            headers = mapOf("Content-Type" to "application/offset+octet-stream", "Upload-Offset" to offset.toString()),
        )
    )
}

@kotlinx.serialization.Serializable
data class WalletLink(val saveUrl: String)
