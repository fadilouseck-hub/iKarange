package sn.assurplus.app.core.network

import kotlinx.coroutines.CancellationException
import kotlinx.serialization.SerializationException
import kotlinx.serialization.Serializable
import sn.assurplus.app.core.l10n.t
import java.io.InterruptedIOException
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException

/** Every error surfaced to the UI. Server messages are already in French and shown as-is. */
sealed class APIError : Exception() {
    /** `{ "error": { "code", "message", "fields" } }` returned by the API. */
    data class Server(val status: Int, val code: String, val serverMessage: String, val fields: Map<String, String>) : APIError()
    /** Refresh failed: the session is over and the user must log in again. */
    data object Unauthorized : APIError()
    data object Offline : APIError()
    data object Timeout : APIError()
    data class Decoding(val detail: String) : APIError()
    data object InvalidResponse : APIError()
    data class Transport(val detail: String) : APIError()
    data object Cancelled : APIError()

    val userMessage: String
        get() = when (this) {
            is Server -> serverMessage
            Unauthorized -> t("Votre session a expiré. Veuillez vous reconnecter.")
            Offline -> t("Pas de connexion internet. Les données affichées peuvent ne pas être à jour.")
            Timeout -> t("Le réseau est lent. Veuillez réessayer.")
            is Decoding, InvalidResponse -> t("Réponse inattendue du serveur. Veuillez réessayer plus tard.")
            is Transport -> t("Une erreur réseau est survenue. Veuillez réessayer.")
            Cancelled -> t("Opération annulée.")
        }

    override val message: String get() = userMessage

    /** Field-level validation messages, keyed by the request field name. */
    val fieldErrors: Map<String, String> get() = (this as? Server)?.fields ?: emptyMap()

    val isRetryable: Boolean
        get() = when (this) {
            Offline, Timeout, is Transport -> true
            is Server -> status >= 500 || status == 429
            else -> false
        }

    companion object {
        fun wrap(error: Throwable): APIError = when (error) {
            is APIError -> error
            is CancellationException -> Cancelled
            is UnknownHostException, is ConnectException -> Offline
            is SocketTimeoutException -> Timeout
            is InterruptedIOException -> Timeout
            is SerializationException, is IllegalArgumentException -> Decoding(error.toString())
            else -> Transport(error::class.java.simpleName)
        }
    }
}

/** Wire format of API errors. */
@Serializable
data class APIErrorEnvelope(val error: Body) {
    @Serializable
    data class Body(val code: String, val message: String, val fields: Map<String, String>? = null)
}
