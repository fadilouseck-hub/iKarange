package sn.assurplus.app.features.profile

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.window.DialogWindowProvider
import coil3.compose.AsyncImage
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import sn.assurplus.app.BuildConfig
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.Appearance
import sn.assurplus.app.app.Documents
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.app.Route
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.l10n.LanguageSettings
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.security.BiometricKind
import sn.assurplus.app.core.upload.ImageCompressor
import sn.assurplus.app.core.upload.UploadFile
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.tenant.Tenant

private fun wrapError(error: Exception): APIError {
    if (error is CancellationException) throw error
    return APIError.wrap(error)
}

class ProfileViewModel(private val env: AppEnvironment) {
    var legal by mutableStateOf<List<LegalDocument>>(emptyList())
        private set
    var isUploadingPhoto by mutableStateOf(false)
        private set
    var deletionStatus by mutableStateOf<ServerStatus?>(null)
        private set
    var message by mutableStateOf<Message?>(null)

    val user: Me? get() = env.session.user

    suspend fun loadLegal() {
        if (legal.isEmpty()) legal = try {
            env.api.legalDocuments()
        } catch (e: Exception) {
            wrapError(e)
            emptyList()
        }
    }

    suspend fun setPhoto(data: ByteArray) {
        val jpeg = withContext(Dispatchers.Default) { ImageCompressor.jpeg(data) } ?: return
        isUploadingPhoto = true
        try {
            val uploadId = env.uploader.upload(UploadFile(jpeg, "photo.jpg", "image/jpeg"), "profile_photo") { }
            env.session.updateUser(env.api.setPhoto(uploadId))
            message = Message(Message.Level.success, t("Photo mise à jour. Elle apparaîtra sur votre carte après validation."))
        } catch (e: Exception) {
            message = Message(Message.Level.error, wrapError(e).userMessage)
        } finally {
            isUploadingPhoto = false
        }
    }

    suspend fun setBiometricLock(enabled: Boolean) {
        if (enabled) {
            if (env.biometrics.availableKind == BiometricKind.none && !env.isMock) {
                message = Message(Message.Level.warning, t("Aucune biométrie configurée sur cet appareil."))
                return
            }
            if (!env.biometrics.authenticate(t("Activer le déverrouillage biométrique"))) return
        }
        env.settings.updateBiometricLock(enabled)
    }

    suspend fun requestDeletion(reason: String) {
        try {
            deletionStatus = env.api.requestAccountDeletion(reason.ifEmpty { null })
        } catch (e: Exception) {
            message = Message(Message.Level.error, wrapError(e).userMessage)
        }
    }
}

private enum class ProfileSheet { contact, password, notifications }

