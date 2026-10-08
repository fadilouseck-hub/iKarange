package sn.assurplus.app.core.l10n

import android.content.Context
import android.content.SharedPreferences
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonPrimitive
import sn.assurplus.app.tenant.Tenant
import java.util.Locale

/**
 * Language of the app UI, same model as iOS: strings are written in French (the source language) in code and
 * translated through tables exported from the iOS String Catalog (`tools/export_strings.py`). The current code is
 * Compose state, so every `t(…)` read during composition re-renders when the user switches language in Profile.
 *
 * Format specifiers follow the catalog: `%@` (any value), `%lld` (integer), positional `%1$@`, `%%`.
 */
object L10n {
    private var current by mutableStateOf("fr")
    private var tables: Map<String, Map<String, String>> = emptyMap()

    /** "fr", "en". Safe to read anywhere (formatters, API headers); observed when read in composition. */
    val code: String get() = current

    /** Keeps Senegalese regional conventions (dates, numbers) in every language. */
    val locale: Locale get() = locale(code)

    fun locale(code: String): Locale = Locale.Builder().setLanguage(code).setRegion("SN").build()

    fun loadTables(context: Context) {
        val loaded = mutableMapOf<String, Map<String, String>>()
        for (file in context.assets.list("l10n").orEmpty()) {
            val lang = file.removeSuffix(".json")
            val text = context.assets.open("l10n/$file").bufferedReader().use { it.readText() }
            val json = Json.parseToJsonElement(text) as JsonObject
            loaded[lang] = json.mapValues { it.value.jsonPrimitive.content }
        }
        tables = loaded
    }

    fun set(code: String) {
        current = code
    }

    fun translate(key: String, args: Array<out Any?>): String {
        val template = if (current == "fr") key else tables[current]?.get(key) ?: key
        return if (args.isEmpty() && !template.contains('%')) template else format(template, args)
    }

    /** Minimal printf for the catalog's specifiers; unknown specifiers are left as-is. */
    internal fun format(template: String, args: Array<out Any?>): String {
        val out = StringBuilder()
        var next = 0
        var i = 0
        while (i < template.length) {
            val c = template[i]
            if (c != '%' || i + 1 >= template.length) {
                out.append(c); i++; continue
            }
            if (template[i + 1] == '%') {
                out.append('%'); i += 2; continue
            }
            var j = i + 1
            var position: Int? = null
            val digits = StringBuilder()
            while (j < template.length && template[j].isDigit()) digits.append(template[j++])
            if (digits.isNotEmpty() && j < template.length && template[j] == '$') {
                position = digits.toString().toInt() - 1
                j++
            } else {
                j = i + 1
            }
            val spec = when {
                template.startsWith("@", j) -> "@"
                template.startsWith("lld", j) -> "lld"
                template.startsWith("ld", j) -> "ld"
                template.startsWith("d", j) -> "d"
                else -> null
            }
            if (spec == null) {
                out.append(c); i++; continue
            }
            val index = position ?: next++
            out.append(args.getOrNull(index)?.toString() ?: "")
            i = j + spec.length
        }
        return out.toString()
    }
}

/** Translates a French source string (same keys as the iOS String Catalog). */
fun t(key: String, vararg args: Any?): String = L10n.translate(key, args)

/**
 * Initial value follows the device when it is supported by the tenant, otherwise the tenant default (French).
 * A choice made in Profile overrides it and persists. Changes apply immediately.
 */
class LanguageSettings(private val prefs: SharedPreferences, tenant: Tenant, preferred: List<Locale>) {
    val supported: List<String> = tenant.supportedLanguages
    var followsDevice by mutableStateOf(true)
        private set
    val code: String get() = L10n.code

    init {
        val saved = prefs.getString(PREFERENCE_KEY, null)
        val initial = if (saved != null && saved in supported) {
            followsDevice = false
            saved
        } else {
            resolve(preferred.map { it.language }, supported, tenant.defaultLanguage)
        }
        L10n.set(initial)
    }

    fun select(newCode: String) {
        if (newCode !in supported) return
        prefs.edit().putString(PREFERENCE_KEY, newCode).apply()
        followsDevice = false
        L10n.set(newCode)
    }

    companion object {
        const val PREFERENCE_KEY = "app.language"

        fun resolve(preferred: List<String>, supported: List<String>, fallback: String): String =
            preferred.firstOrNull { it in supported } ?: fallback

        /** Name of a language written in that language (shown in the picker). */
        fun nativeName(code: String): String {
            val locale = Locale.forLanguageTag(code)
            return locale.getDisplayLanguage(locale).replaceFirstChar { it.titlecase(locale) }
        }
    }
}
