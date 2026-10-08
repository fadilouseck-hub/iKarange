package sn.assurplus.app.mock

import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import android.text.Layout
import android.text.StaticLayout
import android.text.TextPaint
import sn.assurplus.app.tenant.Tenant
import java.io.ByteArrayOutputStream

/** Generated stand-ins for server files (PDFs, sample invoice) so the mock flavor ships no binary fixtures. */
object MockDocuments {
    fun pdf(title: String, lines: List<String>): ByteArray {
        val document = PdfDocument()
        val width = 595
        val height = 842 // A4 in points
        val titlePaint = TextPaint().apply {
            textSize = 20f; typeface = Typeface.DEFAULT_BOLD; color = (0xFF000000 or Tenant.hex(Tenant.current.colors.brandDark)).toInt(); isAntiAlias = true
        }
        val bodyPaint = TextPaint().apply { textSize = 12f; color = Color.BLACK; isAntiAlias = true }
        val notePaint = TextPaint().apply { textSize = 10f; typeface = Typeface.create(Typeface.DEFAULT, Typeface.ITALIC); color = Color.GRAY; isAntiAlias = true }
        var pageNumber = 1
        var page = document.startPage(PdfDocument.PageInfo.Builder(width, height, pageNumber).create())
        var canvas = page.canvas
        canvas.drawText("${Tenant.current.displayName} — $title", 48f, 68f, titlePaint)
        var y = 96f
        fun footer() = canvas.drawText("Document de démonstration — sans valeur contractuelle.", 48f, height - 32f, notePaint)
        for (line in lines) {
            val layout = StaticLayout.Builder.obtain(line, 0, line.length, bodyPaint, width - 96).setAlignment(Layout.Alignment.ALIGN_NORMAL).build()
            if (y + layout.height > height - 72) {
                footer()
                document.finishPage(page)
                page = document.startPage(PdfDocument.PageInfo.Builder(width, height, ++pageNumber).create())
                canvas = page.canvas
                y = 48f
            }
            canvas.save(); canvas.translate(48f, y); layout.draw(canvas); canvas.restore()
            y += layout.height + 8
        }
        footer()
        document.finishPage(page)
        val out = ByteArrayOutputStream()
        document.writeTo(out)
        document.close()
        return out.toByteArray()
    }

    /** A fake pharmacy receipt, used in the mock flavor when no camera / scanner is available (emulator, UI tests). */
    fun sampleInvoice(): Bitmap {
        val bitmap = Bitmap.createBitmap(900, 1300, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bitmap)
        canvas.drawColor(Color.WHITE)
        val mono = Paint().apply { typeface = Typeface.MONOSPACE; textSize = 30f; color = Color.BLACK; isAntiAlias = true }
        val bold = Paint(mono).apply { typeface = Typeface.create(Typeface.MONOSPACE, Typeface.BOLD); textSize = 36f }
        val rows = listOf(
            "PHARMACIE DÉMO MERMOZ" to bold, "VDN, Mermoz — Dakar" to mono, "" to mono,
            "Facture F-2026-10-0457" to mono, "Date : 03/10/2026" to mono, "" to mono,
            "Paracétamol 500mg  2 x 1500   3000" to mono, "Amoxicilline 1g    1 x 12500 12500" to mono,
            "Sirop antitussif   1 x 4500   4500" to mono, "" to mono,
            "TOTAL                     20000 FCFA" to bold, "" to mono, "*** EXEMPLE — DOCUMENT FICTIF ***" to mono,
        )
        var y = 110f
        for ((text, paint) in rows) {
            canvas.drawText(text, 60f, y, paint)
            y += 64f
        }
        return bitmap
    }

    fun sampleInvoiceJpeg(): ByteArray = ByteArrayOutputStream().also { sampleInvoice().compress(Bitmap.CompressFormat.JPEG, 70, it) }.toByteArray()
}