@Composable
fun ProfileScreen() {
    val env = LocalEnv.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val model = remember { ProfileViewModel(env) }
    var sheet by remember { mutableStateOf<ProfileSheet?>(null) }
    var confirmLogout by remember { mutableStateOf(false) }
    var confirmDeletion by remember { mutableStateOf(false) }
    var deletionReason by remember { mutableStateOf("") }

    LaunchedEffect(Unit) { model.loadLegal() }

    val photoPicker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri: Uri? ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val data = withContext(Dispatchers.IO) { runCatching { context.contentResolver.openInputStream(uri)?.use { it.readBytes() } }.getOrNull() }
            if (data != null) model.setPhoto(data)
        }
    }
    val openUrl: (String) -> Unit = { Documents.openUrl(context, it) }

    Screen(t("Profil")) {
        model.user?.let { user ->
            ProfileHeader(user, model.isUploadingPhoto, env.session.can(Permission.profileEdit)) {
                photoPicker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
            }
        }
        model.message?.let { MessageBanner(it) }

        FormSection(header = t("Mon compte")) {
            val canEdit = env.session.can(Permission.profileEdit)
            FormRow(
                t("Coordonnées"), symbol = "person.text.rectangle", divider = false,
                modifier = Modifier.alpha(if (canEdit) 1f else 0.4f),
                onClick = if (canEdit) ({ sheet = ProfileSheet.contact }) else null,
            )
            if (env.session.can(Permission.familyView)) {
                FormRow(t("Ma famille"), symbol = "person.3", chevron = true, onClick = { env.router.push(Route.Family) })
            }
            if (env.session.can(Permission.paymentsView)) {
                FormRow(t("Paiements"), symbol = "creditcard", chevron = true, onClick = { env.router.push(Route.Payments) })
            }
            if (env.session.can(Permission.vaultView)) {
                FormRow(t("Coffre santé"), symbol = "lock.doc", chevron = true, onClick = { env.router.push(Route.Vault) })
            }
        }

        FormSection(header = t("Sécurité")) {
            val kind = env.biometrics.availableKind
            FormRow(t("Verrouiller avec %@", kind.label), symbol = kind.symbol, divider = false) {
                IOSSwitch(
                    env.settings.biometricLockEnabled,
                    { value -> scope.launch { model.setBiometricLock(value) } },
                    modifier = Modifier.padding(start = DS.Spacing.s),
                    tag = "profile.biometrics",
                )
            }
            FormRow(t("Changer le mot de passe"), symbol = "key", onClick = { sheet = ProfileSheet.password })
        }

        FormSection(header = t("Préférences")) {
            FormRow(t("Notifications"), symbol = "bell.badge", divider = false, onClick = { sheet = ProfileSheet.notifications })
            MenuPickerRow(
                title = t("Langue"), symbol = "globe",
                options = env.language.supported, selected = env.language.code,
                label = { LanguageSettings.nativeName(it) },
                onSelect = { code ->
                    env.language.select(code)
                    // Server-side preference: SMS, e-mails and push content in the same language.
                    scope.launch {
                        runCatching { env.api.updateMe(MeUpdate(preferredLanguage = code)) }.getOrNull()?.let(env.session::updateUser)
                    }
                },
                tag = "profile.language",
            )
            MenuPickerRow(
                title = t("Apparence"), symbol = "circle.lefthalf.filled",
                options = Appearance.entries, selected = env.settings.appearance,
                label = { it.title },
                onSelect = { env.settings.updateAppearance(it) },
                tag = "profile.appearance",
            )
        }

        FormSection(header = t("Aide et informations")) {
            var first = true
            fun divider(): Boolean = !first.also { first = false }
            model.legal.forEach { document ->
                FormRow(t("%@ (%@)", document.title, document.version), symbol = "doc.plaintext", divider = divider(), onClick = { openUrl(document.url) })
            }
            if (model.legal.isEmpty()) {
                Tenant.current.termsUri?.let { url ->
                    FormRow(t("Conditions générales d'utilisation"), symbol = "doc.plaintext", divider = divider(), onClick = { openUrl(url) })
                }
                Tenant.current.privacyUri?.let { url ->
                    FormRow(t("Politique de confidentialité"), symbol = "hand.raised", divider = divider(), onClick = { openUrl(url) })
                }
            }
            Tenant.current.supportPhoneUri?.let { url ->
                FormRow(t("Appeler le support"), symbol = "phone", divider = divider(), onClick = { openUrl(url) })
            }
            Tenant.current.supportEmailUri?.let { url ->
                FormRow(t("Écrire au support"), symbol = "envelope", divider = divider(), onClick = { openUrl(url) })
            }
        }

        FormSection(
            footer = "${Tenant.current.displayName} ${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE}) · Copyright © ${Tenant.current.copyrightHolder}",
        ) {
            FormRow(t("Se déconnecter"), divider = false, onClick = { confirmLogout = true }, tag = "profile.logout")
            val status = model.deletionStatus
            if (status != null) {
                Column {
                    Hairline(Modifier.padding(horizontal = DS.Spacing.l))
                    Box(Modifier.fillMaxWidth().heightIn(min = 60.dp).padding(horizontal = DS.Spacing.l), contentAlignment = Alignment.CenterStart) {
                        StatusBadge(status)
                    }
                }
            } else {
                FormRow(t("Supprimer mon compte"), titleColor = DS.Palette.danger, onClick = { confirmDeletion = true })
            }
        }
    }

    ActionSheet(
        visible = confirmLogout,
        title = t("Se déconnecter ?"),
        message = t("Les données enregistrées sur cet appareil seront effacées."),
        actions = listOf(SheetAction(t("Se déconnecter"), destructive = true, tag = "profile.logout.confirm") { scope.launch { env.session.logout() } }),
        onDismiss = { confirmLogout = false },
    )

    if (confirmDeletion) {
        val dismiss = { confirmDeletion = false }
        AlertDialog(
            onDismissRequest = dismiss,
            containerColor = DS.Palette.surface,
            title = { Text(t("Supprimer mon compte"), style = DS.Typography.headline, color = DS.Palette.textPrimary) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                    Text(
                        t("Votre demande sera traitée selon la réglementation. Les données liées à un contrat en cours peuvent être conservées pendant la durée légale."),
                        style = DS.Typography.callout, color = DS.Palette.textSecondary,
                    )
                    Row(
                        Modifier.fillMaxWidth().outlined(DS.Radius.s).padding(horizontal = DS.Spacing.m),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        InputText(deletionReason, { deletionReason = it }, t("Motif (facultatif)"))
                    }
                }
            },
            confirmButton = {
                TextButton({
                    dismiss()
                    scope.launch { model.requestDeletion(deletionReason) }
                }) { Text(t("Demander la suppression"), style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.danger) }
            },
            dismissButton = {
                TextButton(dismiss) { Text(t("Annuler"), style = DS.Typography.body, color = DS.Palette.accent) }
            },
        )
    }

    sheet?.let { current ->
        val dismiss = { sheet = null }
        ProfileSheetDialog(onDismiss = dismiss) {
            when (current) {
                ProfileSheet.contact -> ContactForm(dismiss)
                ProfileSheet.password -> PasswordChangeForm(dismiss)
                ProfileSheet.notifications -> NotificationPreferencesForm(dismiss)
            }
        }
    }
}

