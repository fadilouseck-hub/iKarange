package sn.assurplus.app.features.card

import android.app.Activity
import android.view.WindowManager
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.FilterQuality
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.serializer
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.WalletResult
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Percent
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.CardBeneficiary
import sn.assurplus.app.core.model.MemberCard
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.QRToken
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.tenant.Tenant
import java.time.Duration
import java.time.Instant
import java.time.ZoneId

class CardViewModel(env: AppEnvironment) {
    private val api = env.api
    private val cache = env.cache
    private val wallet = env.wallet
    private val tokenSerializer = MapSerializer(String.serializer(), QRToken.serializer())

    val card = RemoteResource(env.cache, CacheKey.card, MemberCard.serializer()) { env.api.card() }
    var selectedId by mutableStateOf(card.value?.beneficiaries?.firstOrNull()?.id)

    /** Last token per beneficiary. Persisted so the QR can still be shown offline until it expires. */
    var tokens by mutableStateOf(env.cache.load(tokenSerializer, CacheKey.qrTokens) ?: emptyMap())
        private set
    var qrError by mutableStateOf<APIError?>(null)
        private set
    var isAddingToWallet by mutableStateOf(false)
        private set
    var walletMessage by mutableStateOf<Message?>(null)

    val beneficiaries: List<CardBeneficiary> get() = card.value?.beneficiaries.orEmpty()
    val selected: CardBeneficiary? get() = beneficiaries.firstOrNull { it.id == selectedId } ?: beneficiaries.firstOrNull()
    val canAddToWallet: Boolean get() = wallet.isAvailable

    fun currentToken(at: Instant = Instant.now()): QRToken? {
        val id = selected?.id ?: return null
        return tokens[id]?.takeIf { it.isValid(at, marginSeconds = 0) }
    }

    suspend fun load() {
        card.load()
        if (selectedId == null || beneficiaries.none { it.id == selectedId }) selectedId = beneficiaries.firstOrNull()?.id
    }

    /** Fetches a fresh token when there is none or it expires within `margin` seconds. */
    suspend fun refreshTokenIfNeeded(now: Instant = Instant.now(), marginSeconds: Long = 10) {
        val id = selected?.id ?: return
        if (tokens[id]?.isValid(now, marginSeconds) == true) return
        try {
            val token = api.qrToken(id)
            tokens = tokens + (id to token)
            cache.store(tokenSerializer, tokens, CacheKey.qrTokens)
            qrError = null
        } catch (e: Exception) {
            qrError = APIError.wrap(e)
        }
    }

    /** Keeps the QR fresh while the card is on screen; cancelled with the composition. */
    suspend fun runTokenRefreshLoop() {
        while (kotlin.coroutines.coroutineContext.isActive) {
            refreshTokenIfNeeded()
            val token = currentToken()
            val wait = if (token != null) maxOf(Duration.between(Instant.now(), token.expiresAt).seconds - 10, 1) else 5 // offline or failed: retry soon
            delay(minOf(wait, 30) * 1000)
        }
    }

    suspend fun addToWallet() {
        val id = selected?.id ?: return
        isAddingToWallet = true
        try {
            val link = api.googleWalletLink(id)
            walletMessage = when (wallet.add(link.saveUrl)) {
                WalletResult.added -> null
                WalletResult.simulated -> Message(Message.Level.success, t("Carte ajoutée à Google Wallet — démonstration."))
                WalletResult.cancelled -> null
            }
        } catch (e: Exception) {
            walletMessage = Message(Message.Level.error, APIError.wrap(e).userMessage)
        } finally {
            isAddingToWallet = false
        }
    }
}

@Composable
fun CardScreen() {
    val env = LocalEnv.current
    val model = remember { CardViewModel(env) }
    val scope = rememberCoroutineScope()
    LaunchedEffect(Unit) { model.load() }
    LaunchedEffect(model.selected?.id) { model.runTokenRefreshLoop() }
    ScreenBrightnessBoost()

    Screen(t("Carte tiers-payant"), largeTitle = false, onRefresh = { model.load() }) {
        LoadableContent(
            model.card,
            retry = { scope.launch { model.load() } },
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    SkeletonBlock(height = 200.dp)
                    SkeletonBlock(height = 260.dp)
                }
            },
        ) { card ->
            if (card.beneficiaries.isEmpty()) {
                EmptyStateView(
                    t("Pas encore de carte"),
                    t("Votre carte tiers-payant sera disponible dès l'activation de votre contrat."),
                    symbol = "creditcard",
                )
            } else {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.l)) {
                    if (card.beneficiaries.size > 1) Switcher(card.beneficiaries, model)
                    model.selected?.let { beneficiary ->
                        MemberCardView(beneficiary)
                        QRPanel(model)
                        WalletButton(model) { scope.launch { model.addToWallet() } }
                    }
                    Text(
                        t("Présentez ce QR code au prestataire de santé. Il se renouvelle automatiquement et ne contient aucune donnée personnelle lisible."),
                        style = DS.Typography.footnote, color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }
    }
}

