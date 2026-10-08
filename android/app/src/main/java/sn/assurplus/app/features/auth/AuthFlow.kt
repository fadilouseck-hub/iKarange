package sn.assurplus.app.features.auth

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.tween
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDefaults
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextRange
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.TextFieldValue
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import sn.assurplus.app.app.AuthSession
import sn.assurplus.app.app.BrandMark
import sn.assurplus.app.app.Documents
import sn.assurplus.app.app.LocalEnv
import sn.assurplus.app.core.format.DateText
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.l10n.t
import sn.assurplus.app.core.model.*
import sn.assurplus.app.core.network.APIError
import sn.assurplus.app.core.network.AssurApi
import sn.assurplus.app.designsystem.*
import sn.assurplus.app.tenant.Tenant
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import kotlin.math.ceil

// MARK: - View models (iOS AuthViewModels.swift)

/** Wraps any failure as an [APIError], letting coroutine cancellation through. */
private fun wrapError(error: Exception): APIError {
    if (error is CancellationException) throw error
    return APIError.wrap(error)
}

/** Sends and verifies one-time codes. Reused by login, sign-up and password reset. */
class OTPViewModel(val purpose: OTPPurpose, private val api: AssurApi) {
    var phone by mutableStateOf("")
    var code by mutableStateOf("")
        private set
    var challenge by mutableStateOf<OTPChallenge?>(null)
        private set
    var resendAvailableAt by mutableStateOf<Instant?>(null)
        private set
    var isSending by mutableStateOf(false)
        private set
    var isVerifying by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    val phoneIsValid: Boolean get() = PhoneNumber.isValid(phone)
    val codeIsComplete: Boolean get() = code.length == 6

    suspend fun send(): Boolean {
        val e164 = PhoneNumber.e164(phone)
        if (e164 == null) {
            error = APIError.Server(422, "invalid_phone", t("Numéro de téléphone invalide."), mapOf("phone" to t("Numéro sénégalais à 9 chiffres attendu.")))
            return false
        }
        isSending = true
        try {
            val challenge = api.sendOTP(e164, purpose)
            this.challenge = challenge
            resendAvailableAt = Instant.now().plusSeconds(challenge.resendAfter.toLong())
            code = ""
            error = null
            return true
        } catch (e: Exception) {
            error = wrapError(e)
            return false
        } finally {
            isSending = false
        }
    }

    /** Returns the verification token on success. */
    suspend fun verify(): String? {
        val challenge = challenge ?: return null
        if (!codeIsComplete) return null
        isVerifying = true
        try {
            val verification = api.verifyOTP(challenge.otpRequestId, code)
            error = null
            return verification.verificationToken
        } catch (e: Exception) {
            error = wrapError(e)
            code = ""
            return null
        } finally {
            isVerifying = false
        }
    }

    fun updateCode(value: String) {
        code = value.filter { it.isDigit() }.take(6)
    }
}

class LoginViewModel(private val api: AssurApi, private val session: AuthSession, val identifierKind: Tenant.LoginIdentifier = Tenant.LoginIdentifier.phone) {
    enum class Mode {
        password, otp;

        val label: String get() = if (this == password) t("Mot de passe") else t("Code SMS")
    }

    val otp = OTPViewModel(OTPPurpose.login, api)
    var mode by mutableStateOf(Mode.password)
    var username by mutableStateOf("")
    var password by mutableStateOf("")
    var showOTPEntry by mutableStateOf(false)
    var isLoading by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    val canSubmit: Boolean
        get() {
            if (identifierKind == Tenant.LoginIdentifier.username) {
                return username.trim().isNotEmpty() && password.isNotEmpty() && !isLoading
            }
            return otp.phoneIsValid && (mode == Mode.otp || password.isNotEmpty()) && !isLoading
        }

