package sn.assurplus.app.core.network

import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import okhttp3.Call
import okhttp3.Callback
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.AuthTokens
import sn.assurplus.app.core.model.EmptyResponse
import sn.assurplus.app.core.model.JsonCoding
import sn.assurplus.app.core.security.TokenStore
import java.io.IOException
import java.util.concurrent.TimeUnit
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

enum class HttpMethod { GET, POST, PUT, PATCH, DELETE }

/** A request against the ASSUR+ API (`docs/api/openapi.yaml`). */
data class Endpoint(
    val method: HttpMethod,
    val path: String,
    val query: List<Pair<String, String>> = emptyList(),
    val body: ByteArray? = null,
    val headers: Map<String, String> = emptyMap(),
    val requiresAuth: Boolean = true,
) {
    companion object {
        /** JSON body endpoint. */
        inline fun <reified B> json(method: HttpMethod, path: String, body: B, requiresAuth: Boolean = true) = Endpoint(
            method, path,
            body = JsonCoding.json.encodeToString(body).toByteArray(),
            headers = mapOf("Content-Type" to "application/json"),
            requiresAuth = requiresAuth,
        )

        /** Builds query items, dropping null values. */
        fun query(vararg pairs: Pair<String, String?>): List<Pair<String, String>> =
            pairs.mapNotNull { (name, value) -> value?.let { name to it } }
    }
}

data class HttpRequest(val method: String, val url: String, val headers: Map<String, String>, val body: ByteArray?)

data class HttpResponse(val status: Int, val body: ByteArray, val contentType: String? = null)

/** The single seam between the app and the network: OkHttp in production, `MockServer` in the mock flavor. */
interface HttpTransport {
    suspend fun send(request: HttpRequest): HttpResponse
}

class OkHttpTransport(private val client: OkHttpClient = defaultClient()) : HttpTransport {
    override suspend fun send(request: HttpRequest): HttpResponse {
        val contentType = request.headers["Content-Type"]?.toMediaTypeOrNull()
        val body = request.body?.toRequestBody(contentType)
            ?: if (request.method in setOf("POST", "PUT", "PATCH")) ByteArray(0).toRequestBody(contentType) else null
        val builder = Request.Builder().url(request.url).method(request.method, body)
        request.headers.forEach { (name, value) -> builder.header(name, value) }
        val call = client.newCall(builder.build())
        return suspendCancellableCoroutine { continuation ->
            continuation.invokeOnCancellation { call.cancel() }
            call.enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) = continuation.resumeWithException(e)
                override fun onResponse(call: Call, response: Response) {
                    response.use {
                        continuation.resume(HttpResponse(it.code, it.body.bytes(), it.header("Content-Type")))
                    }
                }
            })
        }
    }

    companion object {
        /** No HTTP cache: health data must not land on disk outside the app's protected storage. */
        fun defaultClient(): OkHttpClient = OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .callTimeout(120, TimeUnit.SECONDS)
            .cache(null)
            .build()
    }
}

/**
 * Typed HTTP client: builds requests, attaches the bearer token, refreshes it once on 401 (rotation), maps errors
 * to [APIError]. Contains no business logic.
 */
class APIClient(
    val baseURL: String,
    private val transport: HttpTransport,
    val tokens: TokenStore,
) {
    /** Called when the refresh token is rejected; `AuthSession` logs the user out. */
    var onSessionExpired: () -> Unit = {}

    private val refreshMutex = Mutex()
    private var inFlightRefresh: CompletableDeferred<Unit>? = null

    suspend inline fun <reified R> send(endpoint: Endpoint): R {
        val data = sendRaw(endpoint)
        if (R::class == EmptyResponse::class) return EmptyResponse() as R
        return try {
            JsonCoding.json.decodeFromString<R>(data.decodeToString())
        } catch (error: Exception) {
            throw APIError.Decoding(error.toString())
        }
    }

    /** Returns the raw body (PDF, files). */
    suspend fun sendRaw(endpoint: Endpoint, allowRefresh: Boolean = true): ByteArray {
        val response = try {
            transport.send(makeRequest(endpoint))
        } catch (cancelled: kotlinx.coroutines.CancellationException) {
            throw cancelled // structured concurrency: never turn cancellation into an API error
        } catch (error: Throwable) {
            throw APIError.wrap(error)
        }
        return when {
            response.status in 200..299 -> response.body
            response.status == 401 && endpoint.requiresAuth && allowRefresh -> {
                refreshTokens()
                sendRaw(endpoint, allowRefresh = false)
            }
            response.status == 401 && endpoint.requiresAuth -> {
                expireSession()
                throw APIError.Unauthorized
            }
            else -> throw error(response.body, response.status)
        }
    }

    fun makeRequest(endpoint: Endpoint): HttpRequest {
        val url = (baseURL.trimEnd('/') + "/" + endpoint.path).toHttpUrl().newBuilder().apply {
            endpoint.query.forEach { (name, value) -> addQueryParameter(name, value) }
        }.build().toString()
        val headers = linkedMapOf("Accept" to "application/json", "Accept-Language" to L10n.code)
        headers.putAll(endpoint.headers)
        if (endpoint.requiresAuth) tokens.load()?.accessToken?.let { headers["Authorization"] = "Bearer $it" }
        return HttpRequest(endpoint.method.name, url, headers, endpoint.body)
    }

    /** Concurrent 401s share one refresh call. */
    suspend fun refreshTokens() {
        val (deferred, owner) = refreshMutex.withLock {
            inFlightRefresh?.let { it to false } ?: CompletableDeferred<Unit>().also { inFlightRefresh = it }.let { it to true }
        }
        if (!owner) return deferred.await()
        try {
            performRefresh()
            deferred.complete(Unit)
        } catch (error: Throwable) {
            deferred.completeExceptionally(error)
            throw error
        } finally {
            refreshMutex.withLock { inFlightRefresh = null }
        }
    }

    private suspend fun performRefresh() {
        val current = tokens.load() ?: run {
            expireSession()
            throw APIError.Unauthorized
        }
        try {
            val fresh: AuthTokens = send(
                Endpoint.json(HttpMethod.POST, "auth/refresh", mapOf("refreshToken" to current.refreshToken), requiresAuth = false)
            )
            tokens.save(fresh)
        } catch (error: APIError) {
            // Offline or 5xx: keep the session so the user can retry. Rejected token: log out.
            if (error is APIError.Server && error.status in 400..499) {
                expireSession()
                throw APIError.Unauthorized
            }
            throw error
        }
    }

    private fun expireSession() {
        tokens.clear()
        onSessionExpired()
    }

    companion object {
        fun error(data: ByteArray, status: Int): APIError {
            val envelope = runCatching { JsonCoding.json.decodeFromString<APIErrorEnvelope>(data.decodeToString()) }.getOrNull()
            if (envelope != null) {
                return APIError.Server(status, envelope.error.code, envelope.error.message, envelope.error.fields.orEmpty())
            }
            return APIError.Server(status, "http_$status", t("Le service est momentanément indisponible. Veuillez réessayer."), emptyMap())
        }
    }
}
