package sn.assurplus.app.features.claims

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import kotlinx.coroutines.launch
import kotlinx.serialization.builtins.ListSerializer
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.Route
import sn.assurplus.app.app.Sheet
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.ClaimStatus
import sn.assurplus.app.core.model.ClaimSummary
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.Permission
import sn.assurplus.app.core.persist.CacheKey
import sn.assurplus.app.core.persist.RemoteResource
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.features.home.ClaimRow

private enum class ClaimsFilter {
    all, ongoing, finished;

    val label: String
        get() = when (this) {
            all -> t("Tous")
            ongoing -> t("En cours")
            finished -> t("Terminés")
        }

    fun includes(status: ClaimStatus): Boolean {
        val finishedStatuses = setOf(ClaimStatus.paid, ClaimStatus.closed, ClaimStatus.rejected)
        return when (this) {
            all -> true
            ongoing -> status !in finishedStatuses
            finished -> status in finishedStatuses
        }
    }
}

/** Root of the Sinistres tab: draft banner, status filter, claims list (iOS `ClaimsListView`). */
@Composable
fun ClaimsListScreen() {
    val env = LocalEnv.current
    val claims = remember { RemoteResource(env.cache, CacheKey.claims, ListSerializer(ClaimSummary.serializer())) { env.api.claims() } }
    var filter by rememberSaveable { mutableStateOf(ClaimsFilter.all) }
    val scope = rememberCoroutineScope()
    val reload: () -> Unit = { scope.launch { claims.load() } }

    val sheet = env.router.presentedSheet
    // Re-read the draft and reload the list whenever the declaration sheet closes.
    val hasDraft = remember(sheet) { env.cache.load(ClaimDraft.serializer(), CacheKey.claimDraft) != null }
    LaunchedEffect(Unit) { claims.load() }
    LaunchedEffect(sheet) { if (sheet == null) claims.load() }

    Screen(
        t("Sinistres"),
        spacing = DS.Spacing.m,
        onRefresh = { claims.refresh() },
        actions = {
            if (env.session.can(Permission.claimsCreate)) {
                GlassButton(
                    "plus.circle.fill", { env.router.presentedSheet = Sheet.newClaim },
                    description = t("Déclarer"), tag = "claims.new",
                )
            }
        },
    ) {
        if (hasDraft) {
            MessageBanner(
                Message(Message.Level.info, t("Une déclaration est en brouillon. Touchez pour la reprendre.")),
                Modifier.clickable(role = Role.Button) { env.router.presentedSheet = Sheet.newClaim },
            )
        }
        SegmentedPicker(ClaimsFilter.entries, filter, { it.label }, { filter = it })

        LoadableContent(
            claims, retry = reload,
            placeholder = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    SkeletonCard(lines = 2); SkeletonCard(lines = 2); SkeletonCard(lines = 2)
                }
            },
        ) { list ->
            val visible = list.filter { filter.includes(it.status) }
            if (visible.isEmpty()) {
                EmptyStateView(
                    t("Aucun sinistre"),
                    t("Photographiez une facture pour déclarer vos frais de santé."),
                    symbol = "doc.text.magnifyingglass",
                )
            } else {
                Card(padding = DS.Spacing.m) {
                    visible.forEachIndexed { index, claim ->
                        ClaimRow(
                            claim,
                            Modifier
                                .clickable(role = Role.Button) { env.router.claimsPath.add(Route.Claim(claim.id)) }
                                .testTag("claims.row.${claim.id}"),
                        )
                        if (index < visible.lastIndex) Hairline()
                    }
                }
            }
        }
    }
}
