package sn.assurplus.app.features.network

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.drawable.BitmapDrawable
import android.location.Location
import android.location.LocationManager
import android.net.Uri
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.graphics.drawscope.CanvasDrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.graphics.vector.VectorPainter
import androidx.compose.ui.graphics.vector.rememberVectorPainter
import androidx.compose.ui.layout.layout
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.Constraints
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import org.osmdroid.config.Configuration
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.CustomZoomButtonsController
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.Documents
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.l10n.L10n
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Provider
import sn.assurplus.app.core.model.ProviderType
import sn.assurplus.app.core.model.ProvidersResponse
import sn.assurplus.app.core.model.StatusTone
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.designsystem.*
import java.io.File
import kotlin.coroutines.resume
import kotlin.math.roundToInt

/** A latitude / longitude pair (iOS `CLLocationCoordinate2D`). */
private data class Coordinate(val latitude: Double, val longitude: Double)

private val Provider.coordinate: Coordinate?
    get() = if (latitude != null && longitude != null) Coordinate(latitude, longitude) else null

/** One-shot location from the platform `LocationManager` (no Google Play services), requested only when needed. */
private object LocationProvider {
    fun hasPermission(context: Context): Boolean =
        listOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)
            .any { ContextCompat.checkSelfPermission(context, it) == PackageManager.PERMISSION_GRANTED }

    @SuppressLint("MissingPermission")
    suspend fun currentLocation(context: Context): Coordinate? {
        if (!hasPermission(context)) return null
        val manager = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return null
        val fine = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val candidates = buildList {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) add(LocationManager.FUSED_PROVIDER)
            add(LocationManager.NETWORK_PROVIDER)
            if (fine) add(LocationManager.GPS_PROVIDER)
        }.filter { runCatching { manager.isProviderEnabled(it) }.getOrDefault(false) }
        val provider = candidates.firstOrNull() ?: return null

        val fresh = runCatching {
            withTimeoutOrNull(15_000) {
                suspendCancellableCoroutine<Location?> { continuation ->
                    val signal = android.os.CancellationSignal()
                    continuation.invokeOnCancellation { signal.cancel() }
                    LocationManagerCompat.getCurrentLocation(manager, provider, signal, ContextCompat.getMainExecutor(context)) { location ->
                        if (continuation.isActive) continuation.resume(location)
                    }
                }
            }
        }.getOrNull()
        val location = fresh ?: candidates
            .mapNotNull { runCatching { manager.getLastKnownLocation(it) }.getOrNull() }
            .maxByOrNull { it.time }
        return location?.let { Coordinate(it.latitude, it.longitude) }
    }
}

private enum class Mode {
    map, list;

    val label: String
        get() = if (this == map) {
            // Same key as iOS ("Carte" would translate to the insurance card).
            t("network.mode.map").let { if (it == "network.mode.map") "Carte" else it }
        } else t("Liste")
}

