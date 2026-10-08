package sn.assurplus.app.core.model

import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import sn.assurplus.app.core.l10n.t

// MARK: - Family

@Serializable
data class Dependent(
    val id: String,
    val firstName: String,
    val lastName: String,
    val relation: Relation,
    val relationLabel: String,
    val birthDate: LocalDay,
    val status: ServerStatus,
    val guarantees: List<String> = emptyList(),
    val limits: Limits? = null,
    val pendingRequest: DependentRequest? = null,
) {
    val fullName: String get() = "$firstName $lastName"
}

@Serializable
data class DependentRequest(
    val id: String,
    val kind: Kind,
    val fullName: String,
    val status: ServerStatus,
    val createdAt: Timestamp,
    val message: String? = null,
) {
    @Serializable
    enum class Kind { add, remove }
}

@Serializable
data class DependentsResponse(
    val dependents: List<Dependent>,
    val requests: List<DependentRequest>,
    val canRequestChanges: Boolean,
    val info: String? = null,
)

@Serializable
data class DependentAddRequest(
    val firstName: String,
    val lastName: String,
    val relation: Relation,
    val birthDate: LocalDay,
    val gender: Gender,
    val uploadIds: List<String>,
)

@Serializable
data class DependentRemoveRequest(val reason: String)

// MARK: - Vault

@Serializable
enum class VaultCategory(val raw: String) {
    @SerialName("prescription") prescription("prescription"),
    @SerialName("lab_result") labResult("lab_result"),
    @SerialName("imaging") imaging("imaging"),
    @SerialName("vaccine") vaccine("vaccine");

    val label: String
        get() = when (this) {
            prescription -> t("Ordonnances")
            labResult -> t("Analyses")
            imaging -> t("Radios")
            vaccine -> t("Vaccins")
        }

    val symbol: String
        get() = when (this) {
            prescription -> "pills"
            labResult -> "testtube.2"
            imaging -> "rays"
            vaccine -> "syringe"
        }
}

@Serializable
data class VaultDocument(
    val id: String,
    val category: VaultCategory,
    val title: String,
    val fileName: String,
    val mimeType: String,
    val size: Long,
    val createdAt: Timestamp,
    val beneficiaryName: String? = null,
)

@Serializable
data class VaultDocumentCreate(
    val uploadId: String,
    val category: VaultCategory,
    val title: String,
    val beneficiaryId: String? = null,
)

// MARK: - Providers

@Serializable(with = ProviderTypeSerializer::class)
enum class ProviderType(val raw: String) {
    doctor("doctor"), specialist("specialist"), pharmacy("pharmacy"), clinic("clinic"),
    hospital("hospital"), laboratory("laboratory"), other("other");

    val label: String
        get() = when (this) {
            doctor -> t("Médecins")
            specialist -> t("Spécialistes")
            pharmacy -> t("Pharmacies")
            clinic -> t("Cliniques")
            hospital -> t("Hôpitaux")
            laboratory -> t("Laboratoires")
            other -> t("Autres")
        }

    val symbol: String
        get() = when (this) {
            doctor -> "stethoscope"
            specialist -> "heart.text.square"
            pharmacy -> "cross.case"
            clinic -> "building.2"
            hospital -> "cross"
            laboratory -> "testtube.2"
            other -> "mappin"
        }
}

object ProviderTypeSerializer : KSerializer<ProviderType> {
    override val descriptor = PrimitiveSerialDescriptor("ProviderType", PrimitiveKind.STRING)
    override fun serialize(encoder: Encoder, value: ProviderType) = encoder.encodeString(value.raw)
    override fun deserialize(decoder: Decoder): ProviderType {
        val raw = decoder.decodeString()
        return ProviderType.entries.firstOrNull { it.raw == raw } ?: ProviderType.other
    }
}

@Serializable
data class Provider(
    val id: String,
    val name: String,
    val type: ProviderType,
    val specialty: String? = null,
    val address: String,
    val city: String,
    val phone: String? = null,
    /** Coordinates are optional: providers without them appear in the list but not on the map. */
    val latitude: Double? = null,
    val longitude: Double? = null,
    /** True when the backend estimated the position (e.g. city centre): show it as approximate, route by address. */
    val locationApproximate: Boolean? = null,
    val distanceMeters: Int? = null,
    val tiersPayant: Boolean,
    val openingHours: String? = null,
)

@Serializable
data class ProvidersResponse(val providers: List<Provider>)

// MARK: - Notifications

@Serializable
data class DeepLinkTarget(val kind: Kind, val id: String? = null) {
    @Serializable
    enum class Kind { claim, payment, policy, dependents, card, vault, notifications, subscription }
}

@Serializable
data class AppNotification(
    val id: String,
    val category: String,
    val title: String,
    val body: String,
    val createdAt: Timestamp,
    val read: Boolean,
    val target: DeepLinkTarget? = null,
)

@Serializable
data class NotificationsResponse(val notifications: List<AppNotification>, val unreadCount: Int)

// MARK: - Uploads

/** tus-like resumable upload session (see `/uploads` in the OpenAPI spec). */
@Serializable
data class UploadSession(val uploadId: String, val chunkSize: Int, val offset: Long)

@Serializable
data class UploadCreateRequest(
    val fileName: String,
    val mimeType: String,
    val size: Long,
    /** "claim_document", "vault_document", "dependent_document", "profile_photo". */
    val purpose: String,
)
