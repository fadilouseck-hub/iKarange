package sn.assurplus.app.features.subscription

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.Gender
import sn.assurplus.app.core.model.LocalDay
import sn.assurplus.app.core.model.QuoteMember
import sn.assurplus.app.core.model.Relation
import sn.assurplus.app.designsystem.DS
import sn.assurplus.app.designsystem.FormRow
import sn.assurplus.app.designsystem.FormSection
import sn.assurplus.app.designsystem.GlassTextButton
import sn.assurplus.app.designsystem.Hairline
import sn.assurplus.app.designsystem.Screen
import sn.assurplus.app.designsystem.SheetContainer
import java.time.LocalDate

/** Adds a beneficiary to the quote (identity, relation, birth date). Port of the iOS `DependentFormSheet`. */
@Composable
internal fun DependentFormSheet(onDismiss: () -> Unit, onSave: (QuoteMember) -> Unit) {
    var firstName by remember { mutableStateOf("") }
    var lastName by remember { mutableStateOf("") }
    var relation by remember { mutableStateOf(Relation.child) }
    var gender by remember { mutableStateOf(Gender.female) }
    var birthDate by remember { mutableStateOf(LocalDay.of(LocalDate.now().minusYears(5))) }
    var showDatePicker by remember { mutableStateOf(false) }
    val canSave = firstName.trim().isNotEmpty() && lastName.trim().isNotEmpty()

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        Box(Modifier.fillMaxSize()) {
            SheetContainer(onDismiss = onDismiss) {
                Screen(
                    t("Ajouter un ayant droit"),
                    largeTitle = false,
                    leading = { GlassTextButton(t("Annuler"), onDismiss, tint = DS.Palette.accent) },
                    actions = {
                        GlassTextButton(
                            t("Ajouter"),
                            {
                                onSave(QuoteMember(firstName.trim(), lastName.trim(), relation, birthDate, gender))
                                onDismiss()
                            },
                            enabled = canSave, tint = DS.Palette.accent,
                        )
                    },
                ) {
                    FormSection {
                        FormRow(t("Lien"), divider = false, trailing = {
                            MenuPicker(Relation.entries, relation, { it.label }, { relation = it })
                        })
                        FormTextRow(firstName, { firstName = it }, t("Prénom"))
                        FormTextRow(lastName, { lastName = it }, t("Nom"))
                        FormRow(t("Sexe"), trailing = {
                            MenuPicker(Gender.entries, gender, { it.label }, { gender = it })
                        })
                        FormRow(t("Date de naissance"), trailing = {
                            DatePill(DateText.day(birthDate), { showDatePicker = true })
                        })
                    }
                }
            }
        }
    }
    if (showDatePicker) {
        BirthDatePickerDialog(birthDate, onDismiss = { showDatePicker = false }) { birthDate = it }
    }
}

/** A `TextField` row inside a form section (placeholder = label, as in an iOS `Form`). */
@Composable
private fun FormTextRow(value: String, onValueChange: (String) -> Unit, placeholder: String) {
    Column(Modifier.fillMaxWidth()) {
        Hairline(Modifier.padding(horizontal = DS.Spacing.l))
        Row(Modifier.fillMaxWidth().heightIn(min = 60.dp).padding(horizontal = DS.Spacing.l), verticalAlignment = Alignment.CenterVertically) {
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = true,
                textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
                cursorBrush = SolidColor(DS.Palette.accent),
                modifier = Modifier.fillMaxWidth(),
                decorationBox = { inner ->
                    Box {
                        if (value.isEmpty()) {
                            Text(placeholder, style = DS.Typography.body.copy(fontWeight = FontWeight.Normal), color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                        }
                        inner()
                    }
                },
            )
        }
    }
}