private class NetworkModel(private val env: AppEnvironment) {
    var mode by mutableStateOf(Mode.list)
    var type by mutableStateOf<ProviderType?>(null)
    var radiusKm by mutableStateOf<Int?>(null)
    var search by mutableStateOf("")
    var userLocation by mutableStateOf<Coordinate?>(null)
        private set
    var providers by mutableStateOf(env.cache.load(ProvidersResponse.serializer(), CacheKey.providers)?.providers.orEmpty())
        private set
    var isLoading by mutableStateOf(false)
        private set
    var isLocating by mutableStateOf(false)
        private set
    var locationDenied by mutableStateOf(false)
    var isDownloadingPDF by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)
    var selected by mutableStateOf<Provider?>(null)

    /** The map and distance search only make sense when the backend provides coordinates. */
    val hasCoordinates: Boolean get() = providers.any { it.coordinate != null }

    suspend fun load() {
        isLoading = true
        try {
            val location = userLocation
            val response = env.api.providers(
                type, location?.latitude, location?.longitude,
                if (location == null) null else radiusKm, search.ifEmpty { null },
            )
            providers = response.providers
            if (type == null && search.isEmpty() && radiusKm == null) env.cache.store(ProvidersResponse.serializer(), response, CacheKey.providers)
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            val apiError = APIError.wrap(e)
            if (apiError != APIError.Cancelled) error = apiError
        } finally {
            isLoading = false
        }
    }

    /** Called once the permission is granted. Setting the default radius reloads through the screen's effect. */
    suspend fun locateMe(context: Context) {
        isLocating = true
        try {
            val coordinate = LocationProvider.currentLocation(context)
            if (coordinate != null) {
                userLocation = coordinate
                locationDenied = false
                if (radiusKm == null) radiusKm = 10 else load()
            } else {
                locationDenied = !LocationProvider.hasPermission(context)
            }
        } finally {
            isLocating = false
        }
    }

    suspend fun downloadPDF(context: Context) {
        isDownloadingPDF = true
        try {
            val data = env.api.networkPDF(type)
            val file = Documents.save(data, "reseau-de-soins${type?.let { "-${it.raw}" } ?: ""}.pdf")
            Documents.open(context, file)
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isDownloadingPDF = false
        }
    }

    companion object {
        val dakar = Coordinate(14.7167, -17.4677)
        val radiusOptions: List<Int?> = listOf(null, 2, 5, 10, 25)
    }
}

@Composable
fun NetworkScreen() {
    val env = LocalEnv.current
    val context = LocalContext.current
    val model = remember { NetworkModel(env) }
    val scope = rememberCoroutineScope()
    val bottomInset = LocalBottomInset.current

    // Initial load, then reload whenever the type or the distance changes (iOS `.task` + `.onChange`).
    LaunchedEffect(model.type, model.radiusKm) { model.load() }

    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { result ->
        if (result.values.any { it }) scope.launch { model.locateMe(context) } else model.locationDenied = true
    }
    val locate: () -> Unit = {
        if (LocationProvider.hasPermission(context)) scope.launch { model.locateMe(context) }
        else permissionLauncher.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
    }

    CompositionLocalProvider(LocalBottomInset provides 0.dp) {
        Screen(
            title = if (model.hasCoordinates) "" else t("Réseau de soins"),
            largeTitle = false,
            scrollable = false,
            contentPadding = PaddingValues(0.dp),
            spacing = 0.dp,
            leading = {
                if (model.hasCoordinates) {
                    Box(
                        Modifier
                            .width(150.dp)
                            .height(44.dp)
                            .shadow(10.dp, CircleShape, ambientColor = Color.Black.copy(alpha = 0.18f), spotColor = Color.Black.copy(alpha = 0.18f))
                            .background(DS.Palette.surface, CircleShape)
                            .padding(6.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        SegmentedPicker(Mode.entries, model.mode, { it.label }, { model.mode = it }, Modifier.clip(CircleShape))
                    }
                    Spacer(Modifier.width(DS.Spacing.m))
                    Text(t("Réseau de soins"), style = DS.Typography.headline, color = DS.Palette.textPrimary, maxLines = 1)
                }
            },
            actions = {
                if (model.isDownloadingPDF) {
                    Box(Modifier.size(48.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
                    }
                } else {
                    GlassButton("arrow.down.doc", { scope.launch { model.downloadPDF(context) } }, description = t("Télécharger en PDF"), tag = "network.pdf")
                }
            },
        ) {
            SearchField(model.search, { model.search = it }, t("Nom, ville, spécialité")) { scope.launch { model.load() } }
            Filters(model, locate)
            model.error?.let { error ->
                StaleDataBanner(error, { scope.launch { model.load() } }, Modifier.padding(horizontal = DS.Spacing.l))
            }
            Box(Modifier.weight(1f).fillMaxWidth().extendBottom(DS.Spacing.l)) {
                if (model.mode == Mode.map && model.hasCoordinates) {
                    ProvidersMap(model)
                } else {
                    ProvidersList(model, bottomInset) { model.load() }
                }
            }
        }
    }

    model.selected?.let { provider ->
        ModalBottomSheet(
            onDismissRequest = { model.selected = null },
            sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
            containerColor = DS.Palette.surface,
        ) {
            ProviderDetail(provider)
        }
    }
}

/** Lets the map / list run under the screen's bottom spacing, like iOS content under the tab bar. */
private fun Modifier.extendBottom(extra: androidx.compose.ui.unit.Dp): Modifier = layout { measurable, constraints ->
    val px = extra.roundToPx()
    val height = if (constraints.hasBoundedHeight) constraints.maxHeight + px else Constraints.Infinity
    val placeable = measurable.measure(
        if (constraints.hasBoundedHeight) constraints.copy(minHeight = height, maxHeight = height) else constraints
    )
    layout(placeable.width, (placeable.height - px).coerceAtLeast(0)) { placeable.place(0, 0) }
}

@Composable
private fun SearchField(value: String, onValueChange: (String) -> Unit, placeholder: String, onSubmit: () -> Unit) {
    val focus = LocalFocusManager.current
    Row(
        Modifier
            .padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.xs)
            .fillMaxWidth()
            .height(48.dp)
            .shadow(8.dp, CircleShape, ambientColor = Color.Black.copy(alpha = 0.12f), spotColor = Color.Black.copy(alpha = 0.12f))
            .background(DS.Palette.surface, CircleShape)
            .padding(horizontal = DS.Spacing.m),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(sym("magnifyingglass"), null, tint = DS.Palette.textPrimary, modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(DS.Spacing.s))
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
            cursorBrush = SolidColor(DS.Palette.accent),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            keyboardActions = KeyboardActions(onSearch = { focus.clearFocus(); onSubmit() }),
            modifier = Modifier.weight(1f).testTag("network.search"),
            decorationBox = { inner ->
                Box {
                    if (value.isEmpty()) Text(placeholder, style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.7f), maxLines = 1)
                    inner()
                }
            },
        )
        if (value.isNotEmpty()) {
            Icon(
                sym("xmark.circle.fill"), t("Effacer"), tint = DS.Palette.textSecondary.copy(alpha = 0.6f),
                modifier = Modifier.size(20.dp).clip(CircleShape).clickable(role = Role.Button) { onValueChange("") },
            )
        }
    }
}