    suspend fun submit() {
        if (identifierKind == Tenant.LoginIdentifier.username) {
            isLoading = true
            try {
                session.didAuthenticate(api.login(LoginRequest(identifier = username.trim(), password = password)))
            } catch (e: Exception) {
                error = wrapError(e)
            } finally {
                isLoading = false
            }
            return
        }
        val phone = PhoneNumber.e164(otp.phone)
        if (phone == null) {
            error = APIError.Server(422, "invalid_phone", t("Numéro de téléphone invalide."), emptyMap())
            return
        }
        when (mode) {
            Mode.password -> {
                isLoading = true
                try {
                    session.didAuthenticate(api.login(LoginRequest(phone = phone, password = password)))
                } catch (e: Exception) {
                    error = wrapError(e)
                } finally {
                    isLoading = false
                }
            }
            Mode.otp -> {
                isLoading = true
                val sent = otp.send()
                isLoading = false
                if (sent) showOTPEntry = true else error = otp.error
            }
        }
    }

    suspend fun completeOTP() {
        val token = otp.verify() ?: return
        val phone = PhoneNumber.e164(otp.phone) ?: return
        try {
            session.didAuthenticate(api.login(LoginRequest(phone = phone, verificationToken = token)))
        } catch (e: Exception) {
            otp.error = wrapError(e)
        }
    }
}

class RegisterViewModel(private val api: AssurApi, private val session: AuthSession) {
    enum class Step { phone, otp, details }

    val otp = OTPViewModel(OTPPurpose.register, api)
    var step by mutableStateOf(Step.phone)
        private set
    private var verificationToken: String? = null

    var firstName by mutableStateOf("")
    var lastName by mutableStateOf("")
    var birthDate by mutableStateOf<LocalDate>(LocalDate.now().minusYears(30))
    var gender by mutableStateOf(Gender.female)
    var email by mutableStateOf("")
    var city by mutableStateOf("")
    var usePassword by mutableStateOf(true)
    var password by mutableStateOf("")
    var passwordConfirmation by mutableStateOf("")
    var acceptedCGU by mutableStateOf(false)
    var cgu by mutableStateOf<LegalDocument?>(null)
        private set
    var isLoading by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    val passwordError: String?
        get() {
            if (!usePassword || password.isEmpty()) return null
            if (password.length < 8) return t("8 caractères minimum.")
            if (passwordConfirmation.isNotEmpty() && password != passwordConfirmation) return t("Les mots de passe ne correspondent pas.")
            return null
        }

    val emailError: String?
        get() {
            if (email.isEmpty()) return null
            return if (email.contains("@") && email.contains(".")) null else t("Adresse e-mail invalide.")
        }

    val canSubmitDetails: Boolean
        get() = firstName.trim().isNotEmpty() && lastName.trim().isNotEmpty() &&
            acceptedCGU && cgu != null && emailError == null &&
            (!usePassword || (password.length >= 8 && password == passwordConfirmation)) &&
            !isLoading

    suspend fun sendCode() {
        if (otp.send()) step = Step.otp
    }

    suspend fun verifyCode() {
        val token = otp.verify() ?: return
        verificationToken = token
        step = Step.details
        loadCGU()
    }

    suspend fun loadCGU() {
        if (cgu != null) return
        cgu = try {
            api.legalDocuments().firstOrNull { it.id == "cgu" }
        } catch (e: Exception) {
            wrapError(e)
            null
        }
    }

    suspend fun submit() {
        val token = verificationToken ?: return
        val cgu = cgu ?: return
        isLoading = true
        val request = RegisterRequest(
            verificationToken = token,
            firstName = firstName.trim(),
            lastName = lastName.trim(),
            birthDate = LocalDay.of(birthDate), gender = gender,
            email = email.ifEmpty { null }, address = null, city = city.ifEmpty { null },
            password = if (usePassword) password else null, cguVersion = cgu.version,
        )
        try {
            session.didAuthenticate(api.register(request))
        } catch (e: Exception) {
            error = wrapError(e)
        } finally {
            isLoading = false
        }
    }
}