@Composable
private fun Switcher(beneficiaries: List<CardBeneficiary>, model: CardViewModel) {
    Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        beneficiaries.forEach { beneficiary ->
            val isSelected = beneficiary.id == model.selected?.id
            Text(
                beneficiary.fullName.split(" ").firstOrNull() ?: beneficiary.fullName,
                style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold),
                color = if (isSelected) DS.Palette.onPrimary else DS.Palette.textPrimary,
                modifier = Modifier
                    .clip(CircleShape)
                    .background(if (isSelected) DS.Palette.primary else DS.Palette.surface)
                    .clickable(role = Role.Tab) { model.selectedId = beneficiary.id }
                    .padding(horizontal = DS.Spacing.m, vertical = DS.Spacing.s)
                    .semantics { contentDescription = "${beneficiary.fullName}, ${beneficiary.relationLabel}"; selected = isSelected }
                    .testTag("card.beneficiary.${beneficiary.id}"),
            )
        }
    }
}

@Composable
fun MemberCardView(beneficiary: CardBeneficiary, modifier: Modifier = Modifier) {
    val mint = DS.Palette.mint
    Column(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(DS.Radius.l))
            .background(Brush.linearGradient(listOf(DS.Palette.teal, DS.Palette.tealMid)))
            .drawBehind {
                // Decorative mint circle in the top-right corner (220 pt, offset 90 / -110 from the trailing edge).
                val radius = 110.dp.toPx()
                drawCircle(mint.copy(alpha = 0.18f), radius, Offset(size.width - radius + 90.dp.toPx(), radius - 110.dp.toPx()))
            }
            .padding(DS.Spacing.l)
            .semantics(mergeDescendants = true) {}
            .testTag("card.member"),
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(Tenant.current.wordmark, style = DS.Typography.headline.copy(fontWeight = FontWeight.Black), color = Color.White, modifier = Modifier.weight(1f))
            Text(beneficiary.insurerName, style = DS.Typography.caption.copy(fontWeight = FontWeight.SemiBold), color = Color.White.copy(alpha = 0.85f))
        }
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            Photo(beneficiary)
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
                Text(beneficiary.fullName, style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = Color.White, maxLines = 2)
                Text(beneficiary.relationLabel, style = DS.Typography.caption, color = Color.White.copy(alpha = 0.85f))
                beneficiary.birthDate?.let {
                    Text(t("Né(e) le %@", DateText.short(it.date.atStartOfDay(ZoneId.systemDefault()).toInstant())), style = DS.Typography.caption, color = Color.White.copy(alpha = 0.85f))
                }
            }
        }
        Row(verticalAlignment = Alignment.Bottom) {
            Field(t("N° assuré"), beneficiary.memberNumber)
            Spacer(Modifier.weight(1f))
            Field(t("Contrat"), beneficiary.policyNumber)
        }
        Row(verticalAlignment = Alignment.Bottom) {
            if (beneficiary.formulaName != beneficiary.policyNumber && beneficiary.formulaName.isNotEmpty()) {
                Field(t("Formule"), beneficiary.formulaName)
                Spacer(Modifier.weight(1f))
            }
            Field(t("Couverture"), Percent.format(beneficiary.coverageRate))
            Spacer(Modifier.weight(1f))
            Field(t("Valide jusqu'au"), DateText.short(beneficiary.validUntil.date.atStartOfDay(ZoneId.systemDefault()).toInstant()))
        }
        if (beneficiary.status.code != "active") {
            Text(
                beneficiary.status.label, style = DS.Typography.caption.copy(fontWeight = FontWeight.Bold), color = Color.White,
                modifier = Modifier.background(DS.Palette.danger, CircleShape).padding(horizontal = DS.Spacing.s, vertical = DS.Spacing.xs),
            )
        }
    }
}

@Composable
private fun Photo(beneficiary: CardBeneficiary) {
    val shape = RoundedCornerShape(DS.Radius.m)
    if (beneficiary.photoURL != null) {
        AsyncImage(
            model = beneficiary.photoURL, contentDescription = null, contentScale = ContentScale.Crop,
            modifier = Modifier.size(64.dp).clip(shape),
        )
    } else {
        Box(Modifier.size(64.dp).background(Color.White.copy(alpha = 0.18f), shape), contentAlignment = Alignment.Center) {
            Text(initials(beneficiary.fullName), style = DS.Typography.title2.copy(fontWeight = FontWeight.Bold), color = Color.White)
        }
    }
}