@Composable
private fun Filters(model: NetworkModel, locate: () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(vertical = DS.Spacing.s), verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Row(
            Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = DS.Spacing.l),
            horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
        ) {
            TypeChip(model.type == null, t("Tous"), "square.grid.2x2", null) { model.type = null }
            ProviderType.entries.filter { it != ProviderType.other }.forEach { type ->
                TypeChip(model.type == type, type.label, type.symbol, "network.type.${type.raw}") { model.type = type }
            }
        }
        if (model.hasCoordinates) {
            Row(Modifier.fillMaxWidth().padding(horizontal = DS.Spacing.l), verticalAlignment = Alignment.CenterVertically) {
                if (model.isLocating) {
                    CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.padding(vertical = DS.Spacing.xs).size(20.dp))
                } else {
                    TextLink(
                        if (model.userLocation == null) t("Autour de moi") else t("Position mise à jour"), locate,
                        symbol = "location", tag = "network.locate",
                    )
                }
                Spacer(Modifier.weight(1f))
                if (model.userLocation != null) RadiusMenu(model)
            }
        }
        if (model.locationDenied) {
            Text(
                t("Localisation refusée. Activez-la dans Réglages pour trier par distance."),
                style = DS.Typography.caption, color = DS.Palette.textSecondary, modifier = Modifier.padding(horizontal = DS.Spacing.l),
            )
        }
    }
}

@Composable
private fun TypeChip(selected: Boolean, label: String, symbol: String, tag: String?, onClick: () -> Unit) {
    Box(Modifier.semantics { this.selected = selected }) { FilterCapsule(label, symbol, selected, onClick, tag) }
}

private fun radiusLabel(radius: Int?): String = radius?.let { "$it km" } ?: t("Toute distance")