@Composable
private fun ProfileHeader(user: Me, isUploading: Boolean, canEditPhoto: Boolean, onPickPhoto: () -> Unit) {
    val photoDescription = t("Changer la photo de profil")
    Row(
        Modifier.fillMaxWidth().padding(vertical = DS.Spacing.s),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(DS.Spacing.l),
    ) {
        Box(Modifier.size(72.dp)) {
            Box(
                Modifier
                    .size(72.dp)
                    .clip(CircleShape)
                    .clickable(enabled = canEditPhoto, role = Role.Button, onClick = onPickPhoto)
                    .semantics { contentDescription = photoDescription },
            ) {
                InitialsAvatar(user.fullName, size = 72.dp)
                user.photoURL?.let { url ->
                    AsyncImage(url, null, contentScale = ContentScale.Crop, modifier = Modifier.size(72.dp).clip(CircleShape))
                }
            }
            if (canEditPhoto) {
                // SF "camera.circle.fill" / "arrow.up.circle.fill": white glyph in an accent disc, on a surface ring.
                Box(
                    Modifier.align(Alignment.BottomEnd).size(26.dp).background(DS.Palette.surface, CircleShape).padding(2.dp)
                        .background(DS.Palette.accent, CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(sym(if (isUploading) "arrow.up" else "camera.fill"), null, tint = DS.Palette.surface, modifier = Modifier.size(13.dp))
                }
            }
        }
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.xxs)) {
            Text(user.fullName, style = DS.Typography.title3.copy(fontWeight = FontWeight.Bold), color = DS.Palette.textPrimary)
            Text(PhoneNumber.display(user.phone), style = DS.Typography.callout, color = DS.Palette.textSecondary)
            user.memberNumber?.let { Text(t("N° %@", it), style = DS.Typography.caption, color = DS.Palette.textSecondary) }
            Text(
                if (user.role == AccountRole.principal) t("Assuré principal") else t("Ayant droit"),
                style = DS.Typography.caption.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.accent,
            )
        }
    }
}

