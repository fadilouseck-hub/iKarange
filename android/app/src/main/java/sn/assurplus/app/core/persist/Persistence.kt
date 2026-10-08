package sn.assurplus.app.core.persist

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.KSerializer
import sn.assurplus.app.core.model.JsonCoding
import sn.assurplus.app.core.network.APIError
import java.io.File
import java.time.Instant
import java.util.UUID

/**
 * Local files holding personal or medical data: app-private `noBackupFilesDir` (never backed up, removed with
 * the app). Counterpart of the iOS `ProtectedStorage` (`NSFileProtectionComplete`, excluded from backups).
 */
object ProtectedStorage {
    private lateinit var root: File

    fun init(context: Context) {
        root = context.noBackupFilesDir
    }

    fun directory(name: String): File = File(root, name).apply { mkdirs() }

    /** Writes a file (vault document, PDF) to a protected location and returns it. */
    fun write(data: ByteArray, fileName: String, folder: String = "Files"): File =
        File(directory(folder), sanitize(fileName)).apply { writeBytes(data) }

    /** Removes every locally cached file (called on logout). */
    fun wipe() {
        directory("Files").deleteRecursively()
    }

    fun sanitize(name: String): String {
        val cleaned = name.map { if (it.isLetterOrDigit() || it in "._-") it else '_' }.joinToString("")
        return cleaned.ifEmpty { UUID.randomUUID().toString() }
    }
}

enum class CacheKey {
    me, dashboard, card, policy, dependents, claims, claimTypes, providers, payments, notifications,
    vault, products, qrTokens, claimDraft
}

/** Read-only offline cache of API responses (dashboard, card, contract, dependants, claims, providers…). */
interface ResponseCache {
    fun <T> load(serializer: KSerializer<T>, key: CacheKey): T?
    fun <T> store(serializer: KSerializer<T>, value: T, key: CacheKey)
    fun remove(key: CacheKey)
    fun clear()
}

/** One JSON file per key in protected storage; a corrupt entry is ignored and never blocks the app. */
class FileResponseCache : ResponseCache {
    private fun file(key: CacheKey) = File(ProtectedStorage.directory("Cache"), "${key.name}.json")

    override fun <T> load(serializer: KSerializer<T>, key: CacheKey): T? =
        runCatching { JsonCoding.json.decodeFromString(serializer, file(key).readText()) }.getOrNull()

    override fun <T> store(serializer: KSerializer<T>, value: T, key: CacheKey) {
        runCatching { file(key).writeText(JsonCoding.json.encodeToString(serializer, value)) }
    }

    override fun remove(key: CacheKey) {
        file(key).delete()
    }

    override fun clear() {
        ProtectedStorage.directory("Cache").listFiles()?.forEach { it.delete() }
    }
}

class InMemoryResponseCache : ResponseCache {
    private val entries = mutableMapOf<CacheKey, String>()
    override fun <T> load(serializer: KSerializer<T>, key: CacheKey): T? =
        entries[key]?.let { runCatching { JsonCoding.json.decodeFromString(serializer, it) }.getOrNull() }
    override fun <T> store(serializer: KSerializer<T>, value: T, key: CacheKey) {
        entries[key] = JsonCoding.json.encodeToString(serializer, value)
    }
    override fun remove(key: CacheKey) { entries.remove(key) }
    override fun clear() = entries.clear()
}

/**
 * Stale-while-revalidate state for one API resource: shows the cached copy immediately (offline use), refreshes
 * from the network, keeps the cached value when the refresh fails. Compose state, so screens re-render.
 */
class RemoteResource<T>(
    private val cache: ResponseCache? = null,
    private val key: CacheKey? = null,
    private val serializer: KSerializer<T>? = null,
    private val fetch: suspend () -> T,
) {
    var value by mutableStateOf<T?>(if (cache != null && key != null && serializer != null) cache.load(serializer, key) else null)
        private set
    var isLoading by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)
        private set
    var lastUpdated by mutableStateOf<Instant?>(null)
        private set

    suspend fun load() {
        if (isLoading) return
        isLoading = true
        try {
            update(fetch())
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            val apiError = APIError.wrap(e)
            if (apiError != APIError.Cancelled) error = apiError
        } finally {
            isLoading = false
        }
    }

    suspend fun refresh() = load()

    /** Replaces the value locally (after a mutation) and persists it. */
    fun update(newValue: T) {
        value = newValue
        lastUpdated = Instant.now()
        if (cache != null && key != null && serializer != null) cache.store(serializer, newValue, key)
    }
}
