package sn.assurplus.app.features.subscription

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.LocalDay
import sn.assurplus.app.designsystem.DS
import sn.assurplus.app.designsystem.sym
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

/** Material3 date picker limited to past days (iOS `DatePicker(in: ...Date.now)`). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun BirthDatePickerDialog(initial: LocalDay, onDismiss: () -> Unit, onPick: (LocalDay) -> Unit) {
    val todayMillis = remember { LocalDate.now().atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli() }
    val state = rememberDatePickerState(
        initialSelectedDateMillis = initial.date.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
        selectableDates = object : SelectableDates {
            override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis <= todayMillis
            override fun isSelectableYear(year: Int) = year <= LocalDate.now().year
        },
    )
    val colors = DatePickerDefaults.colors(
        containerColor = DS.Palette.surface,
        selectedDayContainerColor = DS.Palette.accent,
        selectedDayContentColor = DS.Palette.onPrimary,
        todayDateBorderColor = DS.Palette.accent,
        todayContentColor = DS.Palette.accent,
        selectedYearContainerColor = DS.Palette.accent,
        selectedYearContentColor = DS.Palette.onPrimary,
    )
    DatePickerDialog(
        onDismissRequest = onDismiss,
        colors = colors,
        confirmButton = {
            TextButton(onClick = {
                state.selectedDateMillis?.let { onPick(LocalDay.of(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate())) }
                onDismiss()
            }) { Text(t("OK"), style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.accent) }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(t("Annuler"), style = DS.Typography.body, color = DS.Palette.accent) }
        },
    ) {
        DatePicker(state = state, colors = colors)
    }
}

/** iOS `Picker(.menu)`: the selected label in the accent colour with an up/down chevron, opening a menu. */
@Composable
internal fun <T> MenuPicker(
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    modifier: Modifier = Modifier,
    tag: String? = null,
) {
    var expanded by remember { mutableStateOf(false) }
    Box(modifier) {
        Row(
            Modifier
                .clip(RoundedCornerShape(DS.Radius.s))
                .clickable(role = Role.DropdownList) { expanded = true }
                .padding(vertical = DS.Spacing.xs)
                .then(if (tag != null) Modifier.testTag(tag) else Modifier),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs),
        ) {
            Text(label(selected), style = DS.Typography.body, color = DS.Palette.accent)
            Icon(sym("chevron.up.chevron.down"), null, tint = DS.Palette.accent, modifier = Modifier.size(16.dp))
        }
        DropdownMenu(expanded, onDismissRequest = { expanded = false }, containerColor = DS.Palette.surface) {
            options.forEach { option ->
                DropdownMenuItem(
                    text = { Text(label(option), style = DS.Typography.body, color = DS.Palette.textPrimary) },
                    trailingIcon = if (option == selected) ({ Icon(sym("checkmark"), null, tint = DS.Palette.textPrimary) }) else null,
                    onClick = { expanded = false; onSelect(option) },
                )
            }
        }
    }
}

/** iOS `TextField(...).textFieldStyle(.roundedBorder)`. */
@Composable
internal fun RoundedBorderField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    keyboardType: KeyboardType = KeyboardType.Text,
    singleLine: Boolean = true,
) {
    val shape = RoundedCornerShape(6.dp)
    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        singleLine = singleLine,
        textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
        cursorBrush = SolidColor(DS.Palette.accent),
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        modifier = modifier
            .fillMaxWidth()
            .background(DS.Palette.surface, shape)
            .border(0.5.dp, DS.Palette.border, shape)
            .padding(horizontal = 7.dp, vertical = 6.dp),
    )
}

/** Compact grey date "pill" shown by an iOS `DatePicker` in a form row. */
@Composable
internal fun DatePill(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Text(
        text, style = DS.Typography.body, color = DS.Palette.textPrimary,
        modifier = modifier
            .clip(RoundedCornerShape(DS.Radius.s))
            .background(DS.Palette.surfaceMuted)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 11.dp, vertical = 6.dp),
    )
}
