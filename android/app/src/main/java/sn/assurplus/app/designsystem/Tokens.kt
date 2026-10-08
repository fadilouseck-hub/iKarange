package sn.assurplus.app.designsystem

import androidx.compose.runtime.Composable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import sn.assurplus.app.R
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.LineHeightStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.StatusTone
import sn.assurplus.app.tenant.Tenant

/** True when the dark palette is active (system setting or Profil › Apparence). */
val LocalIsDark = staticCompositionLocalOf { false }

/** Design tokens — same values as the iOS `DS`. Brand colours come from the tenant; neutral and status colours are shared. */
object DS {
    object Palette {
        private val brand get() = Tenant.current.colors
        private fun hex(value: Long) = Color(0xFF000000 or value)

        /** Bright brand colour (mint for Assur Plus) and deep brand colour (teal). */
        val mint: Color get() = hex(Tenant.hex(brand.brandAccent))
        val teal: Color get() = hex(Tenant.hex(brand.brandDark))
        val tealMid: Color get() = hex(Tenant.hex(brand.brandDarkSecondary))

        /** Primary action colour: deep brand colour on light, bright brand colour on dark. */
        val primary: Color @Composable @ReadOnlyComposable get() = if (LocalIsDark.current) mint else teal
        val onPrimary: Color @Composable @ReadOnlyComposable get() = dynamic(0xFFFFFF, 0x0E2222)
        val accent: Color @Composable @ReadOnlyComposable get() = if (LocalIsDark.current) mint else hex(Tenant.hex(brand.accentOnLight))
        val accentSoft: Color @Composable @ReadOnlyComposable get() = dynamic(0xE3F8F2, 0x163A33)

        val background: Color @Composable @ReadOnlyComposable get() = dynamic(0xF5F5F9, 0x0B1414)
        val surface: Color @Composable @ReadOnlyComposable get() = dynamic(0xFFFFFF, 0x152222)
        val surfaceMuted: Color @Composable @ReadOnlyComposable get() = dynamic(0xEEF1F3, 0x1D2D2D)
        val border: Color @Composable @ReadOnlyComposable get() = dynamic(0xDDE3E6, 0x2A3C3C)

        val textPrimary: Color @Composable @ReadOnlyComposable get() = dynamic(0x0F1F1F, 0xF1F6F5)
        val textSecondary: Color @Composable @ReadOnlyComposable get() = dynamic(0x4B5C5C, 0xA9BDBB)

        val success: Color @Composable @ReadOnlyComposable get() = dynamic(0x157F5B, 0x4ADE9F)
        val successSoft: Color @Composable @ReadOnlyComposable get() = dynamic(0xDDF5EA, 0x173A2B)
        val warning: Color @Composable @ReadOnlyComposable get() = dynamic(0x9A5B00, 0xF5B94A)
        val warningSoft: Color @Composable @ReadOnlyComposable get() = dynamic(0xFFF1D6, 0x3D2E12)
        val danger: Color @Composable @ReadOnlyComposable get() = dynamic(0xB3261E, 0xFF8A80)
        val dangerSoft: Color @Composable @ReadOnlyComposable get() = dynamic(0xFCE4E2, 0x3F1C1A)
        val info: Color @Composable @ReadOnlyComposable get() = dynamic(0x1F5FA8, 0x8AB8F0)
        val infoSoft: Color @Composable @ReadOnlyComposable get() = dynamic(0xE2EDFA, 0x1A2A3F)

        /** iOS `systemRed`: destructive-role buttons (Supprimer, Se déconnecter, Demander le retrait…). */
        val destructive: Color @Composable @ReadOnlyComposable get() = dynamic(0xFF3B30, 0xFF453A)

        /** iOS system separator (hairlines in lists and cards). */
        val separator: Color @Composable @ReadOnlyComposable get() = if (LocalIsDark.current) Color(0x5C545458) else Color(0x4A3C3C43)

        @Composable @ReadOnlyComposable
        fun dynamic(light: Long, dark: Long): Color = hex(if (LocalIsDark.current) dark else light)
    }

    object Spacing {
        val xxs = 2.dp
        val xs = 4.dp
        val s = 8.dp
        val m = 12.dp
        val l = 16.dp
        val xl = 24.dp
        val xxl = 32.dp
    }

    object Radius {
        val s = 8.dp
        val m = 12.dp
        val l = 20.dp
        val pill = 999.dp
    }

    /**
     * iOS Dynamic Type text styles at the default size (Large), so screens have the same rhythm as on iPhone. Sizes
     * scale with the user's font setting through `sp`.
     */
    object Typography {
        /** Inter: the closest open font to Apple's SF Pro in widths and rhythm, so text wraps like on iOS (OFL). */
        val family = FontFamily(
            Font(R.font.inter_regular, FontWeight.Normal),
            Font(R.font.inter_medium, FontWeight.Medium),
            Font(R.font.inter_semibold, FontWeight.SemiBold),
            Font(R.font.inter_bold, FontWeight.Bold),
            Font(R.font.inter_black, FontWeight.Black),
            Font(R.font.inter_black, FontWeight.ExtraBold),
        )
        private val trim = LineHeightStyle(LineHeightStyle.Alignment.Center, LineHeightStyle.Trim.None)
        private fun style(size: Int, line: Int, weight: FontWeight = FontWeight.Normal, tracking: Double = 0.0) = TextStyle(
            fontFamily = family, fontSize = size.sp, lineHeight = line.sp, fontWeight = weight,
            letterSpacing = tracking.em, lineHeightStyle = trim,
        )

        val largeTitle = style(34, 41, FontWeight.Bold, -0.012)
        val title1 = style(28, 34, FontWeight.Normal, -0.01)
        val title2 = style(22, 28, FontWeight.Normal, -0.008)
        val title3 = style(20, 25, FontWeight.Normal, -0.006)
        val headline = style(17, 22, FontWeight.SemiBold, -0.004)
        val body = style(17, 22, FontWeight.Normal, -0.004)
        val callout = style(16, 21, FontWeight.Normal, -0.002)
        val subheadline = style(15, 20)
        val footnote = style(13, 18)
        val caption = style(12, 16)
        val caption2 = style(11, 13)

        /** Same names as the iOS `DS.Typography` tokens. */
        val display = largeTitle
        val title = title2.copy(fontWeight = FontWeight.Bold)
        val amount = title1.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum")
        val amountSmall = headline.copy(fontFeatureSettings = "tnum")
    }
}

val StatusTone.foreground: Color
    @Composable @ReadOnlyComposable get() = when (this) {
        StatusTone.success -> DS.Palette.success
        StatusTone.warning -> DS.Palette.warning
        StatusTone.danger -> DS.Palette.danger
        StatusTone.info -> DS.Palette.info
        StatusTone.neutral -> DS.Palette.textSecondary
    }

val StatusTone.background: Color
    @Composable @ReadOnlyComposable get() = when (this) {
        StatusTone.success -> DS.Palette.successSoft
        StatusTone.warning -> DS.Palette.warningSoft
        StatusTone.danger -> DS.Palette.dangerSoft
        StatusTone.info -> DS.Palette.infoSoft
        StatusTone.neutral -> DS.Palette.surfaceMuted
    }

val Message.Level.tone: StatusTone
    get() = when (this) {
        Message.Level.info -> StatusTone.info
        Message.Level.warning -> StatusTone.warning
        Message.Level.error -> StatusTone.danger
        Message.Level.success -> StatusTone.success
    }
