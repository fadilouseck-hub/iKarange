package sn.assurplus.app.core.model

import kotlinx.serialization.KSerializer
import kotlinx.serialization.Serializable
import kotlinx.serialization.SerializationException
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.Json
import java.time.Instant
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/**
 * JSON conventions shared with the API: camelCase keys, ISO-8601 timestamps, `yyyy-MM-dd` calendar days
 * (see [LocalDay]), amounts as integer XOF.
 */
object JsonCoding {
    val json: Json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
        encodeDefaults = true
    }

    fun parseTimestamp(value: String): Instant? =
        runCatching { OffsetDateTime.parse(value, DateTimeFormatter.ISO_OFFSET_DATE_TIME).toInstant() }.getOrNull()
            ?: runCatching { Instant.parse(value) }.getOrNull()
            ?: LocalDay.parse(value)?.date?.atStartOfDay(ZoneId.systemDefault())?.toInstant()
}

object InstantSerializer : KSerializer<Instant> {
    override val descriptor = PrimitiveSerialDescriptor("Instant", PrimitiveKind.STRING)
    override fun serialize(encoder: Encoder, value: Instant) = encoder.encodeString(DateTimeFormatter.ISO_INSTANT.format(value))
    override fun deserialize(decoder: Decoder): Instant {
        val raw = decoder.decodeString()
        return JsonCoding.parseTimestamp(raw) ?: throw SerializationException("Date ISO-8601 invalide: $raw")
    }
}

typealias Timestamp = @Serializable(with = InstantSerializer::class) Instant

/** A calendar day without time (birth dates, contract start/end). Encoded as `yyyy-MM-dd`. */
@Serializable(with = LocalDaySerializer::class)
data class LocalDay(val year: Int, val month: Int, val day: Int) : Comparable<LocalDay> {
    val date: LocalDate get() = LocalDate.of(year, month, day)
    val isoString: String get() = "%04d-%02d-%02d".format(year, month, day)

    override fun compareTo(other: LocalDay): Int = compareValuesBy(this, other, { it.year }, { it.month }, { it.day })

    companion object {
        fun parse(value: String): LocalDay? {
            val parts = value.take(10).split("-").mapNotNull { it.toIntOrNull() }
            if (parts.size != 3 || parts[1] !in 1..12 || parts[2] !in 1..31) return null
            return LocalDay(parts[0], parts[1], parts[2])
        }

        fun of(date: LocalDate) = LocalDay(date.year, date.monthValue, date.dayOfMonth)
        fun today() = of(LocalDate.now())
    }
}

object LocalDaySerializer : KSerializer<LocalDay> {
    override val descriptor = PrimitiveSerialDescriptor("LocalDay", PrimitiveKind.STRING)
    override fun serialize(encoder: Encoder, value: LocalDay) = encoder.encodeString(value.isoString)
    override fun deserialize(decoder: Decoder): LocalDay {
        val raw = decoder.decodeString()
        return LocalDay.parse(raw) ?: throw SerializationException("Jour invalide: $raw")
    }
}

/** Decodes an empty or ignored response body (204, or bodies the app does not need). */
@Serializable
class EmptyResponse
