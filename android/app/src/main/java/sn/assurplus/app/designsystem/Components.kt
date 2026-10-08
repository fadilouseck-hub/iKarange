package sn.assurplus.app.designsystem

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import sn.assurplus.app.core.format.Money
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.ClaimStatus
import sn.assurplus.app.core.model.Message
import sn.assurplus.app.core.model.ServerStatus
import sn.assurplus.app.core.model.StatusTone

// MARK: - Text

/** `Text` with the app's default colour (textPrimary). */
@Composable
fun DSText(
    text: String,
    style: TextStyle,
    modifier: Modifier = Modifier,
    color: Color = DS.Palette.textPrimary,
    weight: FontWeight? = null,
    align: TextAlign? = null,
    maxLines: Int = Int.MAX_VALUE,
) {
    Text(
        text = text, style = if (weight != null) style.copy(fontWeight = weight) else style, color = color,
        modifier = modifier, textAlign = align, maxLines = maxLines, overflow = TextOverflow.Ellipsis,
    )
}

// MARK: - Buttons

/** Filled brand button, 50 dp high (iOS `.buttonStyle(.primary)`). */
@Composable
fun PrimaryButton(
    title: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    loading: Boolean = false,
    symbol: String? = null,
    tag: String? = null,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val shape = RoundedCornerShape(DS.Radius.m)
    Row(
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 50.dp)
            .alpha(if (pressed) 0.85f else 1f)
            .clip(shape)
            .background(DS.Palette.primary.copy(alpha = if (enabled) 1f else 0.4f), shape)
            .clickable(interaction, null, enabled = enabled && !loading, role = Role.Button, onClick = onClick)
            .padding(horizontal = DS.Spacing.l)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            CircularProgressIndicator(color = DS.Palette.onPrimary, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(DS.Spacing.s))
        }
        if (symbol != null) {
            Icon(sym(symbol), null, tint = DS.Palette.onPrimary, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(DS.Spacing.s))
        }
        Text(title, style = DS.Typography.headline, color = DS.Palette.onPrimary, textAlign = TextAlign.Center)
    }
}

/** Outlined button on surface, 50 dp high (iOS `.buttonStyle(.secondary)`). */
@Composable
fun SecondaryButton(
    title: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    symbol: String? = null,
    color: Color = DS.Palette.primary,
    tag: String? = null,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val shape = RoundedCornerShape(DS.Radius.m)
    Row(
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 50.dp)
            .alpha(if (!enabled) 0.4f else if (pressed) 0.75f else 1f)
            .clip(shape)
            .background(DS.Palette.surface, shape)
            .border(1.dp, color.copy(alpha = 0.35f), shape)
            .clickable(interaction, null, enabled = enabled, role = Role.Button, onClick = onClick)
            .padding(horizontal = DS.Spacing.l)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (symbol != null) {
            Icon(sym(symbol), null, tint = color, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(DS.Spacing.s))
        }
        Text(title, style = DS.Typography.headline, color = color, textAlign = TextAlign.Center)
    }
}

/** Borderless text button in the accent colour (iOS plain `Button` with tint). */
@Composable
fun TextLink(
    title: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    style: TextStyle = DS.Typography.callout.copy(fontWeight = FontWeight.SemiBold),
    color: Color = DS.Palette.accent,
    enabled: Boolean = true,
    symbol: String? = null,
    tag: String? = null,
) {
    Row(
        modifier
            .alpha(if (enabled) 1f else 0.4f)
            .clip(RoundedCornerShape(DS.Radius.s))
            .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
            .padding(vertical = DS.Spacing.xs)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (symbol != null) {
            Icon(sym(symbol), null, tint = color, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(DS.Spacing.xs))
        }
        Text(title, style = style, color = color)
    }
}

// MARK: - Card

/** White rounded card with a hairline border (iOS `.card()`). */
@Composable
fun Modifier.card(padding: Dp = DS.Spacing.l): Modifier {
    val shape = RoundedCornerShape(DS.Radius.l)
    return this
        .fillMaxWidth()
        .clip(shape)
        .background(DS.Palette.surface, shape)
        .border(1.dp, DS.Palette.border.copy(alpha = 0.6f), shape)
        .padding(padding)
}