class ForgotPasswordViewModel(private val api: AssurApi, private val session: AuthSession) {
    enum class Step { phone, otp, newPassword }

    val otp = OTPViewModel(OTPPurpose.resetPassword, api)
    var step by mutableStateOf(Step.phone)
        private set
    private var verificationToken: String? = null
    var password by mutableStateOf("")
    var confirmation by mutableStateOf("")
    var isLoading by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    val canSave: Boolean get() = password.length >= 8 && password == confirmation && !isLoading

    suspend fun sendCode() {
        if (otp.send()) step = Step.otp
    }

    suspend fun verifyCode() {
        val token = otp.verify() ?: return
        verificationToken = token
        step = Step.newPassword
    }

    suspend fun save() {
        val token = verificationToken ?: return
        val phone = PhoneNumber.e164(otp.phone) ?: return
        isLoading = true
        try {
            api.resetPassword(PasswordResetRequest(token, password))
            session.didAuthenticate(api.login(LoginRequest(phone = phone, password = password)))
        } catch (e: Exception) {
            error = wrapError(e)
        } finally {
            isLoading = false
        }
    }
}

// MARK: - Navigation (iOS AuthFlowView: welcome → login / register / forgot, login → OTP)

private sealed class AuthRoute {
    class Login(val model: LoginViewModel) : AuthRoute()
    class LoginOTP(val model: LoginViewModel) : AuthRoute()
    class Register(val model: RegisterViewModel) : AuthRoute()
    class Forgot(val model: ForgotPasswordViewModel) : AuthRoute()
}

@Composable
fun AuthFlow() {
    val env = LocalEnv.current
    val stack = remember { mutableStateListOf<AuthRoute>() }
    val pop: () -> Unit = { stack.removeLastOrNull() }

    AnimatedContent(
        targetState = stack.toList(),
        transitionSpec = {
            val forward = targetState.size >= initialState.size
            if (forward) slideInHorizontally(tween(320)) { it } togetherWith slideOutHorizontally(tween(320)) { -it / 3 }
            else slideInHorizontally(tween(320)) { -it / 3 } togetherWith slideOutHorizontally(tween(320)) { it }
        },
        label = "auth",
    ) { routes ->
        when (val top = routes.lastOrNull()) {
            null -> WelcomeView(
                onLogin = { stack.add(AuthRoute.Login(LoginViewModel(env.api, env.session, env.loginIdentifier))) },
                onRegister = { stack.add(AuthRoute.Register(RegisterViewModel(env.api, env.session))) },
            )
            is AuthRoute.Login -> LoginView(
                model = top.model,
                onBack = pop,
                onForgot = { stack.add(AuthRoute.Forgot(ForgotPasswordViewModel(env.api, env.session))) },
                onShowOTP = { stack.add(AuthRoute.LoginOTP(top.model)) },
            )
            is AuthRoute.LoginOTP -> {
                val scope = rememberCoroutineScope()
                Screen("", largeTitle = false, onBack = pop, modifier = Modifier.imePadding(), contentPadding = authPadding) {
                    OTPEntry(top.model.otp) { scope.launch { top.model.completeOTP() } }
                }
            }
            is AuthRoute.Register -> RegisterView(top.model, onBack = pop)
            is AuthRoute.Forgot -> ForgotPasswordView(top.model, onBack = pop)
        }
    }
}

/** iOS `.padding(DS.Spacing.xl)` around the scroll content of auth screens. */
private val authPadding = PaddingValues(start = DS.Spacing.xl, end = DS.Spacing.xl, top = DS.Spacing.xl, bottom = DS.Spacing.xl)

// MARK: - Welcome

