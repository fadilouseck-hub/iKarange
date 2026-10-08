package sn.assurplus.app.designsystem

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.LocalDay
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

/**
 * Small iOS form sheet (`.sheet { NavigationStack { Form … } }`): full-height page sheet with "Annuler" on the
 * left, an inline title and a confirmation button (or a spinner while sending) on the right.
 */
@Composable
fun FormSheet(
    visible: Boolean,
    title: String,
    onDismiss: () -> Unit,
    confirmTitle: String,
    onConfirm: () -> Unit,
    confirmEnabled: Boolean = true,
    confirmLoading: Boolean = false,
    confirmTag: String? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    if (!visible) return
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        SheetContainer(onDismiss) {
            Screen(
                title = title,
                largeTitle = false,
                leading = { GlassTextButton(t("Annuler"), onDismiss) },
                actions = {
                    if (confirmLoading) {
                        Box(Modifier.size(44.dp), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
                        }
                    } else {
                        GlassTextButton(confirmTitle, onConfirm, enabled = confirmEnabled, tint = DS.Palette.accent, tag = confirmTag)
                    }
                },
                spacing = DS.Spacing.xl,
                content = content,
            )
        }
    }
}

/** iOS `.alert` with a `TextField` (reason for a removal or a termination). */
@Composable
fun TextFieldAlert(
    title: String,
    message: String?,
    placeholder: String,
    value: String,
    onValueChange: (String) -> Unit,
    confirmTitle: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    destructive: Boolean = false,
    fieldTag: String? = null,
    confirmTag: String? = null,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = DS.Palette.surface,
        title = { Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                if (message != null) Text(message, style = DS.Typography.callout, color = DS.Palette.textSecondary)
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(DS.Radius.s))
                        .background(DS.Palette.surfaceMuted)
                        .padding(horizontal = DS.Spacing.s),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    InputText(value, onValueChange, placeholder, tag = fieldTag)
                }
            }
        },
        confirmButton = {
            TextButton(onClick = { onDismiss(); onConfirm() }, modifier = if (confirmTag != null) Modifier.testTag(confirmTag) else Modifier) {
                Text(confirmTitle, style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = if (destructive) DS.Palette.destructive else DS.Palette.accent)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text(t("Annuler"), style = DS.Typography.body, color = DS.Palette.accent) }
        },
    )
}

/** Text field row inside a [FormSection] (iOS `TextField` in a `Form`). */
@Composable
fun FormTextFieldRow(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    divider: Boolean = true,
    tag: String? = null,
    capitalization: KeyboardCapitalization = KeyboardCapitalization.Sentences,
) {
    Column(Modifier.fillMaxWidth()) {
        if (divider) Hairline(Modifier.padding(start = DS.Spacing.l, end = DS.Spacing.l))
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
            cursorBrush = SolidColor(DS.Palette.accent),
            keyboardOptions = KeyboardOptions(capitalization = capitalization),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 52.dp)
                .padding(horizontal = DS.Spacing.l, vertical = 15.dp)
                .then(if (tag != null) Modifier.testTag(tag) else Modifier),
            decorationBox = { inner ->
                Box {
                    if (value.isEmpty()) Text(placeholder, style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                    inner()
                }
            },
        )
    }
}

/** iOS `Picker` (menu style) inside a `Form`: title on the left, current value and ⌃⌄ on the right. */
@Composable
fun <T> FormPickerRow(
    title: String,
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    divider: Boolean = true,
    symbol: ((T) -> String)? = null,
    tag: String? = null,
) {
    var expanded by remember { mutableStateOf(false) }
    FormRow(title = title, divider = divider, onClick = { expanded = true }, tag = tag, trailing = {
        Box {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
                if (symbol != null) Icon(sym(symbol(selected)), null, tint = DS.Palette.textSecondary, modifier = Modifier.size(18.dp))
                Text(label(selected), style = DS.Typography.body, color = DS.Palette.textSecondary)
                Icon(sym("chevron.up.chevron.down"), null, tint = DS.Palette.textSecondary, modifier = Modifier.size(16.dp))
            }
            DropdownMenu(expanded, onDismissRequest = { expanded = false }, containerColor = DS.Palette.surface) {
                options.forEach { option ->
                    DropdownMenuItem(
                        text = { Text(label(option), style = DS.Typography.body, color = DS.Palette.textPrimary) },
                        leadingIcon = if (symbol != null) ({ Icon(sym(symbol(option)), null, tint = DS.Palette.textPrimary) }) else null,
                        trailingIcon = if (option == selected) ({ Icon(sym("checkmark"), null, tint = DS.Palette.accent) }) else null,
                        onClick = { expanded = false; onSelect(option) },
                    )
                }
            }
        }
    })
}