@Composable
fun Card(modifier: Modifier = Modifier, padding: Dp = DS.Spacing.l, content: @Composable ColumnScope.() -> Unit) {
    Column(modifier.card(padding), content = content)
}

/** Standard screen background. */
@Composable
fun Modifier.screenBackground(): Modifier = background(DS.Palette.background)

/** 0.5 pt hairline (iOS `Divider`). */
@Composable
fun Hairline(modifier: Modifier = Modifier, color: Color = DS.Palette.separator) {
    Box(modifier.fillMaxWidth().height(0.5.dp).background(color))
}

// MARK: - Status badge

@Composable
fun StatusBadge(text: String, tone: StatusTone, modifier: Modifier = Modifier, symbol: String? = null) {
    Row(
        modifier
            .clip(CircleShape)
            .background(tone.background)
            .padding(horizontal = DS.Spacing.s, vertical = DS.Spacing.xs)
            .semantics(mergeDescendants = true) { contentDescription = t("Statut : %@", text) },
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs),
    ) {
        if (symbol != null) Icon(sym(symbol), null, tint = tone.foreground, modifier = Modifier.size(12.dp))
        Text(text, style = DS.Typography.caption.copy(fontWeight = FontWeight.SemiBold), color = tone.foreground)
    }
}

@Composable
fun StatusBadge(status: ServerStatus, modifier: Modifier = Modifier) = StatusBadge(status.label, status.tone, modifier)

@Composable
fun StatusBadge(status: ClaimStatus, modifier: Modifier = Modifier) = StatusBadge(status.label, status.tone, modifier, status.symbol)

// MARK: - Rows

/** Label on the left, value on the right; stacks vertically when it does not fit. */
@Composable
fun InfoRow(label: String, value: String, modifier: Modifier = Modifier, emphasized: Boolean = false) {
    val valueStyle = DS.Typography.callout.copy(fontWeight = if (emphasized) FontWeight.SemiBold else FontWeight.Normal)
    FlowRow(
        modifier.fillMaxWidth().semantics(mergeDescendants = true) {},
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs),
    ) {
        Text(label, style = DS.Typography.callout, color = DS.Palette.textSecondary, modifier = Modifier.padding(end = DS.Spacing.m))
        Text(value, style = valueStyle, color = DS.Palette.textPrimary, textAlign = TextAlign.End)
    }
}

@Composable
fun SectionHeader(title: String, modifier: Modifier = Modifier, actionTitle: String? = null, action: (() -> Unit)? = null) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(title, style = DS.Typography.headline, color = DS.Palette.textPrimary, modifier = Modifier.weight(1f).semantics { heading() })
        if (actionTitle != null && action != null) TextLink(actionTitle, action)
    }
}

@Composable
fun MessageBanner(message: Message, modifier: Modifier = Modifier) {
    val tone = message.level.tone
    val symbol = when (message.level) {
        Message.Level.info -> "info.circle.fill"
        Message.Level.warning -> "exclamationmark.triangle.fill"
        Message.Level.error -> "xmark.octagon.fill"
        Message.Level.success -> "checkmark.circle.fill"
    }
    Row(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(DS.Radius.m))
            .background(tone.background)
            .padding(DS.Spacing.m)
            .semantics(mergeDescendants = true) {},
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        Icon(sym(symbol), null, tint = tone.foreground, modifier = Modifier.size(20.dp))
        Text(message.text, style = DS.Typography.callout, color = DS.Palette.textPrimary, modifier = Modifier.weight(1f))
    }
}

/** Horizontal progress through a multi-step flow. */
@Composable
fun StepProgress(current: Int, total: Int, title: String, modifier: Modifier = Modifier) {
    val fraction by animateFloatAsState(current.toFloat() / total.coerceAtLeast(1), label = "step")
    Column(
        modifier.fillMaxWidth().semantics(mergeDescendants = true) { contentDescription = t("%@, étape %lld sur %lld", title, current, total) },
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.s),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(title, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.textPrimary, modifier = Modifier.weight(1f))
            Text(t("Étape %lld sur %lld", current, total), style = DS.Typography.caption, color = DS.Palette.textSecondary)
        }
        ProgressBar(fraction, color = DS.Palette.accent, height = 6.dp)
    }
}