@Composable
private fun RadiusMenu(model: NetworkModel) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        Row(
            Modifier.clip(CircleShape).clickable(role = Role.Button) { expanded = true }.padding(vertical = DS.Spacing.xs, horizontal = DS.Spacing.xs),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs),
        ) {
            Text(radiusLabel(model.radiusKm), style = DS.Typography.body, color = DS.Palette.accent)
            Icon(sym("chevron.up.chevron.down"), null, tint = DS.Palette.accent, modifier = Modifier.size(16.dp))
        }
        DropdownMenu(expanded, onDismissRequest = { expanded = false }, containerColor = DS.Palette.surface) {
            NetworkModel.radiusOptions.forEach { radius ->
                DropdownMenuItem(
                    text = { Text(radiusLabel(radius), style = DS.Typography.body, color = DS.Palette.textPrimary) },
                    trailingIcon = if (radius == model.radiusKm) ({ Icon(sym("checkmark"), null, tint = DS.Palette.accent) }) else null,
                    onClick = { expanded = false; model.radiusKm = radius },
                )
            }
        }
    }
}

@Composable
private fun ProvidersList(model: NetworkModel, bottomInset: androidx.compose.ui.unit.Dp, refresh: suspend () -> Unit) {
    val scope = rememberCoroutineScope()
    var refreshing by remember { mutableStateOf(false) }
    PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = { scope.launch { refreshing = true; refresh(); refreshing = false } },
        modifier = Modifier.fillMaxSize(),
    ) {
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = DS.Spacing.l, end = DS.Spacing.l, top = DS.Spacing.l, bottom = DS.Spacing.l + bottomInset),
            verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
        ) {
            if (model.providers.isEmpty()) {
                if (model.isLoading) {
                    items(4) { SkeletonCard(lines = 2) }
                } else {
                    item {
                        EmptyStateView(t("Aucun prestataire"), t("Modifiez les filtres ou élargissez la distance."), symbol = "mappin.slash")
                    }
                }
            }
            items(model.providers, key = { it.id }) { provider ->
                ProviderRow(provider, Modifier.clickable(role = Role.Button) { model.selected = provider }.testTag("network.provider.${provider.id}"))
            }
        }
    }
}

/** Distance as iOS `Measurement(.road)` abbreviated: "850 m", "1,2 km". */
private fun distanceText(meters: Int): String =
    if (meters < 1000) "${(meters / 10.0).roundToInt() * 10} m"
    else String.format(L10n.locale, if (meters < 10_000) "%.1f km" else "%.0f km", meters / 1000.0)

@Composable
private fun ProviderRow(provider: Provider, modifier: Modifier = Modifier) {
    Row(
        Modifier
            .clip(androidx.compose.foundation.shape.RoundedCornerShape(DS.Radius.l))
            .then(modifier)
            .card(DS.Spacing.m)
            .semantics(mergeDescendants = true) {},
        verticalAlignment = Alignment.Top,
    ) {
        IconTile(provider.type.symbol, size = 40.dp, iconSize = 22.dp)
        Spacer(Modifier.width(DS.Spacing.m))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(provider.name, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
            Text(provider.specialty ?: provider.type.label, style = DS.Typography.caption, color = DS.Palette.textSecondary)
            Text("${provider.address}, ${provider.city}", style = DS.Typography.caption, color = DS.Palette.textSecondary)
            if (provider.tiersPayant) StatusBadge(t("Tiers-payant"), StatusTone.success)
        }
        provider.distanceMeters?.let {
            Spacer(Modifier.width(DS.Spacing.s))
            Text(distanceText(it), style = DS.Typography.caption.copy(fontFeatureSettings = "tnum"), color = DS.Palette.textSecondary)
        }
    }
}