/**
 * iOS compact `DatePicker` inside a `Form`: title on the left, the date in a grey pill on the right; tapping
 * opens the Material date picker. Dates after [latest] cannot be selected.
 */
@Composable
fun FormDateRow(title: String, value: LocalDay, onChange: (LocalDay) -> Unit, latest: LocalDay = LocalDay.today(), divider: Boolean = true) {
    var open by remember { mutableStateOf(false) }
    FormRow(title = title, divider = divider, onClick = { open = true }, trailing = {
        Text(
            DateText.day(value), style = DS.Typography.body, color = DS.Palette.textPrimary,
            modifier = Modifier
                .clip(RoundedCornerShape(DS.Radius.s))
                .background(DS.Palette.surfaceMuted)
                .padding(horizontal = DS.Spacing.m, vertical = 6.dp),
        )
    })
    if (open) {
        val latestMillis = latest.date.atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli()
        val state = rememberDatePickerState(
            initialSelectedDateMillis = value.date.atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli(),
            selectableDates = object : SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis <= latestMillis
                override fun isSelectableYear(year: Int) = year <= latest.year
            },
        )
        DatePickerDialog(
            onDismissRequest = { open = false },
            confirmButton = {
                TextButton(onClick = {
                    state.selectedDateMillis?.let { millis ->
                        onChange(LocalDay.of(LocalDate.ofInstant(Instant.ofEpochMilli(millis), ZoneOffset.UTC)))
                    }
                    open = false
                }) { Text(t("OK"), color = DS.Palette.accent) }
            },
            dismissButton = { TextButton(onClick = { open = false }) { Text(t("Annuler"), color = DS.Palette.accent) } },
            colors = DatePickerDefaults.colors(containerColor = DS.Palette.surface),
        ) {
            DatePicker(state, colors = DatePickerDefaults.colors(containerColor = DS.Palette.surface, selectedDayContainerColor = DS.Palette.accent, todayDateBorderColor = DS.Palette.accent))
        }
    }
}

/** Capsule filter used by the vault and the care network (icon + label, primary fill when selected, no border). */
@Composable
fun FilterCapsule(label: String, symbol: String, selected: Boolean, onClick: () -> Unit, tag: String? = null) {
    val color = if (selected) DS.Palette.onPrimary else DS.Palette.textPrimary
    Row(
        Modifier
            .clip(CircleShape)
            .background(if (selected) DS.Palette.primary else DS.Palette.surface)
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = DS.Spacing.m, vertical = DS.Spacing.s)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        Icon(sym(symbol), null, tint = color, modifier = Modifier.size(18.dp))
        Text(label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = color, maxLines = 1)
    }
}

/** iOS `Label(title, systemImage:)`: icon then text on one baseline. */
@Composable
fun IconLabel(
    text: String,
    symbol: String,
    modifier: Modifier = Modifier,
    style: androidx.compose.ui.text.TextStyle = DS.Typography.callout,
    color: Color = DS.Palette.textPrimary,
    iconTint: Color = color,
) {
    val iconSize = style.fontSize.value + 3
    val lineHeight = if (style.lineHeight.isSp) style.lineHeight.value else iconSize
    Row(modifier, verticalAlignment = Alignment.Top, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        Icon(
            sym(symbol), null, tint = iconTint,
            modifier = Modifier.padding(top = ((lineHeight - iconSize) / 2).coerceAtLeast(0f).dp).size(iconSize.dp),
        )
        Text(text, style = style, color = color)
    }
}
