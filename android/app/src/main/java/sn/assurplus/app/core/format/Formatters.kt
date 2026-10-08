package sn.assurplus.app.core.format

import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.model.LocalDay
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import kotlin.math.abs
import kotlin.math.roundToInt

/** XOF amounts: integers, no decimals, displayed as `20 000 FCFA`. */
object Money {
    fun format(amount: Long): String {
        val digits = abs(amount).toString()
        val groups = digits.reversed().chunked(3).map { it.reversed() }.reversed()
        // Non-breaking spaces so an amount never wraps across lines.
        return (if (amount < 0) "-" else "") + groups.joinToString(" ") + " FCFA"
    }

    fun format(amount: Int): String = format(amount.toLong())
}

/** Senegalese phone numbers: 9 national digits, +221 prefix, displayed as `+221 77 123 45 67`. */
object PhoneNumber {
    const val COUNTRY_CODE = "221"
    private val validPrefixes = setOf("70", "71", "75", "76", "77", "78", "33")

    /** The 9 national digits, or null when the input is not a Senegalese number. */
    fun nationalDigits(input: String): String? {
        var digits = input.filter { it.isDigit() }
        if (digits.startsWith("00221")) digits = digits.drop(5)
        else if (digits.startsWith(COUNTRY_CODE) && digits.length == 12) digits = digits.drop(3)
        if (digits.length != 9 || digits.take(2) !in validPrefixes) return null
        return digits
    }

    fun isValid(input: String): Boolean = nationalDigits(input) != null

    /** E.164 form sent to the API. */
    fun e164(input: String): String? = nationalDigits(input)?.let { "+$COUNTRY_CODE$it" }

    fun display(input: String): String {
        val d = nationalDigits(input) ?: return input
        return "+221 ${d.substring(0, 2)} ${d.substring(2, 5)} ${d.substring(5, 7)} ${d.substring(7, 9)}"
    }

    /** Formats as the user types (national part only, max 9 digits). */
    fun formatInput(input: String): String {
        val digits = input.filter { it.isDigit() }.take(9)
        val result = StringBuilder()
        digits.forEachIndexed { index, char ->
            if (index == 2 || index == 5 || index == 7) result.append(' ')
            result.append(char)
        }
        return result.toString()
    }
}

/** Dates in the app language with Senegalese conventions (`3 octobre 2026`, `03/10/2026`). */
object DateText {
    private val zone: ZoneId get() = ZoneId.systemDefault()

    fun day(day: LocalDay): String = DateTimeFormatter.ofPattern("d MMMM yyyy", L10n.locale).format(day.date)

    fun day(instant: Instant): String =
        DateTimeFormatter.ofPattern("d MMMM yyyy", L10n.locale).format(instant.atZone(zone))

    fun short(instant: Instant): String =
        DateTimeFormatter.ofPattern("dd/MM/yyyy", L10n.locale).format(instant.atZone(zone))

    fun dateTime(instant: Instant): String {
        val pattern = if (L10n.code == "fr") "d MMM yyyy 'à' HH:mm" else "d MMM yyyy 'at' HH:mm"
        return DateTimeFormatter.ofPattern(pattern, L10n.locale).format(instant.atZone(zone))
    }

    /** "il y a 2 heures" / "2 hours ago", "hier" / "yesterday" (iOS `.relative(presentation: .named)`). */
    fun relative(instant: Instant, now: Instant = Instant.now()): String {
        val fr = L10n.code == "fr"
        val seconds = ChronoUnit.SECONDS.between(instant, now)
        val minutes = seconds / 60
        val hours = minutes / 60
        val days = ChronoUnit.DAYS.between(instant.atZone(zone).toLocalDate(), now.atZone(zone).toLocalDate())
        fun plural(n: Long, frUnit: String, enUnit: String): String =
            if (fr) "il y a $n $frUnit${if (n > 1 && !frUnit.endsWith("s")) "s" else ""}"
            else "$n $enUnit${if (n > 1) "s" else ""} ago"
        return when {
            seconds < 60 -> if (fr) "maintenant" else "now"
            minutes < 60 -> plural(minutes, "minute", "minute")
            hours < 24 && days == 0L -> plural(hours, "heure", "hour")
            days == 1L -> if (fr) "hier" else "yesterday"
            days < 7 -> plural(days, "jour", "day")
            days < 30 -> plural(days / 7, "semaine", "week")
            days < 365 -> if (fr) "il y a ${days / 30} mois" else plural(days / 30, "", "month").trim()
            else -> plural(days / 365, "an", "year")
        }
    }
}

object Percent {
    fun format(value: Int): String = "$value %"
    fun confidence(value: Double): String = "${(value * 100).roundToInt()} %"
}
