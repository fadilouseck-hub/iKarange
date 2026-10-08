package sn.assurplus.app.app

import android.content.SharedPreferences
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshots.SnapshotStateList
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.AuthResponse
import sn.assurplus.app.core.model.DeepLinkTarget
import sn.assurplus.app.core.model.Me
import sn.assurplus.app.core.model.Permission
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.network.AssurApi
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.ProtectedStorage
import sn.assurplus.app.core.persist.ResponseCache
import sn.assurplus.app.core.security.TokenStore

/** Local, non-sensitive preferences. */
class AppSettings(private val prefs: SharedPreferences) {
    var biometricLockEnabled by mutableStateOf(prefs.getBoolean("biometricLockEnabled", false))
        private set
    var appearance by mutableStateOf(Appearance.entries.firstOrNull { it.name == prefs.getString("appearance", null) } ?: Appearance.system)
        private set
    var hasSeenWelcome by mutableStateOf(prefs.getBoolean("hasSeenWelcome", false))
        private set

    fun updateBiometricLock(enabled: Boolean) {
        biometricLockEnabled = enabled
        prefs.edit().putBoolean("biometricLockEnabled", enabled).apply()
    }

    fun updateAppearance(value: Appearance) {
        appearance = value
        prefs.edit().putString("appearance", value.name).apply()
    }

    fun markWelcomeSeen() {
        hasSeenWelcome = true
        prefs.edit().putBoolean("hasSeenWelcome", true).apply()
    }
}

/** Light / dark / follow the system. */
enum class Appearance {
    system, light, dark;

    val title: String
        get() = when (this) {
            system -> t("Automatique")
            light -> t("Clair")
            dark -> t("Sombre")
        }
}

/** Who is logged in and what they may do. Rights come from the server (`permissions` in `/me`). */
class AuthSession(
    private val api: AssurApi,
    private val tokens: TokenStore,
    private val cache: ResponseCache,
    private val settings: AppSettings,
) {
    enum class State { launching, signedOut, locked, signedIn }

    var state by mutableStateOf(State.launching)
        private set
    var user by mutableStateOf<Me?>(null)
        private set

    init {
        api.client.onSessionExpired = { endSession() }
    }

    val permissions: Set<Permission> get() = user?.grantedPermissions ?: emptySet()
    fun can(permission: Permission): Boolean = permission in permissions

    /** Called at launch: restores the session from the token store (cached profile when offline). */
    suspend fun bootstrap() {
        if (tokens.load() == null) {
            state = State.signedOut
            return
        }
        user = cache.load(Me.serializer(), CacheKey.me)
        state = if (settings.biometricLockEnabled) State.locked else State.signedIn
        refreshUser()
    }

    suspend fun refreshUser() {
        try {
            val me = api.me()
            user = me
            cache.store(Me.serializer(), me, CacheKey.me)
            if (state == State.launching) state = State.signedIn
        } catch (e: APIError.Unauthorized) {
            endSession()
        } catch (e: Exception) {
            if (user == null && state != State.signedOut) {
                // Offline on first launch with no cached profile: still let the user in to cached data.
                state = if (settings.biometricLockEnabled) State.locked else State.signedIn
            }
        }
    }

    fun didAuthenticate(response: AuthResponse) {
        tokens.save(response.tokens)
        user = response.user
        cache.store(Me.serializer(), response.user, CacheKey.me)
        state = State.signedIn
    }

    fun updateUser(me: Me) {
        user = me
        cache.store(Me.serializer(), me, CacheKey.me)
    }

    fun lock() {
        if (state == State.signedIn && settings.biometricLockEnabled) state = State.locked
    }

    fun unlock() {
        if (state == State.locked) state = State.signedIn
    }

    suspend fun logout() {
        tokens.load()?.refreshToken?.let { runCatching { api.logout(it) } }
        endSession()
    }

    /** Clears tokens, caches and protected files. */
    fun endSession() {
        tokens.clear()
        cache.clear()
        ProtectedStorage.wipe()
        settings.updateBiometricLock(false)
        user = null
        state = State.signedOut
    }
}

