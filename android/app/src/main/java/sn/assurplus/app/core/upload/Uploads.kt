package sn.assurplus.app.core.upload

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Color
import kotlinx.coroutines.delay
import kotlinx.coroutines.ensureActive
import kotlin.coroutines.coroutineContext
import sn.assurplus.app.core.model.UploadCreateRequest
import sn.assurplus.app.core.model.UploadSession
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.network.AssurApi
import java.io.ByteArrayOutputStream
import kotlin.math.max
import kotlin.math.min
import kotlin.math.roundToInt

class UploadFile(val data: ByteArray, val fileName: String, val mimeType: String)

interface Uploading {
    /** Uploads the file and returns the server `uploadId`. `progress` is called with 0…1. */
    suspend fun upload(file: UploadFile, purpose: String, progress: (Double) -> Unit): String
}

/**
 * tus-like resumable uploads: create a session, send chunks with `Upload-Offset`, and on any retryable failure ask
 * the server for the committed offset and continue from there (exponential backoff).
 */
class ResumableUploader(
    private val api: AssurApi,
    private val maxAttempts: Int = 6,
    private val baseDelayMs: Long = 1000,
) : Uploading {
    override suspend fun upload(file: UploadFile, purpose: String, progress: (Double) -> Unit): String {
        val total = file.data.size.toLong()
        var session = withRetry { api.createUpload(UploadCreateRequest(file.fileName, file.mimeType, total, purpose)) }
        progress(session.offset.toDouble() / max(total, 1))

        var failures = 0
        while (session.offset < total) {
            coroutineContext.ensureActive()
            val end = min(session.offset + max(session.chunkSize, 64 * 1024), total)
            val chunk = file.data.copyOfRange(session.offset.toInt(), end.toInt())
            try {
                session = api.uploadChunk(session.uploadId, session.offset, chunk)
                failures = 0
                progress(session.offset.toDouble() / max(total, 1))
            } catch (error: APIError) {
                if (!error.isRetryable && !(error is APIError.Server && error.status == 409)) throw error
                failures++
                if (failures >= maxAttempts) throw error
                delay(baseDelayMs * (1L shl (failures - 1)))
                // The chunk may have been partially committed: resume from the server's offset.
                val uploadId = session.uploadId
                session = withRetry { api.uploadOffset(uploadId) }
            }
        }
        progress(1.0)
        return session.uploadId
    }

    private suspend fun <T> withRetry(operation: suspend () -> T): T {
        var attempt = 0
        while (true) {
            try {
                return operation()
            } catch (error: APIError) {
                if (!error.isRetryable || attempt + 1 >= maxAttempts) throw error
                attempt++
                delay(baseDelayMs * (1L shl (attempt - 1)))
            }
        }
    }
}

/** Shrinks photos before upload: any decodable input → JPEG, long edge ≤ 1600 px, quality 70. */
object ImageCompressor {
    const val MAX_LONG_EDGE = 1600
    const val QUALITY = 70

    fun jpeg(data: ByteArray): ByteArray? = BitmapFactory.decodeByteArray(data, 0, data.size)?.let { jpeg(it) }

    fun jpeg(bitmap: Bitmap): ByteArray {
        val out = ByteArrayOutputStream()
        resized(bitmap).compress(Bitmap.CompressFormat.JPEG, QUALITY, out)
        return out.toByteArray()
    }

    fun targetSize(width: Int, height: Int): Pair<Int, Int> {
        val longEdge = max(width, height)
        if (longEdge <= MAX_LONG_EDGE) return width to height
        val scale = MAX_LONG_EDGE.toDouble() / longEdge
        return (width * scale).roundToInt() to (height * scale).roundToInt()
    }

    fun resized(bitmap: Bitmap): Bitmap {
        val (w, h) = targetSize(bitmap.width, bitmap.height)
        return if (w == bitmap.width && h == bitmap.height) bitmap else Bitmap.createScaledBitmap(bitmap, w, h, true)
    }

    /** Merges scanned pages into one tall image (keeps the upload contract to a single file). */
    fun merge(pages: List<Bitmap>): Bitmap? {
        if (pages.size <= 1) return pages.firstOrNull()
        val width = pages.maxOf { it.width }
        val height = pages.sumOf { (it.height * (width.toDouble() / max(it.width, 1))).roundToInt() }
        val result = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(result)
        canvas.drawColor(Color.WHITE)
        var y = 0
        for (page in pages) {
            val pageHeight = (page.height * (width.toDouble() / max(page.width, 1))).roundToInt()
            canvas.drawBitmap(Bitmap.createScaledBitmap(page, width, pageHeight, true), 0f, y.toFloat(), null)
            y += pageHeight
        }
        return result
    }
}

@Suppress("unused")
private fun UploadSession.done(total: Long) = offset >= total