/** iOS menu-style `Picker` row: current value and an up/down chevron, options in a pop-up menu with a checkmark. */
@Composable
private fun <T> MenuPickerRow(
    title: String,
    symbol: String,
    options: List<T>,
    selected: T,
    label: (T) -> String,
    onSelect: (T) -> Unit,
    tag: String,
) {
    var expanded by remember { mutableStateOf(false) }
    Box(Modifier.fillMaxWidth()) {
        FormRow(
            title, symbol = symbol, value = label(selected), onClick = { expanded = true }, tag = tag,
            trailing = {
                Icon(sym("chevron.up.chevron.down"), null, tint = DS.Palette.textSecondary, modifier = Modifier.padding(start = DS.Spacing.xs).size(18.dp))
            },
        )
        Box(Modifier.align(Alignment.BottomEnd).padding(end = DS.Spacing.l)) {
            DropdownMenu(expanded, { expanded = false }, containerColor = DS.Palette.surface) {
                options.forEach { option ->
                    DropdownMenuItem(
                        text = { Text(label(option), style = DS.Typography.body, color = DS.Palette.textPrimary) },
                        onClick = {
                            expanded = false
                            onSelect(option)
                        },
                        leadingIcon = {
                            if (option == selected) Icon(sym("checkmark"), null, tint = DS.Palette.textPrimary, modifier = Modifier.size(20.dp))
                            else Spacer(Modifier.size(20.dp))
                        },
                    )
                }
            }
        }
    }
}

// MARK: - Sheets

/** iOS page sheet over the tabs, in a full-screen dialog (the tab bar is drawn above the tab content). */
@Composable
private fun ProfileSheetDialog(onDismiss: () -> Unit, content: @Composable () -> Unit) {
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        // SheetContainer draws its own dimming.
        (LocalView.current.parent as? DialogWindowProvider)?.window?.setDimAmount(0f)
        SheetContainer(onDismiss) {
            Box(Modifier.fillMaxSize().imePadding()) { content() }
        }
    }
}

@Composable
private fun SheetScreen(
    title: String,
    cancelTitle: String,
    onCancel: () -> Unit,
    onSave: () -> Unit,
    saveEnabled: Boolean,
    content: @Composable ColumnScope.() -> Unit,
) {
    Screen(
        title, largeTitle = false,
        leading = { GlassTextButton(cancelTitle, onCancel) },
        actions = { GlassTextButton(t("Enregistrer"), onSave, enabled = saveEnabled, tint = DS.Palette.accent) },
        content = content,
    )
}

/** Text field as a row of an inset grouped form. */
@Composable
private fun FormTextField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    divider: Boolean = true,
    keyboardType: KeyboardType = KeyboardType.Text,
    secure: Boolean = false,
) {
    Column(Modifier.fillMaxWidth()) {
        if (divider) Hairline(Modifier.padding(horizontal = DS.Spacing.l))
        Row(Modifier.fillMaxWidth().heightIn(min = 52.dp).padding(horizontal = DS.Spacing.l), verticalAlignment = Alignment.CenterVertically) {
            InputText(value, onValueChange, placeholder, keyboardType = keyboardType, secure = secure)
        }
    }
}

@Composable
private fun FormText(text: String, color: androidx.compose.ui.graphics.Color, divider: Boolean = true) {
    Column(Modifier.fillMaxWidth()) {
        if (divider) Hairline(Modifier.padding(horizontal = DS.Spacing.l))
        Text(text, style = DS.Typography.body, color = color, modifier = Modifier.padding(horizontal = DS.Spacing.l, vertical = DS.Spacing.m))
    }
}

@Composable
private fun ContactForm(dismiss: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    val user = env.session.user
    var email by remember { mutableStateOf(user?.email.orEmpty()) }
    var address by remember { mutableStateOf(user?.address.orEmpty()) }
    var city by remember { mutableStateOf(user?.city.orEmpty()) }
    var error by remember { mutableStateOf<APIError?>(null) }
    var isSaving by remember { mutableStateOf(false) }

    SheetScreen(
        t("Coordonnées"), t("Annuler"), dismiss,
        onSave = {
            scope.launch {
                isSaving = true
                try {
                    env.session.updateUser(env.api.updateMe(MeUpdate(email = email, address = address, city = city)))
                    dismiss()
                } catch (e: Exception) {
                    error = wrapError(e)
                } finally {
                    isSaving = false
                }
            }
        },
        saveEnabled = !isSaving,
    ) {
        FormSection(footer = t("Pour changer de numéro, contactez le support.")) {
            FormRow(t("Téléphone"), value = PhoneNumber.display(user?.phone.orEmpty()), divider = false)
        }
        FormSection {
            FormTextField(email, { email = it }, t("E-mail"), divider = false, keyboardType = KeyboardType.Email)
            FormTextField(address, { address = it }, t("Adresse"))
            FormTextField(city, { city = it }, t("Ville"))
            error?.let { FormText(it.fieldErrors.values.firstOrNull() ?: it.userMessage, DS.Palette.danger) }
        }
    }
}