@Composable
private fun Field(label: String, value: String) {
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
        Text(label, style = DS.Typography.caption2, color = Color.White.copy(alpha = 0.75f))
        Text(value, style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum"), color = Color.White)
    }
}

@Composable
private fun QRPanel(model: CardViewModel) {
    // Ticks every second for the countdown (iOS TimelineView).
    var now by remember { mutableStateOf(Instant.now()) }
    LaunchedEffect(Unit) { while (true) { now = Instant.now(); delay(1000) } }
    val token = model.currentToken(now)

    Column(Modifier.card(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Box(
            Modifier.background(Color.White, RoundedCornerShape(DS.Radius.l)).padding(DS.Spacing.m).size(240.dp),
            contentAlignment = Alignment.Center,
        ) {
            val image = token?.let { StyledQRCache.image(it.token) }
            if (token != null && image != null) {
                Image(
                    image.asImageBitmap(), null, filterQuality = FilterQuality.High, contentScale = ContentScale.Fit,
                    modifier = Modifier.fillMaxSize()
                        .semantics { contentDescription = t("QR code de la carte tiers-payant"); stateDescription = token.token }
                        .testTag("card.qr"),
                )
            } else {
                Box(Modifier.fillMaxSize().background(DS.Palette.surfaceMuted, RoundedCornerShape(DS.Radius.m)))
                Column(Modifier.padding(DS.Spacing.l), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
                    if (model.qrError == null) {
                        CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(22.dp))
                        Text(t("Génération du QR code…"), style = DS.Typography.footnote, color = DS.Palette.textSecondary)
                    } else {
                        Icon(sym("wifi.slash"), null, tint = DS.Palette.textSecondary, modifier = Modifier.size(28.dp))
                        Text(t("QR code indisponible hors ligne. Reconnectez-vous pour le renouveler."), style = DS.Typography.footnote, color = DS.Palette.textSecondary, textAlign = TextAlign.Center)
                    }
                }
            }
        }
        if (token != null) {
            val seconds = maxOf(Duration.between(now, token.expiresAt).seconds, 0)
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs), modifier = Modifier.testTag("card.qrCountdown")) {
                Icon(sym("arrow.triangle.2.circlepath"), null, tint = DS.Palette.textSecondary, modifier = Modifier.size(16.dp))
                Text(t("Renouvellement dans %lld s", seconds), style = DS.Typography.caption.copy(fontFeatureSettings = "tnum"), color = DS.Palette.textSecondary)
            }
        }
    }
}

/** "Add to Google Wallet" button (black, like Google's official asset), and the result message. */
@Composable
private fun WalletButton(model: CardViewModel, onClick: () -> Unit) {
    if (!model.canAddToWallet) return
    Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Row(
            Modifier
                .fillMaxWidth()
                .height(48.dp)
                .alpha(if (model.isAddingToWallet) 0.5f else 1f)
                .clip(RoundedCornerShape(DS.Radius.s))
                .background(Color.Black)
                .clickable(enabled = !model.isAddingToWallet, role = Role.Button, onClick = onClick)
                .testTag("card.addToWallet"),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            WalletGlyph()
            Spacer(Modifier.width(DS.Spacing.s))
            Text(t("Ajouter à Google Wallet"), color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.Medium)
        }
        model.walletMessage?.let { MessageBanner(it, Modifier.testTag("card.walletMessage")) }
    }
}

/** Stacked coloured cards (Google Wallet mark style). */
@Composable
private fun WalletGlyph() {
    val colors = listOf(Color(0xFF4285F4), Color(0xFF34A853), Color(0xFFFBBC04), Color(0xFFEA4335))
    Box(Modifier.size(width = 26.dp, height = 20.dp)) {
        colors.forEachIndexed { index, color ->
            Box(Modifier.padding(top = (index * 3).dp).fillMaxWidth().height(10.dp).background(color, RoundedCornerShape(3.dp)))
        }
    }
}

/** Raises screen brightness while the card is shown so scanners read the QR, then restores it. */
@Composable
private fun ScreenBrightnessBoost() {
    val activity = LocalContext.current as? Activity ?: return
    DisposableEffect(activity) {
        val window = activity.window
        val previous = window.attributes.screenBrightness
        window.attributes = window.attributes.apply { screenBrightness = maxOf(previous, 0.9f) }
        onDispose { window.attributes = window.attributes.apply { screenBrightness = previous.takeIf { it >= 0 } ?: WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_NONE } }
    }
}
