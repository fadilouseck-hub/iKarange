package sn.assurplus.app.mock

import android.content.Context
import kotlinx.coroutines.delay
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import okhttp3.HttpUrl.Companion.toHttpUrl
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.HttpRequest
import sn.assurplus.app.core.network.HttpResponse
import sn.assurplus.app.core.network.HttpTransport
import sn.assurplus.app.core.network.WalletLink
import java.time.Instant
import java.time.LocalDate
import java.time.Period
import java.util.UUID
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.roundToLong
import kotlin.math.sin
import kotlin.math.sqrt

/** Shapes of the JSON files in `assets/mock` (shared with iOS `Resources/MockFixtures`). */
@Serializable
data class MockAccountFixture(
    val users: List<User>,
    val policy: PolicyDetail,
    val limits: Limits,
    val card: MemberCard,
    val dependents: DependentsResponse,
    val claims: List<Claim>,
    val payments: List<Payment>,
    val vault: List<VaultDocument>,
    val notifications: List<AppNotification>,
) {
    @Serializable
    data class User(val phone: String, val password: String, val me: Me)
}

@Serializable
data class MockCatalogFixture(
    val products: List<Product>,
    val questionnaire: HealthQuestionnaire,
    val paymentMethods: List<PaymentMethod>,
    val claimTypes: List<ClaimType>,
    val ocr: OCRResult,
    val legal: List<LegalDocument>,
    val notificationPreferences: NotificationPreferences,
)

@Serializable
data class MockProvidersFixture(val providers: List<Provider>)

object MockFixtures {
    inline fun <reified T> load(context: Context, name: String): T =
        JsonCoding.json.decodeFromString(context.assets.open("mock/$name.json").bufferedReader().use { it.readText() })
}

/**
 * In-process fake backend for the mock flavor and UI tests. It implements the OpenAPI contract on top of the JSON
 * fixtures and keeps state for the session (registration, quotes, payments, claims…). Port of the iOS
 * `MockServer`: same accounts, same fake business behaviour, kept out of the app on purpose.
 * Test credentials: see `android/README.md`. OTP code is always `123456`.
 */