@Composable
private fun ProviderDetail(provider: Provider) {
    val context = LocalContext.current
    Column(
        Modifier.fillMaxWidth().padding(start = DS.Spacing.xl, end = DS.Spacing.xl, bottom = DS.Spacing.xl).navigationBarsPadding(),
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Text(provider.name, style = DS.Typography.title, color = DS.Palette.textPrimary)
        Text(provider.specialty ?: provider.type.label, style = DS.Typography.body, color = DS.Palette.textSecondary)
        InfoRow(t("Adresse"), "${provider.address}, ${provider.city}")
        provider.openingHours?.let { InfoRow(t("Horaires"), it) }
        InfoRow(t("Tiers-payant"), if (provider.tiersPayant) t("Accepté") else t("Non"))
        if (provider.locationApproximate == true) {
            IconLabel(
                t("Position approximative sur la carte : l'itinéraire utilise l'adresse."), "mappin.and.ellipse",
                style = DS.Typography.footnote, color = DS.Palette.textSecondary,
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            provider.phone?.let { phone ->
                val digits = phone.filter { it.isDigit() || it == '+' }
                SecondaryButton(t("Appeler"), { Documents.openUrl(context, "tel:$digits") }, Modifier.weight(1f), symbol = "phone.fill")
            }
            PrimaryButton(t("Itinéraire"), { Documents.openUrl(context, directionsUrl(provider)) }, Modifier.weight(1f), symbol = "arrow.triangle.turn.up.right.diamond.fill")
        }
    }
}

/** Driving directions to exact coordinates, or a search by name and address when the position is approximate. */
private fun directionsUrl(provider: Provider): String {
    val coordinate = provider.coordinate
    return if (coordinate != null && provider.locationApproximate != true) {
        "https://www.google.com/maps/dir/?api=1&destination=${coordinate.latitude},${coordinate.longitude}&travelmode=driving"
    } else {
        "geo:0,0?q=" + Uri.encode("${provider.name}, ${provider.address}, ${provider.city}")
    }
}

// MARK: - Map (osmdroid, OpenStreetMap tiles)

@Composable
private fun ProvidersMap(model: NetworkModel) {
    val context = LocalContext.current
    val density = LocalDensity.current
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    val painters: Map<ProviderType, VectorPainter> = ProviderType.entries.associateWith { rememberVectorPainter(sym(it.symbol)) }
    val teal = DS.Palette.teal
    val mint = DS.Palette.mint
    val info = DS.Palette.info
    val textColor = DS.Palette.textPrimary
    val halo = DS.Palette.background
    var mapView by remember { mutableStateOf<MapView?>(null) }

    DisposableEffect(lifecycle, mapView) {
        val view = mapView
        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_RESUME -> view?.onResume()
                Lifecycle.Event.ON_PAUSE -> view?.onPause()
                else -> Unit
            }
        }
        lifecycle.addObserver(observer)
        onDispose { lifecycle.removeObserver(observer) }
    }

    AndroidView(
        modifier = Modifier.fillMaxSize(),
        factory = { ctx ->
            Configuration.getInstance().apply {
                userAgentValue = ctx.packageName
                val base = File(ctx.cacheDir, "osmdroid")
                osmdroidBasePath = base
                osmdroidTileCache = File(base, "tiles")
            }
            MapView(ctx).apply {
                setTileSource(TileSourceFactory.MAPNIK)
                setMultiTouchControls(true)
                isTilesScaledToDpi = true
                zoomController.setVisibility(CustomZoomButtonsController.Visibility.NEVER)
                val center = model.userLocation ?: NetworkModel.dakar
                // ~20 km region, like the iOS initial camera.
                controller.setZoom(12.0)
                controller.setCenter(GeoPoint(center.latitude, center.longitude))
                onResume()
                mapView = this
            }
        },
        update = { map ->
            map.overlays.clear()
            model.userLocation?.let { user ->
                map.overlays.add(Marker(map).apply {
                    position = GeoPoint(user.latitude, user.longitude)
                    icon = BitmapDrawable(context.resources, userDot(density, info))
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    title = t("Vous")
                    setInfoWindow(null)
                    setOnMarkerClickListener { _, _ -> true }
                })
            }
            model.providers.forEach { provider ->
                val coordinate = provider.coordinate ?: return@forEach
                val painter = painters.getValue(provider.type)
                val (bitmap, anchorY) = providerAnnotation(density, painter, provider.name, teal, mint, textColor, halo)
                map.overlays.add(Marker(map).apply {
                    position = GeoPoint(coordinate.latitude, coordinate.longitude)
                    icon = BitmapDrawable(context.resources, bitmap)
                    setAnchor(Marker.ANCHOR_CENTER, anchorY)
                    title = "${provider.name}, ${provider.type.label}"
                    setInfoWindow(null)
                    setOnMarkerClickListener { _, _ -> model.selected = provider; true }
                })
            }
            map.invalidate()
        },
        onRelease = { map ->
            map.onPause()
            map.onDetach()
            mapView = null
        },
    )
}