/** iOS linear `ProgressView`: rounded track and fill. */
@Composable
fun ProgressBar(value: Float, modifier: Modifier = Modifier, color: Color = DS.Palette.accent, height: Dp = 4.dp) {
    val track = DS.Palette.dynamic(0xE3E3E8, 0x2C3A3A)
    Canvas(modifier.fillMaxWidth().height(height)) {
        val radius = CornerRadius(size.height / 2, size.height / 2)
        drawRoundRect(track, cornerRadius = radius)
        val width = size.width * value.coerceIn(0f, 1f)
        if (width > 0) drawRoundRect(color, size = size.copy(width = width.coerceAtLeast(size.height)), cornerRadius = radius)
    }
}

/** Labelled amount used in dashboards and settlements. */
@Composable
fun AmountTile(label: String, amount: Long, modifier: Modifier = Modifier, tone: StatusTone = StatusTone.neutral) {
    Column(modifier.semantics(mergeDescendants = true) {}, verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
        Text(label, style = DS.Typography.caption, color = DS.Palette.textSecondary)
        Text(
            Money.format(amount), style = DS.Typography.amountSmall, maxLines = 1, softWrap = false,
            color = if (tone == StatusTone.neutral) DS.Palette.textPrimary else tone.foreground,
        )
    }
}

/** Field with a label above and an optional error below; the input is drawn in a rounded bordered box. */
@Composable
fun LabeledField(label: String, modifier: Modifier = Modifier, error: String? = null, content: @Composable RowScope.() -> Unit) {
    val shape = RoundedCornerShape(DS.Radius.m)
    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xs)) {
        Text(label, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.Medium), color = DS.Palette.textSecondary)
        Row(
            Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clip(shape)
                .background(DS.Palette.surface, shape)
                .border(if (error == null) 1.dp else 1.5.dp, if (error == null) DS.Palette.border else DS.Palette.danger, shape)
                .padding(horizontal = DS.Spacing.m),
            verticalAlignment = Alignment.CenterVertically,
            content = content,
        )
        if (error != null) Text(error, style = DS.Typography.caption, color = DS.Palette.danger)
    }
}

/** Plain single-line text input with a grey placeholder (iOS `TextField` / `SecureField`). */
@Composable
fun RowScope.InputText(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    modifier: Modifier = Modifier,
    keyboardType: KeyboardType = KeyboardType.Text,
    secure: Boolean = false,
    singleLine: Boolean = true,
    tag: String? = null,
) {
    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        singleLine = singleLine,
        textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
        cursorBrush = SolidColor(DS.Palette.accent),
        keyboardOptions = KeyboardOptions(keyboardType = if (secure) KeyboardType.Password else keyboardType),
        visualTransformation = if (secure) PasswordVisualTransformation() else VisualTransformation.None,
        modifier = modifier.weight(1f).padding(vertical = DS.Spacing.m).then(if (tag != null) Modifier.testTag(tag) else Modifier),
        decorationBox = { inner ->
            Box {
                if (value.isEmpty()) Text(placeholder, style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                inner()
            }
        },
    )
}

// MARK: - Controls

/** iOS segmented control. */
@Composable
fun <T> SegmentedPicker(
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    modifier: Modifier = Modifier,
    tag: String? = null,
) {
    val shape = RoundedCornerShape(9.dp)
    Row(
        modifier
            .fillMaxWidth()
            .height(32.dp)
            .clip(shape)
            .background(DS.Palette.dynamic(0xE3E3E8, 0x1D2D2D))
            .padding(2.dp)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
    ) {
        options.forEach { option ->
            val isSelected = option == selected
            Box(
                Modifier
                    .weight(1f)
                    .fillMaxHeight()
                    .then(
                        if (isSelected) Modifier.shadow(2.dp, RoundedCornerShape(7.dp)).background(DS.Palette.dynamic(0xFFFFFF, 0x3A4A4A), RoundedCornerShape(7.dp))
                        else Modifier
                    )
                    .clip(RoundedCornerShape(7.dp))
                    .clickable(role = Role.Tab) { onSelect(option) },
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    label(option), maxLines = 1, overflow = TextOverflow.Ellipsis,
                    style = DS.Typography.footnote.copy(fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Medium),
                    color = DS.Palette.textPrimary,
                )
            }
        }
    }
}