@Composable
private fun PasswordChangeForm(dismiss: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    var current by remember { mutableStateOf("") }
    var new by remember { mutableStateOf("") }
    var confirmation by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<APIError?>(null) }
    var done by remember { mutableStateOf(false) }

    SheetScreen(
        t("Mot de passe"), t("Fermer"), dismiss,
        onSave = {
            scope.launch {
                try {
                    env.api.changePassword(PasswordChangeRequest(current, new))
                    done = true
                    error = null
                } catch (e: Exception) {
                    error = wrapError(e)
                }
            }
        },
        saveEnabled = current.isNotEmpty() && new.length >= 8 && new == confirmation,
    ) {
        FormSection {
            FormTextField(current, { current = it }, t("Mot de passe actuel"), divider = false, secure = true)
            FormTextField(new, { new = it }, t("Nouveau mot de passe (8 caractères min.)"), secure = true)
            FormTextField(confirmation, { confirmation = it }, t("Confirmation"), secure = true)
            if (confirmation.isNotEmpty() && confirmation != new) FormText(t("Les mots de passe ne correspondent pas."), DS.Palette.danger)
            error?.let { FormText(it.fieldErrors.values.firstOrNull() ?: it.userMessage, DS.Palette.danger) }
            if (done) FormText(t("Mot de passe modifié."), DS.Palette.success)
        }
    }
}

@Composable
private fun NotificationPreferencesForm(dismiss: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    var preferences by remember { mutableStateOf<NotificationPreferences?>(null) }
    var error by remember { mutableStateOf<APIError?>(null) }

    LaunchedEffect(Unit) {
        try {
            preferences = env.api.notificationPreferences()
        } catch (e: Exception) {
            error = wrapError(e)
        }
    }

    SheetScreen(
        t("Notifications"), t("Annuler"), dismiss,
        onSave = {
            val prefs = preferences ?: return@SheetScreen
            scope.launch {
                try {
                    env.api.updateNotificationPreferences(prefs)
                    dismiss()
                } catch (e: Exception) {
                    error = wrapError(e)
                }
            }
        },
        saveEnabled = preferences != null,
    ) {
        val prefs = preferences
        when {
            prefs != null -> prefs.categories.forEachIndexed { index, category ->
                fun update(change: (NotificationPreferences.Channel) -> NotificationPreferences.Channel) {
                    preferences = prefs.copy(categories = prefs.categories.toMutableList().also { it[index] = change(category) })
                }
                FormSection(header = category.label) {
                    FormRow(t("Notification push"), divider = false) {
                        IOSSwitch(category.push, { value -> update { it.copy(push = value) } }, Modifier.padding(start = DS.Spacing.s))
                    }
                    FormRow(t("SMS")) {
                        IOSSwitch(category.sms, { value -> update { it.copy(sms = value) } }, Modifier.padding(start = DS.Spacing.s))
                    }
                    FormRow(t("E-mail")) {
                        IOSSwitch(category.email, { value -> update { it.copy(email = value) } }, Modifier.padding(start = DS.Spacing.s))
                    }
                }
            }
            error != null -> FormSection { FormText(error?.userMessage.orEmpty(), DS.Palette.textPrimary, divider = false) }
            else -> FormSection {
                Box(Modifier.fillMaxWidth().heightIn(min = 52.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = DS.Palette.textSecondary, strokeWidth = 2.dp, modifier = Modifier.size(22.dp))
                }
            }
        }
    }
}
