package sn.assurplus.app.features.home

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.ClaimSummary
import sn.assurplus.app.designsystem.*

/** One claim in a list: status icon, type, beneficiary · date, status badge, amount and number (shared). */
@Composable
fun ClaimRow(claim: ClaimSummary, modifier: Modifier = Modifier) {
    Row(
        modifier.fillMaxWidth().padding(vertical = DS.Spacing.s).semantics(mergeDescendants = true) {},
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        Box(Modifier.size(36.dp).background(claim.status.tone.background, CircleShape), contentAlignment = Alignment.Center) {
            Icon(sym(claim.status.symbol), null, tint = claim.status.tone.foreground, modifier = Modifier.size(18.dp))
        }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(claim.typeLabel, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary)
            Text(t("%@ · %@", claim.beneficiaryName, DateText.short(claim.createdAt)), style = DS.Typography.caption, color = DS.Palette.textSecondary)
            StatusBadge(claim.status)
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            claim.amount?.let {
                Text(Money.format(it), style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum"), color = DS.Palette.textPrimary)
            }
            claim.number?.let { Text(it, style = DS.Typography.caption2, color = DS.Palette.textSecondary) }
        }
    }
}