/** iOS switch (wide capsule, white thumb), tinted with the accent colour. */
@Composable
fun IOSSwitch(checked: Boolean, onCheckedChange: (Boolean) -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true, tag: String? = null) {
    val track by animateColorAsState(if (checked) DS.Palette.accent else DS.Palette.dynamic(0xE3E3E8, 0x39393D), label = "track")
    val offset by animateDpAsState(if (checked) 26.dp else 2.dp, label = "thumb")
    Box(
        modifier
            .size(width = 64.dp, height = 28.dp)
            .alpha(if (enabled) 1f else 0.5f)
            .clip(CircleShape)
            .background(track)
            .clickable(enabled = enabled, role = Role.Switch) { onCheckedChange(!checked) }
            .semantics { contentDescription = if (checked) "on" else "off" }
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        contentAlignment = Alignment.CenterStart,
    ) {
        Box(
            Modifier
                .offset(x = offset)
                .size(width = 36.dp, height = 24.dp)
                .shadow(2.dp, CircleShape)
                .background(Color.White, CircleShape)
        )
    }
}

/** Title on the left, switch on the right (iOS `Toggle`). */
@Composable
fun ToggleRow(title: String, checked: Boolean, onCheckedChange: (Boolean) -> Unit, modifier: Modifier = Modifier, tag: String? = null, subtitle: (@Composable () -> Unit)? = null) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f).padding(end = DS.Spacing.m), verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(title, style = DS.Typography.body, color = DS.Palette.textPrimary)
            subtitle?.invoke()
        }
        IOSSwitch(checked, onCheckedChange, tag = tag)
    }
}

// MARK: - Avatars & chips

@Composable
fun InitialsAvatar(name: String, modifier: Modifier = Modifier, size: Dp = 44.dp) {
    Box(
        modifier.size(size).background(DS.Palette.mint.copy(alpha = 0.35f), CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Text(initials(name), color = DS.Palette.teal, fontWeight = FontWeight.Bold, fontSize = (size.value * 0.38f).sp)
    }
}

fun initials(name: String): String =
    name.split(" ").filter { it.isNotBlank() }.take(2).mapNotNull { it.firstOrNull()?.toString() }.joinToString("").uppercase()

/** Capsule filter chip: filled with the primary colour when selected (beneficiary switcher, filters). */
@Composable
fun Chip(title: String, selected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier, symbol: String? = null, tag: String? = null) {
    Row(
        modifier
            .clip(CircleShape)
            .background(if (selected) DS.Palette.primary else DS.Palette.surface)
            .then(if (selected) Modifier else Modifier.border(BorderStroke(1.dp, DS.Palette.border.copy(alpha = 0.6f)), CircleShape))
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = DS.Spacing.l, vertical = 10.dp)
            .then(if (tag != null) Modifier.testTag(tag) else Modifier),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.xs),
    ) {
        val color = if (selected) DS.Palette.onPrimary else DS.Palette.textPrimary
        if (symbol != null) Icon(sym(symbol), null, tint = color, modifier = Modifier.size(16.dp))
        Text(title, style = DS.Typography.subheadline.copy(fontWeight = FontWeight.Medium), color = color)
    }
}

/** Accent icon in a soft rounded square (quick actions, list leading icons). */
@Composable
fun IconTile(symbol: String, modifier: Modifier = Modifier, size: Dp = 48.dp, iconSize: Dp = 24.dp, tint: Color = DS.Palette.accent, background: Color = DS.Palette.accentSoft) {
    Box(modifier.size(size).background(background, RoundedCornerShape(DS.Radius.m)), contentAlignment = Alignment.Center) {
        Icon(sym(symbol), null, tint = tint, modifier = Modifier.size(iconSize))
    }
}