@Composable
private fun WelcomeView(onLogin: () -> Unit, onRegister: () -> Unit) {
    val env = LocalEnv.current
    Column(
        Modifier.fillMaxSize().screenBackground().systemBarsPadding().padding(DS.Spacing.xl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(DS.Spacing.xl),
    ) {
        Spacer(Modifier.weight(1f))
        BrandMark(size = 72.dp)
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
            Text(t("Votre santé, simplement assurée"), style = DS.Typography.title, color = DS.Palette.textPrimary, textAlign = TextAlign.Center)
            Text(
                t("Carte tiers-payant, remboursements et réseau de soins dans votre poche."),
                style = DS.Typography.callout, color = DS.Palette.textSecondary, textAlign = TextAlign.Center,
            )
        }
        Card {
            Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
                FeatureLine("qrcode", t("Présentez votre carte digitale chez le prestataire"))
                FeatureLine("camera.viewfinder", t("Déclarez un sinistre en photographiant la facture"))
                FeatureLine("map", t("Trouvez une pharmacie ou une clinique conventionnée"))
            }
        }
        Spacer(Modifier.weight(1f))
        Column(verticalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
            PrimaryButton(t("Se connecter"), onLogin, tag = "welcome.login")
            if (env.features.selfRegistration) {
                SecondaryButton(t("Créer un compte"), onRegister, tag = "welcome.register")
            }
        }
        Text("Copyright © ${Tenant.current.copyrightHolder}", style = DS.Typography.caption, color = DS.Palette.textSecondary)
    }
}

@Composable
private fun FeatureLine(symbol: String, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.m)) {
        Box(Modifier.width(28.dp), contentAlignment = Alignment.Center) {
            Icon(sym(symbol), null, tint = DS.Palette.accent, modifier = Modifier.size(22.dp))
        }
        Text(text, style = DS.Typography.callout, color = DS.Palette.textPrimary)
    }
}

// MARK: - Phone field

/** `+221` prefix and formatted national number (reformatted on every change, cursor kept at the end). */
@Composable
fun PhoneField(phone: String, onPhoneChange: (String) -> Unit, error: String?) {
    var field by remember { mutableStateOf(TextFieldValue(phone, TextRange(phone.length))) }
    val description = t("Numéro de téléphone, indicatif plus 221")
    LabeledField(t("Numéro de téléphone"), error = error) {
        Text("🇸🇳 +221", style = DS.Typography.body, color = DS.Palette.textSecondary, modifier = Modifier.clearAndSetSemantics {})
        Spacer(Modifier.width(DS.Spacing.s))
        BasicTextField(
            value = field,
            onValueChange = { value ->
                val formatted = PhoneNumber.formatInput(value.text)
                field = if (formatted == value.text) value else TextFieldValue(formatted, TextRange(formatted.length))
                onPhoneChange(formatted)
            },
            singleLine = true,
            textStyle = DS.Typography.body.copy(color = DS.Palette.textPrimary),
            cursorBrush = SolidColor(DS.Palette.accent),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            modifier = Modifier
                .weight(1f)
                .padding(vertical = DS.Spacing.m)
                .semantics { contentDescription = description }
                .testTag("auth.phone"),
            decorationBox = { inner ->
                Box {
                    if (field.text.isEmpty()) Text("77 123 45 67", style = DS.Typography.body, color = DS.Palette.textSecondary.copy(alpha = 0.6f))
                    inner()
                }
            },
        )
    }
}

// MARK: - Login

