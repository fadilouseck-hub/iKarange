package sn.assurplus.app.app

import android.app.Application
import android.content.Intent
import android.graphics.Color as AndroidColor
import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.key
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.model.DeepLinkTarget
import sn.assurplus.app.core.persist.ProtectedStorage
import sn.assurplus.app.core.security.DeviceBiometrics
import sn.assurplus.app.designsystem.DS
import sn.assurplus.app.designsystem.LocalIsDark
import sn.assurplus.app.tenant.Tenant
import java.time.Instant

class AssurPlusApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        Tenant.load(this)
        L10n.loadTables(this)
        ProtectedStorage.init(this)
    }
}

class MainActivity : FragmentActivity() {
    private lateinit var env: AppEnvironment
    private var backgroundedAt: Instant? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        env = AppEnvironment.obtain(this, intent.extras)
        (env.biometrics as? DeviceBiometrics)?.activity = this
        CurrentActivity.activity = this
        hideContentInRecents()
        handle(intent)

        // Lock after 60 s in the background (iOS: scene phase).
        lifecycle.addObserver(LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_STOP -> backgroundedAt = Instant.now()
                Lifecycle.Event.ON_START -> {
                    backgroundedAt?.let { if (Instant.now().epochSecond - it.epochSecond > 60) env.session.lock() }
                    backgroundedAt = null
                }
                else -> Unit
            }
        })

        setContent {
            AppTheme(env) {
                // Re-render every string when the user switches language in Profile.
                key(L10n.code) { RootView() }
            }
        }
    }

    override fun onResume() {
        super.onResume()
        CurrentActivity.activity = this
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handle(intent)
    }

    /** `assurplus://…` links and notification taps (`target_kind` / `target_id` extras, never personal data). */
    private fun handle(intent: Intent?) {
        intent ?: return
        intent.data?.let { env.router.open(it) }
        val kind = intent.getStringExtra("target_kind")?.let { raw -> DeepLinkTarget.Kind.entries.firstOrNull { it.name == raw } }
        if (kind != null) env.router.open(DeepLinkTarget(kind, intent.getStringExtra("target_id")))
    }

    /** Health data must not show in the app switcher (iOS privacy shield). */
    private fun hideContentInRecents() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            setRecentsScreenshotEnabled(false)
        } else if (!env.isMock) {
            window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
        }
    }

    @Composable
    private fun AppTheme(env: AppEnvironment, content: @Composable () -> Unit) {
        val dark = when (env.settings.appearance) {
            Appearance.system -> isSystemInDarkTheme()
            Appearance.light -> false
            Appearance.dark -> true
        }
        LaunchedEffect(dark) {
            val style = if (dark) SystemBarStyle.dark(AndroidColor.TRANSPARENT) else SystemBarStyle.light(AndroidColor.TRANSPARENT, AndroidColor.TRANSPARENT)
            enableEdgeToEdge(statusBarStyle = style, navigationBarStyle = style)
        }
        CompositionLocalProvider(LocalIsDark provides dark, LocalEnv provides env) {
            val p = DS.Palette
            val scheme = if (dark) darkColorScheme(
                primary = p.primary, onPrimary = p.onPrimary, secondary = p.accent, background = p.background,
                surface = p.surface, onSurface = p.textPrimary, onBackground = p.textPrimary, error = p.danger,
                surfaceContainerHigh = p.surface, surfaceContainer = p.surface, onSurfaceVariant = p.textSecondary,
            ) else lightColorScheme(
                primary = p.primary, onPrimary = p.onPrimary, secondary = p.accent, background = p.background,
                surface = p.surface, onSurface = p.textPrimary, onBackground = p.textPrimary, error = p.danger,
                surfaceContainerHigh = p.surface, surfaceContainer = p.surface, onSurfaceVariant = p.textSecondary,
            )
            MaterialTheme(colorScheme = scheme, content = content)
        }
    }
}
