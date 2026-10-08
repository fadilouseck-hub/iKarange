package sn.assurplus.app.features.card

import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.RectF
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import com.google.zxing.qrcode.encoder.Encoder
import sn.assurplus.app.tenant.Tenant
import kotlin.math.ceil

/** The QR code's module grid, computed locally with ZXing. The payload is the server's signed token only. */
class QRMatrix(val size: Int, private val modules: BooleanArray) {
    operator fun get(x: Int, y: Int): Boolean = x in 0 until size && y in 0 until size && modules[y * size + x]

    /** Top-left corner of each 7×7 finder pattern. */
    val finderOrigins: List<Pair<Int, Int>> get() = listOf(0 to 0, (size - 7) to 0, 0 to (size - 7))

    fun isInFinder(x: Int, y: Int) = finderOrigins.any { (fx, fy) -> x >= fx && x < fx + 7 && y >= fy && y < fy + 7 }

    companion object {
        /** Correction level "H" (≈30 % recoverable) leaves room for the centre logo. */
        fun make(payload: String): QRMatrix? = runCatching {
            val code = Encoder.encode(payload, ErrorCorrectionLevel.H, mapOf(EncodeHintType.CHARACTER_SET to "UTF-8"))
            val matrix = code.matrix
            val size = matrix.width
            QRMatrix(size, BooleanArray(size * size) { matrix.get(it % size, it / size).toInt() == 1 })
        }.getOrNull()
    }
}

object QRCode {
    class Style(
        // Deep brand colour: high contrast on white for scanners.
        val foreground: Int = brandDark(),
        val eyeAccent: Int = brandDark(),
        val background: Int = 0xFFFFFFFF.toInt(),
        val logoBackground: Int = brandDark(),
        val logoMark: Int = (0xFF000000 or Tenant.hex(Tenant.current.colors.brandAccent)).toInt(),
        /** Dot diameter relative to the module size. */
        val dotScale: Float = 0.82f,
        /** Logo side relative to the symbol (≤ 0.24 keeps it well within "H" correction). */
        val logoRatio: Float = 0.22f,
        /** Quiet zone in modules (the white card padding adds to it). */
        val quietZone: Int = 2,
    )

    private fun brandDark() = (0xFF000000 or Tenant.hex(Tenant.current.colors.brandDark)).toInt()

    /** Plain square-module image (kept for simple uses and tests). */
    fun image(payload: String, side: Int = 512): Bitmap? = runCatching {
        val bits = QRCodeWriter().encode(payload, BarcodeFormat.QR_CODE, side, side, mapOf(EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M))
        Bitmap.createBitmap(side, side, Bitmap.Config.ARGB_8888).apply {
            for (y in 0 until side) for (x in 0 until side) setPixel(x, y, if (bits[x, y]) 0xFF000000.toInt() else 0xFFFFFFFF.toInt())
        }
    }.getOrNull()

    /** Rounded "dots" modules, rounded finder eyes and the tenant mark in the centre (same drawing as iOS). */
    fun styledImage(payload: String, side: Int = 720, style: Style = Style()): Bitmap? {
        val matrix = QRMatrix.make(payload) ?: return null
        val total = (matrix.size + style.quietZone * 2).toFloat()
        val cell = side / total
        val origin = style.quietZone * cell

        // Modules hidden behind the logo, rounded out to whole modules plus a 1-module margin.
        val logoModules = ceil(matrix.size * style.logoRatio).toInt() or 1 // odd → centred
        val logoStart = (matrix.size - logoModules) / 2
        val clearRange = (logoStart - 1)..(logoStart + logoModules)

        val bitmap = Bitmap.createBitmap(side, side, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bitmap)
        canvas.drawColor(style.background)
        val fill = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.style = Paint.Style.FILL; color = style.foreground }

        // Data modules as dots.
        val inset = cell * (1 - style.dotScale) / 2
        for (y in 0 until matrix.size) for (x in 0 until matrix.size) {
            if (!matrix[x, y] || matrix.isInFinder(x, y)) continue
            if (x in clearRange && y in clearRange) continue
            canvas.drawOval(RectF(origin + x * cell + inset, origin + y * cell + inset, origin + (x + 1) * cell - inset, origin + (y + 1) * cell - inset), fill)
        }

        // Finder eyes: round ring and round pupil (UIKit renders the iOS rounded rects at these radii as circles).
        val stroke = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.style = Paint.Style.STROKE; strokeWidth = cell; color = style.foreground }
        for ((fx, fy) in matrix.finderOrigins) {
            val cx = origin + (fx + 3.5f) * cell
            val cy = origin + (fy + 3.5f) * cell
            canvas.drawCircle(cx, cy, 3f * cell, stroke)
            fill.color = style.eyeAccent
            canvas.drawCircle(cx, cy, 1.5f * cell, fill)
        }

        // Centre logo: teal rounded square with the mint "+".
        val logoSide = logoModules * cell
        val left = origin + logoStart * cell
        val top = origin + logoStart * cell
        fill.color = style.logoBackground
        canvas.drawRoundRect(RectF(left, top, left + logoSide, top + logoSide), logoSide * 0.28f, logoSide * 0.28f, fill)
        fill.color = style.logoMark
        val bar = logoSide * 0.17f
        val length = logoSide * 0.56f
        val cx = left + logoSide / 2
        val cy = top + logoSide / 2
        canvas.drawRoundRect(RectF(cx - bar / 2, cy - length / 2, cx + bar / 2, cy + length / 2), bar * 0.35f, bar * 0.35f, fill)
        canvas.drawRoundRect(RectF(cx - length / 2, cy - bar / 2, cx + length / 2, cy + bar / 2), bar * 0.35f, bar * 0.35f, fill)
        return bitmap
    }
}

/** Re-rendering every second (countdown) would be wasteful: keep the last styled image per token. */
object StyledQRCache {
    private var last: Pair<String, Bitmap>? = null

    fun image(token: String): Bitmap? {
        last?.let { if (it.first == token) return it.second }
        return QRCode.styledImage(token)?.also { last = token to it }
    }
}