@Composable
private fun LoginView(model: LoginViewModel, onBack: () -> Unit, onForgot: () -> Unit, onShowOTP: () -> Unit) {
    val env = LocalEnv.current
    val scope = rememberCoroutineScope()
    LaunchedEffect(model.showOTPEntry) {
        if (model.showOTPEntry) {
            model.showOTPEntry = false
            onShowOTP()
        }
    }
    Screen(t("Connexion"), largeTitle = false, onBack = onBack, modifier = Modifier.imePadding(), contentPadding = authPadding) {
        Text(t("Heureux de vous revoir"), style = DS.Typography.title, color = DS.Palette.textPrimary)
        if (env.features.otpLogin) {
            SegmentedPicker(LoginViewModel.Mode.entries, model.mode, { it.label }, { model.mode = it })
        }

        if (model.identifierKind == Tenant.LoginIdentifier.username) {
            LabeledField(t("Identifiant")) {
                InputText(model.username, { model.username = it }, t("Votre identifiant"), tag = "auth.username")
            }
        } else {
            PhoneField(model.otp.phone, { model.otp.phone = it }, model.error?.fieldErrors?.get("phone"))
        }

        if (model.mode == LoginViewModel.Mode.password) {
            LabeledField(t("Mot de passe")) {
                InputText(model.password, { model.password = it }, t("Votre mot de passe"), secure = true, tag = "auth.password")
            }
            if (env.features.passwordReset) {
                TextLink(t("Mot de passe oublié ?"), onForgot)
            } else {
                Text(t("Mot de passe oublié ? Contactez votre gestionnaire."), style = DS.Typography.footnote, color = DS.Palette.textSecondary)
            }
        } else {
            Text(t("Nous vous enverrons un code à 6 chiffres par SMS."), style = DS.Typography.callout, color = DS.Palette.textSecondary)
        }

        model.error?.takeIf { it.fieldErrors.isEmpty() }?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }

        PrimaryButton(
            if (model.mode == LoginViewModel.Mode.password) t("Se connecter") else t("Recevoir le code"),
            { scope.launch { model.submit() } },
            enabled = model.canSubmit, loading = model.isLoading, tag = "auth.submit",
        )
    }
}

// MARK: - OTP entry

@Composable
private fun ColumnScope.OTPEntry(model: OTPViewModel, onComplete: () -> Unit) {
    val scope = rememberCoroutineScope()
    val focus = remember { FocusRequester() }
    var now by remember { mutableStateOf(Instant.now()) }
    LaunchedEffect(Unit) {
        runCatching { focus.requestFocus() }
        while (true) {
            delay(1000)
            now = Instant.now()
        }
    }
    LaunchedEffect(model.code) {
        if (model.code.length == 6) onComplete()
    }

    Text(t("Vérification"), style = DS.Typography.title, color = DS.Palette.textPrimary)
    Text(
        t("Saisissez le code envoyé au %@.", model.challenge?.maskedPhone ?: PhoneNumber.display(model.phone)),
        style = DS.Typography.callout, color = DS.Palette.textSecondary,
    )

    val codeDescription = t("Code de vérification à 6 chiffres")
    Box(Modifier.fillMaxWidth().clickable(indication = null, interactionSource = null) { runCatching { focus.requestFocus() } }) {
        CodeBoxes(model.code)
        BasicTextField(
            value = model.code,
            onValueChange = { model.updateCode(it) },
            singleLine = true,
            textStyle = TextStyle(color = Color.Transparent),
            cursorBrush = SolidColor(Color.Transparent),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
            modifier = Modifier
                .matchParentSize()
                .focusRequester(focus)
                .semantics { contentDescription = codeDescription }
                .testTag("auth.otp"),
        )
    }

    model.error?.let {
        Text(it.fieldErrors["code"] ?: it.userMessage, style = DS.Typography.callout, color = DS.Palette.danger)
    }

    PrimaryButton(
        t("Valider"), onComplete,
        enabled = model.codeIsComplete && !model.isVerifying, loading = model.isVerifying, tag = "auth.otp.submit",
    )

    val remaining = model.resendAvailableAt?.let { ceil((it.toEpochMilli() - now.toEpochMilli()) / 1000.0).toInt() } ?: 0
    TextLink(
        if (remaining > 0) t("Renvoyer le code (%lld s)", remaining) else t("Renvoyer le code"),
        { scope.launch { model.send() } },
        enabled = remaining <= 0 && !model.isSending,
        modifier = Modifier.align(Alignment.CenterHorizontally),
    )
}