/** Blue dot with a white ring (iOS "Vous" annotation). */
private fun userDot(density: Density, color: Color): Bitmap {
    val size = with(density) { 20.dp.toPx() }
    val image = ImageBitmap(size.roundToInt(), size.roundToInt())
    CanvasDrawScope().draw(density, LayoutDirection.Ltr, androidx.compose.ui.graphics.Canvas(image), Size(size, size)) {
        val radius = with(density) { 7.dp.toPx() }
        drawCircle(Color.Black.copy(alpha = 0.15f), radius + with(density) { 4.dp.toPx() })
        drawCircle(color, radius)
        drawCircle(Color.White, radius, style = Stroke(with(density) { 3.dp.toPx() }))
    }
    return image.asAndroidBitmap()
}

/**
 * iOS provider annotation: 30 pt teal circle with a mint ring and the white type icon, the provider name below
 * with a halo. Returns the bitmap and the vertical anchor (centre of the circle).
 */
private fun providerAnnotation(
    density: Density,
    painter: VectorPainter,
    name: String,
    teal: Color,
    mint: Color,
    textColor: Color,
    halo: Color,
): Pair<Bitmap, Float> {
    val circle = with(density) { 30.dp.toPx() }
    val icon = with(density) { 15.dp.toPx() }
    val gap = with(density) { 4.dp.toPx() }
    val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        textSize = with(density) { 11.dp.toPx() }
        typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
        textAlign = Paint.Align.CENTER
    }
    val label = if (name.length > 32) name.take(31) + "…" else name
    val textWidth = paint.measureText(label)
    val metrics = paint.fontMetrics
    val textHeight = metrics.descent - metrics.ascent
    val haloWidth = with(density) { 2.5.dp.toPx() }
    val width = maxOf(circle, textWidth + haloWidth * 2) + 2
    val height = circle + gap + textHeight + haloWidth
    val image = ImageBitmap(width.roundToInt(), height.roundToInt())
    val center = Offset(width / 2, circle / 2)
    CanvasDrawScope().draw(density, LayoutDirection.Ltr, androidx.compose.ui.graphics.Canvas(image), Size(width, height)) {
        val ring = with(density) { 2.dp.toPx() }
        drawCircle(teal, circle / 2 - ring / 2, center)
        drawCircle(mint, circle / 2 - ring / 2, center, style = Stroke(ring))
        translate(center.x - icon / 2, center.y - icon / 2) {
            with(painter) { draw(Size(icon, icon), colorFilter = ColorFilter.tint(Color.White)) }
        }
    }
    val bitmap = image.asAndroidBitmap()
    val canvas = android.graphics.Canvas(bitmap)
    val baseline = circle + gap - metrics.ascent
    paint.style = Paint.Style.STROKE
    paint.strokeWidth = haloWidth * 2
    paint.strokeJoin = Paint.Join.ROUND
    paint.color = halo.toArgb()
    canvas.drawText(label, width / 2, baseline, paint)
    paint.style = Paint.Style.FILL
    paint.color = textColor.toArgb()
    canvas.drawText(label, width / 2, baseline, paint)
    return bitmap to (circle / 2) / height
}
