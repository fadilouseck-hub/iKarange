package sn.assurplus.app.designsystem

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp

/**
 * Tappable choice card: optional leading view, title / subtitle, checkmark when selected or a chevron otherwise
 * (iOS `SelectableRow`, used by the claim declaration and the payment method choice).
 */
@Composable
fun SelectableRow(
    title: String,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    tag: String? = null,
    leading: (@Composable () -> Unit)? = null,
) {
    val shape = RoundedCornerShape(DS.Radius.l)
    Row(
        modifier
            .fillMaxWidth()
            .clip(shape)
            .background(DS.Palette.surface, shape)
            .border(
                if (isSelected) 2.dp else 1.dp,
                if (isSelected) DS.Palette.accent else DS.Palette.border.copy(alpha = 0.6f),
                shape,
            )
            .clickable(role = Role.Button, onClick = onClick)
            .semantics(mergeDescendants = true) { selected = isSelected }
            .padding(DS.Spacing.m)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m),
    ) {
        leading?.invoke()
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary)
            if (subtitle != null) Text(subtitle, style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
        Icon(
            sym(if (isSelected) "checkmark.circle.fill" else "chevron.right"), null,
            tint = if (isSelected) DS.Palette.accent else DS.Palette.textSecondary,
            modifier = Modifier.size(22.dp),
        )
    }
}