@Composable
private fun CodeBoxes(code: String) {
    Row(Modifier.fillMaxWidth().clearAndSetSemantics {}, horizontalArrangement = Arrangement.spacedBy(DS.Spacing.s)) {
        repeat(6) { index ->
            val digit = code.getOrNull(index)?.toString().orEmpty()
            val active = index == code.length
            val shape = RoundedCornerShape(DS.Radius.m)
            Box(
                Modifier
                    .weight(1f)
                    .heightIn(min = 56.dp)
                    .background(DS.Palette.surface, shape)
                    .border(if (active) 2.dp else 1.dp, if (active) DS.Palette.accent else DS.Palette.border, shape),
                contentAlignment = Alignment.Center,
            ) {
                Text(digit, style = DS.Typography.title2.copy(fontWeight = FontWeight.SemiBold, fontFeatureSettings = "tnum"), color = DS.Palette.textPrimary)
            }
        }
    }
}

// MARK: - Register

@Composable
private fun RegisterView(model: RegisterViewModel, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    Screen(t("Créer un compte"), largeTitle = false, onBack = onBack, modifier = Modifier.imePadding(), contentPadding = authPadding) {
        when (model.step) {
            RegisterViewModel.Step.phone -> {
                StepProgress(1, 3, t("Votre numéro"))
                Text(
                    t("Votre numéro de téléphone servira d'identifiant. Nous allons le vérifier par SMS."),
                    style = DS.Typography.callout, color = DS.Palette.textSecondary,
                )
                PhoneField(model.otp.phone, { model.otp.phone = it }, model.otp.error?.fieldErrors?.get("phone"))
                model.otp.error?.takeIf { it.fieldErrors.isEmpty() }?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
                PrimaryButton(
                    t("Recevoir le code"), { scope.launch { model.sendCode() } },
                    enabled = model.otp.phoneIsValid && !model.otp.isSending, loading = model.otp.isSending, tag = "register.sendCode",
                )
            }
            RegisterViewModel.Step.otp -> OTPEntry(model.otp) { scope.launch { model.verifyCode() } }
            RegisterViewModel.Step.details -> RegisterDetails(model)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RegisterDetails(model: RegisterViewModel) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    var showDatePicker by remember { mutableStateOf(false) }

    StepProgress(3, 3, t("Vos informations"))
    LabeledField(t("Prénom"), error = model.error?.fieldErrors?.get("firstName")) {
        InputText(model.firstName, { model.firstName = it }, t("Prénom"), tag = "register.firstName")
    }
    LabeledField(t("Nom"), error = model.error?.fieldErrors?.get("lastName")) {
        InputText(model.lastName, { model.lastName = it }, t("Nom"), tag = "register.lastName")
    }
    LabeledField(t("Date de naissance")) {
        Text(
            DateText.day(LocalDay.of(model.birthDate)),
            style = DS.Typography.body, color = DS.Palette.textPrimary,
            modifier = Modifier.weight(1f).clickable { showDatePicker = true }.padding(vertical = DS.Spacing.m).testTag("register.birthDate"),
        )
    }
    SegmentedPicker(Gender.entries, model.gender, { it.label }, { model.gender = it })
    LabeledField(t("E-mail (facultatif)"), error = model.emailError) {
        InputText(model.email, { model.email = it }, "nom@exemple.com", keyboardType = KeyboardType.Email)
    }
    LabeledField(t("Ville")) {
        InputText(model.city, { model.city = it }, "Dakar")
    }

    ToggleRow(t("Créer un mot de passe"), model.usePassword, { model.usePassword = it }, tag = "register.usePassword")
    if (model.usePassword) {
        LabeledField(t("Mot de passe (8 caractères min.)"), error = model.passwordError) {
            InputText(model.password, { model.password = it }, t("Mot de passe"), secure = true, tag = "register.password")
        }
        LabeledField(t("Confirmation")) {
            InputText(model.passwordConfirmation, { model.passwordConfirmation = it }, t("Confirmez"), secure = true, tag = "register.passwordConfirmation")
        }
    } else {
        Text(t("Vous vous connecterez avec un code reçu par SMS."), style = DS.Typography.footnote, color = DS.Palette.textSecondary)
    }

    ToggleRow(
        t("J'accepte les conditions générales d'utilisation"), model.acceptedCGU, { model.acceptedCGU = it }, tag = "register.cgu",
        subtitle = model.cgu?.let { cgu ->
            {
                TextLink(
                    t("Lire les CGU (version %@)", cgu.version), { Documents.openUrl(context, cgu.url) },
                    style = DS.Typography.footnote.copy(fontWeight = FontWeight.SemiBold),
                )
            }
        },
    )

    model.error?.takeIf { it.fieldErrors.isEmpty() }?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
    PrimaryButton(
        t("Créer mon compte"), { scope.launch { model.submit() } },
        enabled = model.canSubmitDetails, loading = model.isLoading, tag = "register.submit",
    )

    if (showDatePicker) {
        val today = LocalDate.now(ZoneOffset.UTC).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
        val state = rememberDatePickerState(
            initialSelectedDateMillis = model.birthDate.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
            selectableDates = object : SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis <= today
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
            onDismissRequest = { showDatePicker = false },
            confirmButton = {
                TextButton({
                    state.selectedDateMillis?.let { model.birthDate = Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate() }
                    showDatePicker = false
                }) { Text(t("OK"), style = DS.Typography.body.copy(fontWeight = FontWeight.SemiBold), color = DS.Palette.accent) }
            },
            dismissButton = {
                TextButton({ showDatePicker = false }) { Text(t("Annuler"), style = DS.Typography.body, color = DS.Palette.accent) }
            },
            colors = colors,
        ) {
            DatePicker(state, colors = colors)
        }
    }
}

// MARK: - Forgot password

@Composable
private fun ForgotPasswordView(model: ForgotPasswordViewModel, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    Screen(t("Mot de passe oublié"), largeTitle = false, onBack = onBack, modifier = Modifier.imePadding(), contentPadding = authPadding) {
        when (model.step) {
            ForgotPasswordViewModel.Step.phone -> {
                Text(
                    t("Saisissez votre numéro : vous recevrez un code pour choisir un nouveau mot de passe."),
                    style = DS.Typography.callout, color = DS.Palette.textSecondary,
                )
                PhoneField(model.otp.phone, { model.otp.phone = it }, model.otp.error?.fieldErrors?.get("phone"))
                model.otp.error?.takeIf { it.fieldErrors.isEmpty() }?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
                PrimaryButton(
                    t("Recevoir le code"), { scope.launch { model.sendCode() } },
                    enabled = model.otp.phoneIsValid, loading = model.otp.isSending,
                )
            }
            ForgotPasswordViewModel.Step.otp -> OTPEntry(model.otp) { scope.launch { model.verifyCode() } }
            ForgotPasswordViewModel.Step.newPassword -> {
                LabeledField(t("Nouveau mot de passe (8 caractères min.)")) {
                    InputText(model.password, { model.password = it }, t("Mot de passe"), secure = true)
                }
                val mismatch = model.confirmation.isNotEmpty() && model.confirmation != model.password
                LabeledField(t("Confirmation"), error = if (mismatch) t("Les mots de passe ne correspondent pas.") else null) {
                    InputText(model.confirmation, { model.confirmation = it }, t("Confirmez"), secure = true)
                }
                model.error?.let { MessageBanner(Message(Message.Level.error, it.userMessage)) }
                PrimaryButton(
                    t("Enregistrer et se connecter"), { scope.launch { model.save() } },
                    enabled = model.canSave, loading = model.isLoading,
                )
            }
        }
    }
}