class MockServer(
    context: Context,
    private val latencyMs: Long = 350,
    private val qrLifetimeSeconds: Long = 60,
) : HttpTransport {
    companion object {
        const val BASE_URL = "https://mock.assurplus.local/v1"
        const val OTP_CODE = "123456"
    }

    data class Household(
        var policy: PolicyDetail?,
        var limits: Limits?,
        var card: MemberCard,
        var dependents: DependentsResponse,
        var claims: MutableList<Claim>,
        var payments: MutableList<Payment>,
        var vault: MutableList<VaultDocument>,
        var notifications: MutableList<AppNotification>,
        var preferences: NotificationPreferences,
    )

    data class Account(var me: Me, var password: String?, val householdId: String, val beneficiaryId: String)

    private data class Reply(val status: Int = 200, val body: ByteArray, val contentType: String = "application/json")
    private class Failure(val status: Int, val code: String, override val message: String, val fields: Map<String, String> = emptyMap()) : Exception()

    private val catalog: MockCatalogFixture = MockFixtures.load(context, "mock-catalog")
    private val providers: List<Provider> = runCatching { MockFixtures.load<MockProvidersFixture>(context, "mock-providers").providers }.getOrDefault(emptyList())

    private val accounts = mutableMapOf<String, Account>()
    private val households = mutableMapOf<String, Household>()
    private val accessTokens = mutableMapOf<String, String>()
    private val refreshTokens = mutableMapOf<String, String>()
    private val otpRequests = mutableMapOf<String, Pair<String, OTPPurpose>>()
    private val verifications = mutableMapOf<String, String>()
    private val quotes = mutableMapOf<String, Pair<QuoteRequest, Quote>>()
    private data class PendingPolicy(val userId: String, val quoteId: String, val number: String)
    private val pendingPolicies = mutableMapOf<String, PendingPolicy>()
    private val pendingPaymentPolicy = mutableMapOf<String, String>()
    private val paymentPolls = mutableMapOf<String, Int>()
    private val ocrPolls = mutableMapOf<String, Int>()
    private val claimPolls = mutableMapOf<String, Int>()
    private val uploads = mutableMapOf<String, Pair<Long, java.io.ByteArrayOutputStream>>()
    private var counter = 1000
    private val lock = Any()

    init {
        val account: MockAccountFixture = MockFixtures.load(context, "mock-account")
        households["hh_demo"] = Household(
            account.policy, account.limits, account.card, account.dependents, account.claims.toMutableList(),
            account.payments.toMutableList(), account.vault.toMutableList(), account.notifications.toMutableList(),
            catalog.notificationPreferences,
        )
        val beneficiaryIds = mapOf("usr_principal" to "ben_awa", "usr_dependent" to "ben_moussa")
        for (user in account.users) {
            accounts[user.me.id] = Account(user.me, user.password, "hh_demo", beneficiaryIds[user.me.id] ?: "ben_awa")
        }
    }

    // Transport

    override suspend fun send(request: HttpRequest): HttpResponse {
        delay(latencyMs)
        val url = request.url.toHttpUrl()
        val base = BASE_URL.toHttpUrl()
        val parts = url.pathSegments.drop(base.pathSegments.size).filter { it.isNotEmpty() }
        val query = url.queryParameterNames.associateWith { url.queryParameter(it) ?: "" }
        return synchronized(lock) {
            try {
                val reply = route(request.method, parts, query, request)
                HttpResponse(reply.status, reply.body, reply.contentType)
            } catch (failure: Failure) {
                val body = buildJsonObject {
                    put("error", buildJsonObject {
                        put("code", failure.code)
                        put("message", failure.message)
                        put("fields", JsonObject(failure.fields.mapValues { JsonPrimitive(it.value) }))
                    })
                }
                HttpResponse(failure.status, body.toString().toByteArray(), "application/json")
            }
        }
    }

    private inline fun <reified T> json(value: T, status: Int = 200) =
        Reply(status, JsonCoding.json.encodeToString(value).toByteArray())

    private val empty get() = Reply(204, ByteArray(0))

    private inline fun <reified T> body(request: HttpRequest): T = try {
        JsonCoding.json.decodeFromString(request.body!!.decodeToString())
    } catch (e: Exception) {
        throw Failure(400, "bad_request", "Requête invalide.")
    }

    private fun nextId(prefix: String): String {
        counter += 1
        return "${prefix}_$counter"
    }

    private val notFound get() = Failure(404, "not_found", "Élément introuvable.")

    private val staticSegments = setOf(
        "me", "dashboard", "card", "qr-token", "wallet-pass", "google-wallet", "photo", "password", "notification-preferences",
        "deletion-request", "products", "health-questionnaire", "quotes", "policies", "conditions.pdf",
        "termination-requests", "dependents", "requests", "payment-methods", "payments", "claim-types", "claims",
        "documents", "ocr", "submit", "vault", "file", "providers", "network.pdf", "notifications", "read-all",
        "read", "devices", "uploads",
    )

    /** `["claims", "clm_1", "ocr"]` → (`"claims/:id/ocr"`, `["clm_1"]`). */
    private fun template(parts: List<String>): Pair<String, List<String>> {
        val ids = mutableListOf<String>()
        val template = parts.joinToString("/") { part -> if (part in staticSegments) part else { ids += part; ":id" } }
        return template to ids
    }

    // Routing

    private fun route(method: String, parts: List<String>, query: Map<String, String>, request: HttpRequest): Reply {
        if (parts.firstOrNull() == "auth") return routeAuth(method, parts.drop(1).joinToString("/"), request)
        if (method == "GET" && parts.firstOrNull() == "legal") return json(catalog.legal)

        val userId = authenticate(request)
        val (path, ids) = template(parts)
        return when ("$method $path") {
            "GET me" -> json(account(userId).me)
            "PATCH me" -> {
                val update = body<MeUpdate>(request)
                mutateAccount(userId) {
                    var me = it.me
                    update.email?.let { e -> me = me.copy(email = e.ifEmpty { null }) }
                    update.address?.let { a -> me = me.copy(address = a) }
                    update.city?.let { c -> me = me.copy(city = c) }
                    update.preferredLanguage?.let { l -> me = me.copy(preferredLanguage = l) }
                    it.me = me
                }
                json(account(userId).me)
            }
            "PUT me/photo" -> json(account(userId).me)
            "POST me/password" -> {
                val change = body<PasswordChangeRequest>(request)
                if (account(userId).password != change.currentPassword) {
                    throw Failure(422, "invalid_password", "Le mot de passe actuel est incorrect.", mapOf("currentPassword" to "Mot de passe incorrect."))
                }
                mutateAccount(userId) { it.password = change.newPassword }
                empty
            }
            "GET me/notification-preferences" -> json(household(userId).preferences)
            "PUT me/notification-preferences" -> {
                val prefs = body<NotificationPreferences>(request)
                household(userId).preferences = prefs
                json(prefs)
            }
            "POST me/deletion-request" -> json(ServerStatus("requested", "Demande enregistrée — traitement sous 30 jours"))
            "GET me/dashboard" -> json(dashboard(userId))
            "GET me/card" -> json(card(userId))
            "GET me/card/qr-token" -> {
                val token = "AP1." + UUID.randomUUID().toString().replace("-", "").lowercase() + ".sig"
                json(QRToken(token, Instant.now().plusSeconds(qrLifetimeSeconds)))
            }
            "GET me/card/google-wallet" -> json(WalletLink("https://pay.google.com/gp/v/save/mock-${query["beneficiaryId"]}"))

            "GET products" -> json(catalog.products)
            "GET health-questionnaire" -> json(catalog.questionnaire)
            "POST quotes" -> json(makeQuote(body(request)), 201)
            "POST policies" -> json(createPolicy(userId, body(request)), 201)
            "GET policies/:id" -> json(household(userId).policy ?: throw notFound)
            "GET policies/:id/conditions.pdf" -> {
                val policy = household(userId).policy ?: throw notFound
                Reply(body = conditionsPDF(policy), contentType = "application/pdf")
            }
            "POST policies/:id/termination-requests" -> json(ServerStatus("requested", "Demande de résiliation envoyée"), 201)

            "GET dependents" -> json(household(userId).dependents)
            "POST dependents/requests" -> {
                val add = body<DependentAddRequest>(request)
                val pending = DependentRequest(
                    nextId("dreq"), DependentRequest.Kind.add, "${add.firstName} ${add.lastName}",
                    ServerStatus("pending", "En attente de validation"), Instant.now(),
                    "Votre gestionnaire vérifiera les pièces justificatives.",
                )
                val h = household(userId)
                h.dependents = h.dependents.copy(requests = listOf(pending) + h.dependents.requests, canRequestChanges = true)
                json(pending, 201)
            }
            "DELETE dependents/:id" -> {
                val id = ids[0]
                val removal = body<DependentRemoveRequest>(request)
                val h = household(userId)
                val dependent = h.dependents.dependents.firstOrNull { it.id == id } ?: throw notFound
                val pending = DependentRequest(
                    nextId("dreq"), DependentRequest.Kind.remove, dependent.fullName,
                    ServerStatus("pending", "En attente de validation"), Instant.now(), removal.reason,
                )
                h.dependents = h.dependents.copy(requests = listOf(pending) + h.dependents.requests, canRequestChanges = true)
                json(pending, 202)
            }

            "GET payment-methods" -> json(catalog.paymentMethods)
            "POST payments" -> json(createPayment(userId, body(request)), 201)
            "GET payments" -> json(household(userId).payments.toList())
            "GET payments/:id" -> json(pollPayment(userId, ids[0]))

            "GET claim-types" -> json(catalog.claimTypes)
            "GET claims" -> json(visibleClaims(userId).map(::summary))
            "POST claims" -> json(createClaim(userId, body(request)), 201)
            "GET claims/:id" -> json(pollClaim(userId, ids[0]))
            "PATCH claims/:id" -> json(updateClaim(userId, ids[0], body(request)))
            "POST claims/:id/documents" -> json(attachDocument(userId, ids[0], body(request)), 201)
            "GET claims/:id/ocr" -> json(pollOCR(ids[0]))
            "POST claims/:id/submit" -> json(submitClaim(userId, ids[0]))

            "GET vault/documents" -> {
                val docs = household(userId).vault
                json(query["category"]?.let { c -> docs.filter { it.category.raw == c } } ?: docs.toList())
            }
            "POST vault/documents" -> {
                val create = body<VaultDocumentCreate>(request)
                val upload = uploads[create.uploadId] ?: throw Failure(422, "upload_missing", "Fichier introuvable, veuillez réessayer.")
                val doc = VaultDocument(
                    nextId("vlt"), create.category, create.title, "document.jpg", "image/jpeg", upload.first,
                    Instant.now(), account(userId).me.fullName,
                )
                household(userId).vault.add(0, doc)
                json(doc, 201)
            }
            "GET vault/documents/:id/file" -> {
                val doc = household(userId).vault.firstOrNull { it.id == ids[0] } ?: throw notFound
                Reply(body = MockDocuments.pdf(doc.title, listOf(doc.category.label, DateText.day(doc.createdAt))), contentType = "application/pdf")
            }
            "DELETE vault/documents/:id" -> {
                household(userId).vault.removeAll { it.id == ids[0] }
                empty
            }

            "GET providers" -> json(ProvidersResponse(searchProviders(query)))
            "GET providers/network.pdf" -> {
                val list = searchProviders(query).map { "${it.name} — ${it.type.label} — ${it.address}, ${it.city} — ${it.phone ?: ""}" }
                Reply(body = MockDocuments.pdf("Réseau de soins conventionné", list), contentType = "application/pdf")
            }

            "GET notifications" -> {
                val list = household(userId).notifications.toList()
                json(NotificationsResponse(list, list.count { !it.read }))
            }
            "POST notifications/read-all" -> {
                val h = household(userId)
                h.notifications = h.notifications.map { it.copy(read = true) }.toMutableList()
                empty
            }
            "POST notifications/:id/read" -> {
                val h = household(userId)
                h.notifications = h.notifications.map { if (it.id == ids[0]) it.copy(read = true) else it }.toMutableList()
                empty
            }
            "POST devices" -> empty

            "POST uploads" -> {
                val create = body<UploadCreateRequest>(request)
                if (create.size > 15_000_000) throw Failure(413, "too_large", "Le fichier dépasse 15 Mo.")
                val id = nextId("upl")
                uploads[id] = create.size to java.io.ByteArrayOutputStream()
                json(UploadSession(id, 256 * 1024, 0), 201)
            }
            "GET uploads/:id" -> {
                val upload = uploads[ids[0]] ?: throw notFound
                json(UploadSession(ids[0], 256 * 1024, upload.second.size().toLong()))
            }
            "PATCH uploads/:id" -> {
                val upload = uploads[ids[0]] ?: throw notFound
                val offset = request.headers["Upload-Offset"]?.toLongOrNull() ?: -1
                if (offset != upload.second.size().toLong()) throw Failure(409, "offset_mismatch", "Reprise du téléversement…")
                upload.second.write(request.body ?: ByteArray(0))
                json(UploadSession(ids[0], 256 * 1024, upload.second.size().toLong()))
            }
            else -> throw notFound
        }
    }

    // Auth

    private fun routeAuth(method: String, path: String, request: HttpRequest): Reply = when ("$method $path") {
        "POST otp/send" -> {
            val send = body<OTPSendRequest>(request)
            val phone = PhoneNumber.e164(send.phone)
                ?: throw Failure(422, "invalid_phone", "Numéro de téléphone invalide.", mapOf("phone" to "Numéro sénégalais à 9 chiffres attendu."))
            val exists = accounts.values.any { it.me.phone == phone }
            if (send.purpose == OTPPurpose.register && exists) throw Failure(409, "phone_taken", "Un compte existe déjà avec ce numéro. Connectez-vous.")
            if (send.purpose != OTPPurpose.register && !exists) throw Failure(404, "unknown_phone", "Aucun compte n'est associé à ce numéro.")
            val id = nextId("otp")
            otpRequests[id] = phone to send.purpose
            val digits = PhoneNumber.nationalDigits(phone).orEmpty()
            json(OTPChallenge(id, 300, 30, "+221 ${digits.take(2)} *** ** ${digits.takeLast(2)}"))
        }
        "POST otp/verify" -> {
            val verify = body<OTPVerifyRequest>(request)
            val otp = otpRequests[verify.otpRequestId] ?: throw Failure(410, "otp_expired", "Ce code a expiré. Demandez-en un nouveau.")
            if (verify.code != OTP_CODE) throw Failure(422, "otp_invalid", "Code incorrect.", mapOf("code" to "Code incorrect."))
            val token = "ver_" + UUID.randomUUID()
            verifications[token] = otp.first
            json(OTPVerification(token))
        }
        "POST login" -> {
            val login = body<LoginRequest>(request)
            val raw = login.phone ?: login.identifier ?: ""
            val phone = PhoneNumber.e164(raw) ?: raw
            val match = if (login.verificationToken != null) {
                if (verifications[login.verificationToken] == phone) accounts.values.firstOrNull { it.me.phone == phone } else null
            } else {
                accounts.values.firstOrNull { it.me.phone == phone && it.password == login.password }
            } ?: throw Failure(401, "invalid_credentials", "Numéro ou mot de passe incorrect.")
            json(issueTokens(match))
        }
        "POST register" -> {
            val register = body<RegisterRequest>(request)
            val phone = verifications[register.verificationToken]
                ?: throw Failure(422, "verification_required", "Vérifiez d'abord votre numéro de téléphone.")
            val userId = nextId("usr")
            val householdId = nextId("hh")
            households[householdId] = Household(
                null, null, MemberCard(emptyList()), DependentsResponse(emptyList(), emptyList(), false, null),
                mutableListOf(), mutableListOf(), mutableListOf(),
                mutableListOf(
                    AppNotification(
                        nextId("ntf"), "welcome", "Bienvenue sur ASSUR+", "Votre compte est créé. Souscrivez une formule pour obtenir votre carte.",
                        Instant.now(), false, DeepLinkTarget(DeepLinkTarget.Kind.subscription),
                    )
                ),
                catalog.notificationPreferences,
            )
            val me = Me(
                id = userId, firstName = register.firstName, lastName = register.lastName, phone = phone, email = register.email,
                birthDate = register.birthDate, gender = register.gender, address = register.address, city = register.city,
                role = AccountRole.principal, permissions = Permission.entries.map { it.raw }, memberNumber = null,
                hasActivePolicy = false, preferredLanguage = "fr",
            )
            val account = Account(me, register.password, householdId, nextId("ben"))
            accounts[userId] = account
            json(issueTokens(account), 201)
        }
        "POST refresh" -> {
            val refresh = body<Map<String, String>>(request)
            val token = refresh["refreshToken"]
            val userId = token?.let { refreshTokens.remove(it) }
            val account = userId?.let { accounts[it] } ?: throw Failure(401, "invalid_refresh", "Session expirée.")
            json(issueTokens(account).tokens)
        }
        "POST logout" -> empty
        "POST password/reset" -> {
            val reset = body<PasswordResetRequest>(request)
            val phone = verifications.remove(reset.verificationToken)
            val userId = accounts.entries.firstOrNull { it.value.me.phone == phone }?.key
                ?: throw Failure(422, "verification_required", "Vérification expirée, recommencez.")
            accounts[userId]?.password = reset.newPassword
            empty
        }
        else -> throw notFound
    }

    private fun issueTokens(account: Account): AuthResponse {
        val access = "acc_" + UUID.randomUUID()
        val refresh = "ref_" + UUID.randomUUID()
        accessTokens[access] = account.me.id
        refreshTokens[refresh] = account.me.id
        return AuthResponse(AuthTokens(access, refresh, 900), account.me)
    }

    private fun authenticate(request: HttpRequest): String {
        val header = request.headers["Authorization"].orEmpty()
        return header.removePrefix("Bearer ").takeIf { header.startsWith("Bearer ") }?.let { accessTokens[it] }
            ?: throw Failure(401, "unauthorized", "Authentification requise.")
    }

    // State helpers

    private fun account(userId: String) = accounts[userId] ?: throw notFound
    private fun household(userId: String) = households[account(userId).householdId] ?: throw notFound
    private fun mutateAccount(userId: String, change: (Account) -> Unit) = change(account(userId))
    private fun isPrincipal(userId: String) = accounts[userId]?.me?.role == AccountRole.principal

    // Dashboard & card

    private fun dashboard(userId: String): Dashboard {
        val account = account(userId)
        val household = household(userId)
        val claims = visibleClaims(userId)
        val alerts = mutableListOf<Message>()
        if (household.policy == null) alerts += Message(Message.Level.info, "Vous n'avez pas encore de contrat actif. Souscrivez en moins de 3 minutes.")
        if (claims.any { it.status == ClaimStatus.documentsRequested }) alerts += Message(Message.Level.warning, "Un document complémentaire est demandé pour l'un de vos sinistres.")
        val dependents = if (isPrincipal(userId)) household.dependents.dependents.map { DependentSummary(it.id, it.fullName, it.relationLabel, it.status) } else emptyList()
        val limits = if (isPrincipal(userId)) household.limits
        else household.dependents.dependents.firstOrNull { it.id == account.beneficiaryId }?.limits ?: household.limits
        return Dashboard(
            account.me.fullName, account.me.memberNumber, household.policy?.summary, limits, dependents,
            claims.take(3).map(::summary), household.notifications.count { !it.read }, alerts,
        )
    }

    private fun card(userId: String): MemberCard {
        val account = account(userId)
        val card = household(userId).card
        if (isPrincipal(userId)) return card
        return MemberCard(card.beneficiaries.filter { it.id == account.beneficiaryId })
    }

    // Subscription (fake pricing — the real rules live on the server)

    private fun makeQuote(request: QuoteRequest): Quote {
        val product = catalog.products.firstOrNull { it.id == request.productId } ?: throw notFound
        val base = (product.premiumFrom ?: 100_000).toDouble()
        val rateFactor = 1 + (request.coverageRate - 70).toDouble() / 100 * 1.5
        val zoneFactor = if (request.territoriality == "CIMA") 1.2 else 1.0
        val age = Period.between(request.birthDate.date, LocalDate.now()).years
        val ageFactor = if (age < 30) 1.0 else if (age < 45) 1.15 else if (age < 60) 1.4 else 1.8
        fun round(value: Double): Long = (value / 500).roundToLong() * 500

        val principal = round(base * rateFactor * zoneFactor * ageFactor)
        val perMember = mutableListOf(Quote.Line("Assuré principal", principal))
        for (member in request.dependents) {
            val factor = if (member.relation == Relation.child) 0.35 else 0.8
            perMember += Quote.Line("${member.firstName} (${member.relation.label})", round(base * rateFactor * zoneFactor * factor))
        }
        val basePremium = perMember.sumOf { it.amount }
        val answers = request.answers.associate { it.questionId to it.value }
        val messages = mutableListOf<Message>()
        val surcharges = mutableListOf<Quote.Line>()
        var eligible = true
        if (answers["q_chronic"] == "true") {
            surcharges += Quote.Line("Surprime santé (fictive)", round(basePremium * 0.15))
            messages += Message(Message.Level.warning, "Une surprime s'applique selon vos réponses au questionnaire de santé.")
        }
        if (answers["q_incurable"] == "true") {
            eligible = false
            messages += Message(Message.Level.error, "Selon vos réponses, une étude médicale est nécessaire avant toute souscription. Un conseiller vous contactera.")
        }
        if (age > 70) {
            eligible = false
            messages += Message(Message.Level.error, "L'âge limite de souscription en ligne est dépassé (règle fictive).")
        }
        if (eligible && messages.isEmpty()) messages += Message(Message.Level.success, "Vous êtes éligible à cette formule.")
        val fees = listOf(Quote.Line("Frais de dossier (fictifs)", 5_000))
        val total = basePremium + surcharges.sumOf { it.amount } + fees.sumOf { it.amount }
        val quote = Quote(
            nextId("qte"), eligible, messages, basePremium, surcharges, fees, total, "par an", perMember,
            Instant.now().plusSeconds(7 * 86_400), "CP-DEMO-1",
            listOf(
                "Formule ${product.name}, taux de couverture ${request.coverageRate} %.",
                "Territorialité : ${product.territorialities.firstOrNull { it.code == request.territoriality }?.label ?: request.territoriality}.",
                "Conditions particulières de démonstration — le texte réel est fourni par l'assureur.",
            ),
        )
        quotes[quote.id] = request to quote
        return quote
    }

    private fun createPolicy(userId: String, create: PolicyCreateRequest): CreatedPolicy {
        val entry = quotes[create.quoteId] ?: throw Failure(410, "quote_expired", "Ce devis a expiré, veuillez recalculer.")
        if (!entry.second.eligible) throw Failure(422, "not_eligible", "Ce devis ne permet pas la souscription.")
        val id = nextId("pol")
        val number = "POL-2026-%06d".format(counter)
        pendingPolicies[id] = PendingPolicy(userId, create.quoteId, number)
        return CreatedPolicy(id, number, ServerStatus("pending_payment", "En attente de paiement"), entry.second.totalPremium, LocalDay.of(LocalDate.now().plusDays(1)))
    }

    private fun activatePolicy(policyId: String) {
        val pending = pendingPolicies.remove(policyId) ?: return
        val entry = quotes[pending.quoteId] ?: return
        val request = entry.first
        val product = catalog.products.firstOrNull { it.id == request.productId } ?: return
        val account = accounts[pending.userId] ?: return
        val start = LocalDay.of(LocalDate.now().plusDays(1))
        val end = LocalDay(start.year + 1, start.month, maxOf(start.day - 1, 1))
        val active = ServerStatus("active", "Actif")
        val territory = product.territorialities.firstOrNull { it.code == request.territoriality }?.label
        val summary = PolicySummary(policyId, pending.number, "Santé", product.name, start, end, active, request.coverageRate, territory)
        account.me = account.me.copy(hasActivePolicy = true, memberNumber = "ASP-%06d".format(counter))

        val members = mutableListOf(PolicyMember(account.beneficiaryId, account.me.fullName, "Assuré(e) principal(e)", account.me.birthDate))
        val beneficiaries = mutableListOf(
            CardBeneficiary(
                account.beneficiaryId, account.me.fullName, "Assuré(e) principal(e)", account.me.memberNumber.orEmpty(), pending.number,
                "Assureur Démo SA", product.name, request.coverageRate, end, null, active, account.me.birthDate,
            )
        )
        val dependents = mutableListOf<Dependent>()
        for (member in request.dependents) {
            val id = nextId("ben")
            val name = "${member.firstName} ${member.lastName}"
            members += PolicyMember(id, name, member.relation.label, member.birthDate)
            beneficiaries += CardBeneficiary(
                id, name, member.relation.label, "ASP-%06d".format(counter + 1), pending.number, "Assureur Démo SA",
                product.name, request.coverageRate, end, null, active, member.birthDate,
            )
            dependents += Dependent(id, member.firstName, member.lastName, member.relation, member.relation.label, member.birthDate, active, product.guarantees.map { it.label })
        }
        val detail = PolicyDetail(
            summary, "Assureur Démo SA", Money.format(entry.second.totalPremium) + " / an", members, product.guarantees,
            product.exclusions, product.waitingPeriods, product.deductibleLabel,
            RenewalInfo(end, true, null, false, "Contrat à tacite reconduction."), entry.second.conditionsVersion, null,
        )
        val limit = 1_500_000L
        val household = households[account.householdId] ?: return
        household.policy = detail
        household.limits = Limits(limit, 0, 0, limit)
        household.card = MemberCard(beneficiaries)
        household.dependents = DependentsResponse(dependents, emptyList(), true, null)
        household.notifications.add(
            0,
            AppNotification(
                "ntf_act_$policyId", "contract", "Contrat activé", "Votre contrat ${pending.number} est actif. Votre carte tiers-payant est disponible.",
                Instant.now(), false, DeepLinkTarget(DeepLinkTarget.Kind.card),
            ),
        )
    }

    // Payments

    private fun createPayment(userId: String, create: PaymentCreateRequest): Payment {
        val method = catalog.paymentMethods.firstOrNull { it.code == create.method } ?: throw Failure(422, "invalid_method", "Moyen de paiement indisponible.")
        if (method.requiresPhone && create.phone?.let(PhoneNumber::e164) == null) {
            throw Failure(422, "invalid_phone", "Numéro de téléphone invalide.", mapOf("phone" to "Numéro invalide."))
        }
        val pending = pendingPolicies[create.policyId]
        val policy = household(userId).policy
        val (amount, policyNumber) = when {
            pending != null && quotes[pending.quoteId] != null -> quotes[pending.quoteId]!!.second.totalPremium to pending.number
            policy != null && policy.summary.id == create.policyId -> 245_000L to policy.summary.number
            else -> throw notFound
        }
        val id = nextId("pay")
        val payment = Payment(
            id, "TX-2026-%07d".format(counter), amount, method.label, ServerStatus("pending", "En attente de validation"), Instant.now(),
            policyNumber, if (create.purpose == "subscription") "Souscription — première cotisation" else "Cotisation",
            "https://checkout.mock.assurplus.local/$id", if (method.kind == PaymentMethod.Kind.wave) "wave://mock/$id" else null,
        )
        paymentPolls[id] = 0
        household(userId).payments.add(0, payment)
        pendingPaymentPolicy[id] = create.policyId
        return payment
    }

    /** Pending → processing → succeeded after a few polls (simulates the provider webhook). */
    private fun pollPayment(userId: String, id: String): Payment {
        val household = household(userId)
        val payment = household.payments.firstOrNull { it.id == id } ?: throw notFound
        if (payment.isFinal) return payment
        val polls = (paymentPolls[id] ?: 0) + 1
        paymentPolls[id] = polls
        val status = if (polls >= 3) ServerStatus("succeeded", "Réussi") else ServerStatus("processing", "En cours de confirmation")
        val updated = payment.copy(status = status)
        household.payments = household.payments.map { if (it.id == id) updated else it }.toMutableList()
        if (updated.isSuccessful) pendingPaymentPolicy.remove(id)?.let(::activatePolicy)
        return updated
    }

    // Claims

    private fun visibleClaims(userId: String): List<Claim> {
        val claims = household(userId).claims
        if (isPrincipal(userId)) return claims.toList()
        val beneficiaryId = account(userId).beneficiaryId
        return claims.filter { it.beneficiaryId == beneficiaryId }
    }

    private fun summary(claim: Claim) = ClaimSummary(
        claim.id, claim.number, claim.status, claim.typeLabel, claim.beneficiaryName,
        claim.fields.firstOrNull { it.key == "provider" }?.value,
        claim.fields.firstOrNull { it.key == "total" }?.value?.toLongOrNull(),
        claim.createdAt, claim.timeline.lastOrNull()?.date ?: claim.createdAt,
    )

    private fun findClaim(userId: String, id: String) = visibleClaims(userId).firstOrNull { it.id == id } ?: throw notFound

    private fun saveClaim(userId: String, claim: Claim) {
        val claims = household(userId).claims
        val index = claims.indexOfFirst { it.id == claim.id }
        if (index >= 0) claims[index] = claim else claims.add(0, claim)
    }

    private fun createClaim(userId: String, create: ClaimCreateRequest): Claim {
        val type = catalog.claimTypes.firstOrNull { it.code == create.typeCode } ?: throw notFound
        val beneficiary = card(userId).beneficiaries.firstOrNull { it.id == create.beneficiaryId }
            ?: throw Failure(403, "forbidden", "Vous ne pouvez pas déclarer de sinistre pour ce bénéficiaire.")
        val claim = Claim(
            id = nextId("clm"), status = ClaimStatus.draft, typeCode = type.code, typeLabel = type.label,
            beneficiaryId = beneficiary.id, beneficiaryName = beneficiary.fullName, createdAt = Instant.now(),
            timeline = listOf(ClaimEvent(ClaimStatus.draft, Instant.now())),
        )
        saveClaim(userId, claim)
        return claim
    }

    private fun attachDocument(userId: String, claimId: String, attach: ClaimDocumentAttach): ClaimDocument {
        val claim = findClaim(userId, claimId)
        if (uploads[attach.uploadId] == null) throw Failure(422, "upload_missing", "Fichier introuvable, veuillez réessayer.")
        val document = ClaimDocument(nextId("doc"), attach.kind, "justificatif.jpg", Instant.now())
        var requests = claim.documentRequests
        var status = claim.status
        var timeline = claim.timeline
        val index = requests.indexOfFirst { it.id == attach.requestId }
        if (attach.requestId != null && index >= 0) {
            requests = requests.toMutableList().also { it[index] = it[index].copy(fulfilled = true) }
            if (requests.all { it.fulfilled } && status == ClaimStatus.documentsRequested) {
                status = ClaimStatus.inReview
                timeline = timeline + ClaimEvent(ClaimStatus.inReview, Instant.now(), "Pièces reçues, analyse reprise.")
            }
        }
        if (attach.kind == "receipt") ocrPolls[claimId] = 0
        saveClaim(userId, claim.copy(status = status, documents = claim.documents + document, timeline = timeline, documentRequests = requests))
        return document
    }

    private fun pollOCR(claimId: String): OCRResult {
        val polls = (ocrPolls[claimId] ?: 0) + 1
        ocrPolls[claimId] = polls
        if (polls < 3) return OCRResult(OCRResult.State.processing, polls / 3.0, emptyList(), emptyList(), catalog.ocr.reviewThreshold, "Lecture du justificatif…")
        return catalog.ocr
    }

    private fun updateClaim(userId: String, id: String, update: ClaimUpdate): Claim {
        val claim = findClaim(userId, id)
        if (claim.status != ClaimStatus.draft) throw Failure(409, "not_editable", "Ce sinistre ne peut plus être modifié.")
        val template = catalog.ocr.fields
        val fields = update.fields.map { (key, value) ->
            val known = template.firstOrNull { it.key == key }
            OCRField(key, known?.label ?: key, value, known?.confidence)
        }.sortedBy { field -> template.indexOfFirst { it.key == field.key }.let { if (it < 0) 99 else it } }
        val updated = claim.copy(number = null, status = ClaimStatus.draft, submittedAt = null, fields = fields, lines = update.lines, documentRequests = emptyList(), settlement = null, rejectionReason = null)
        saveClaim(userId, updated)
        return updated
    }

    private fun submitClaim(userId: String, id: String): Claim {
        val claim = findClaim(userId, id)
        if (claim.status != ClaimStatus.draft) return claim
        if (claim.documents.isEmpty()) throw Failure(422, "document_required", "Ajoutez au moins un justificatif avant de soumettre.")
        val submitted = claim.copy(
            number = "SIN-2026-%06d".format(counter), status = ClaimStatus.submitted, submittedAt = Instant.now(),
            timeline = claim.timeline + ClaimEvent(ClaimStatus.submitted, Instant.now(), "Déclaration reçue. Vous serez notifié à chaque étape."),
            documentRequests = emptyList(), settlement = null, rejectionReason = null,
        )
        counter += 1
        claimPolls[id] = 0
        saveClaim(userId, submitted)
        household(userId).notifications.add(
            0,
            AppNotification(
                nextId("ntf"), "claim", "Sinistre reçu", "Votre déclaration ${submitted.number.orEmpty()} a bien été reçue.",
                Instant.now(), false, DeepLinkTarget(DeepLinkTarget.Kind.claim, id),
            ),
        )
        return submitted
    }

    /** A freshly submitted claim moves to "En analyse" on a later fetch, so status tracking is visible. */
    private fun pollClaim(userId: String, id: String): Claim {
        val claim = findClaim(userId, id)
        val polls = claimPolls[id]
        if (claim.status != ClaimStatus.submitted || polls == null) return claim
        claimPolls[id] = polls + 1
        if (polls < 1) return claim
        claimPolls.remove(id)
        val reviewed = claim.copy(
            status = ClaimStatus.inReview,
            timeline = claim.timeline + ClaimEvent(ClaimStatus.inReview, Instant.now(), "Votre dossier est en cours d'analyse."),
            documentRequests = emptyList(), settlement = null, rejectionReason = null,
        )
        saveClaim(userId, reviewed)
        return reviewed
    }

    // Providers

    private fun searchProviders(query: Map<String, String>): List<Provider> {
        var list = providers
        query["type"]?.takeIf { it.isNotEmpty() }?.let { type -> list = list.filter { it.type.raw == type } }
        query["q"]?.lowercase()?.takeIf { it.isNotEmpty() }?.let { text ->
            list = list.filter { it.name.lowercase().contains(text) || it.city.lowercase().contains(text) || (it.specialty?.lowercase()?.contains(text) ?: false) }
        }
        val lat = query["lat"]?.toDoubleOrNull()
        val lng = query["lng"]?.toDoubleOrNull()
        if (lat == null || lng == null) return list.sortedBy { it.name }
        val radius = query["radius"]?.toDoubleOrNull()?.times(1000)
        return list.map { p ->
            p.copy(distanceMeters = if (p.latitude != null && p.longitude != null) distance(lat, lng, p.latitude, p.longitude).toInt() else null)
        }.filter { radius == null || (it.distanceMeters ?: 0) <= radius }.sortedBy { it.distanceMeters ?: 0 }
    }

    private fun distance(lat1: Double, lng1: Double, lat2: Double, lng2: Double): Double {
        val r = 6_371_000.0
        val dLat = Math.toRadians(lat2 - lat1)
        val dLng = Math.toRadians(lng2 - lng1)
        val a = sin(dLat / 2) * sin(dLat / 2) + cos(Math.toRadians(lat1)) * cos(Math.toRadians(lat2)) * sin(dLng / 2) * sin(dLng / 2)
        return r * 2 * atan2(sqrt(a), sqrt(1 - a))
    }

    private fun conditionsPDF(policy: PolicyDetail): ByteArray {
        val lines = mutableListOf(
            "Contrat n° ${policy.summary.number}",
            "Assureur : ${policy.insurerName}",
            "Formule : ${policy.summary.formulaName} — couverture ${policy.summary.coverageRate} %",
            "Période : du ${DateText.day(policy.summary.startDate)} au ${DateText.day(policy.summary.endDate)}",
            "",
            "Bénéficiaires :",
        )
        lines += policy.members.map { "• ${it.fullName} — ${it.relationLabel}" }
        lines += listOf("", "Garanties :") + policy.guarantees.map { "• ${it.label} — ${it.limitLabel.orEmpty()}" }
        lines += listOf("", "Exclusions :") + policy.exclusions.map { "• $it" }
        return MockDocuments.pdf("Conditions particulières", lines)
    }
}