enum class AppTab {
    home, card, claims, network, profile;

    val title: String
        get() = when (this) {
            home -> t("Accueil")
            card -> t("Carte")
            claims -> t("Sinistres")
            network -> t("Réseau")
            profile -> t("Profil")
        }

    val symbol: String
        get() = when (this) {
            home -> "house.fill"
            card -> "qrcode"
            claims -> "doc.text.magnifyingglass"
            network -> "map.fill"
            profile -> "person.crop.circle.fill"
        }
}

/** Destinations pushed on a tab's navigation stack. */
sealed class Route {
    data object Policy : Route()
    data object Family : Route()
    data object Payments : Route()
    data object Vault : Route()
    data object Notifications : Route()
    data class Claim(val id: String) : Route()
    data class Payment(val id: String) : Route()
}

enum class Sheet { subscription, newClaim }

/** Owns tab selection and navigation stacks so push notifications and URLs can open any screen. */
class Router {
    var selectedTab by mutableStateOf(AppTab.home)
    val homePath: SnapshotStateList<Route> = mutableStateListOf()
    val claimsPath: SnapshotStateList<Route> = mutableStateListOf()
    val profilePath: SnapshotStateList<Route> = mutableStateListOf()
    var presentedSheet by mutableStateOf<Sheet?>(null)

    /** The stack of the selected tab (card and network have none). */
    fun path(tab: AppTab = selectedTab): SnapshotStateList<Route>? = when (tab) {
        AppTab.home -> homePath
        AppTab.claims -> claimsPath
        AppTab.profile -> profilePath
        else -> null
    }

    /** Pushes on the selected tab's stack. */
    fun push(route: Route) {
        path()?.add(route)
    }

    fun pop() {
        path()?.removeLastOrNull()
    }

    fun open(target: DeepLinkTarget) {
        presentedSheet = null
        when (target.kind) {
            DeepLinkTarget.Kind.claim -> {
                selectedTab = AppTab.claims
                claimsPath.clear()
                target.id?.let { claimsPath.add(Route.Claim(it)) }
            }
            DeepLinkTarget.Kind.payment -> {
                selectedTab = AppTab.home
                homePath.clear()
                homePath.add(Route.Payments)
                target.id?.let { homePath.add(Route.Payment(it)) }
            }
            DeepLinkTarget.Kind.policy -> openOnHome(Route.Policy)
            DeepLinkTarget.Kind.dependents -> openOnHome(Route.Family)
            DeepLinkTarget.Kind.card -> selectedTab = AppTab.card
            DeepLinkTarget.Kind.vault -> openOnHome(Route.Vault)
            DeepLinkTarget.Kind.notifications -> openOnHome(Route.Notifications)
            DeepLinkTarget.Kind.subscription -> presentedSheet = Sheet.subscription
        }
    }

    private fun openOnHome(route: Route) {
        selectedTab = AppTab.home
        homePath.clear()
        homePath.add(route)
    }

    /** `assurplus://claims/<id>`, `assurplus://card`, … Payment return URLs are handled by the payment flow. */
    fun open(url: android.net.Uri) {
        if (url.scheme != "assurplus") return
        // Payment provider return URL: the payment flow is already polling the server, nothing to open.
        if (url.host == "payments" && url.pathSegments.firstOrNull() == "return") return
        val id = url.pathSegments.firstOrNull()
        val kind = when (url.host) {
            "claims" -> DeepLinkTarget.Kind.claim
            "payments" -> DeepLinkTarget.Kind.payment
            "policy" -> DeepLinkTarget.Kind.policy
            "family" -> DeepLinkTarget.Kind.dependents
            "card" -> DeepLinkTarget.Kind.card
            "vault" -> DeepLinkTarget.Kind.vault
            "notifications" -> DeepLinkTarget.Kind.notifications
            "subscribe" -> DeepLinkTarget.Kind.subscription
            else -> null
        } ?: return
        open(DeepLinkTarget(kind, id))
    }

    fun reset() {
        selectedTab = AppTab.home
        homePath.clear()
        claimsPath.clear()
        profilePath.clear()
        presentedSheet = null
    }
}
