import Foundation
import Observation

/// Sends and verifies one-time codes. Reused by login, sign-up and password reset.
@MainActor
@Observable
final class OTPViewModel {
    let purpose: OTPPurpose
    private let api: AssurAPI

    var phone = ""
    var code = ""
    private(set) var challenge: OTPChallenge?
    private(set) var resendAvailableAt: Date?
    private(set) var isSending = false
    private(set) var isVerifying = false
    var error: APIError?

    init(purpose: OTPPurpose, api: AssurAPI) {
        self.purpose = purpose
        self.api = api
    }

    var phoneIsValid: Bool { PhoneNumber.isValid(phone) }
    var codeIsComplete: Bool { code.count == 6 }

    @discardableResult
    func send() async -> Bool {
        guard let e164 = PhoneNumber.e164(phone) else {
            error = .server(status: 422, code: "invalid_phone", message: String(localized: "Numéro de téléphone invalide."), fields: ["phone": String(localized: "Numéro sénégalais à 9 chiffres attendu.")])
            return false
        }
        isSending = true
        defer { isSending = false }
        do {
            let challenge = try await api.sendOTP(phone: e164, purpose: purpose)
            self.challenge = challenge
            resendAvailableAt = .now.addingTimeInterval(TimeInterval(challenge.resendAfter))
            code = ""
            error = nil
            return true
        } catch {
            self.error = .wrap(error)
            return false
        }
    }

    /// Returns the verification token on success.
    func verify() async -> String? {
        guard let challenge, codeIsComplete else { return nil }
        isVerifying = true
        defer { isVerifying = false }
        do {
            let verification = try await api.verifyOTP(requestId: challenge.otpRequestId, code: code)
            error = nil
            return verification.verificationToken
        } catch {
            self.error = .wrap(error)
            code = ""
            return nil
        }
    }

    func setCode(_ value: String) {
        code = String(value.filter(\.isNumber).prefix(6))
    }
}

@MainActor
@Observable
final class LoginViewModel {
    enum Mode: String, CaseIterable, Identifiable {
        case password, otp
        var id: Self { self }
        var label: String { self == .password ? String(localized: "Mot de passe") : String(localized: "Code SMS") }
    }

    private let api: AssurAPI
    private let session: AuthSession
    var otp: OTPViewModel

    var mode: Mode = .password
    var password = ""
    var showOTPEntry = false
    private(set) var isLoading = false
    var error: APIError?

    init(api: AssurAPI, session: AuthSession) {
        self.api = api
        self.session = session
        otp = OTPViewModel(purpose: .login, api: api)
    }

    var canSubmit: Bool {
        otp.phoneIsValid && (mode == .otp || !password.isEmpty) && !isLoading
    }

    func submit() async {
        guard let phone = PhoneNumber.e164(otp.phone) else {
            error = .server(status: 422, code: "invalid_phone", message: String(localized: "Numéro de téléphone invalide."), fields: [:])
            return
        }
        switch mode {
        case .password:
            isLoading = true
            defer { isLoading = false }
            do {
                session.didAuthenticate(try await api.login(LoginRequest(phone: phone, password: password)))
            } catch {
                self.error = .wrap(error)
            }
        case .otp:
            isLoading = true
            let sent = await otp.send()
            isLoading = false
            if sent { showOTPEntry = true } else { error = otp.error }
        }
    }

    func completeOTP() async {
        guard let token = await otp.verify(), let phone = PhoneNumber.e164(otp.phone) else { return }
        do {
            session.didAuthenticate(try await api.login(LoginRequest(phone: phone, verificationToken: token)))
        } catch {
            otp.error = .wrap(error)
        }
    }
}

@MainActor
@Observable
final class RegisterViewModel {
    enum Step { case phone, otp, details }

    private let api: AssurAPI
    private let session: AuthSession
    var otp: OTPViewModel

    var step: Step = .phone
    private var verificationToken: String?

    var firstName = ""
    var lastName = ""
    var birthDate = Calendar.current.date(byAdding: .year, value: -30, to: .now) ?? .now
    var gender: Gender = .female
    var email = ""
    var city = ""
    var usePassword = true
    var password = ""
    var passwordConfirmation = ""
    var acceptedCGU = false
    private(set) var cgu: LegalDocument?
    private(set) var isLoading = false
    var error: APIError?

    init(api: AssurAPI, session: AuthSession) {
        self.api = api
        self.session = session
        otp = OTPViewModel(purpose: .register, api: api)
    }

    var passwordError: String? {
        guard usePassword, !password.isEmpty else { return nil }
        if password.count < 8 { return String(localized: "8 caractères minimum.") }
        if !passwordConfirmation.isEmpty, password != passwordConfirmation { return String(localized: "Les mots de passe ne correspondent pas.") }
        return nil
    }

    var emailError: String? {
        guard !email.isEmpty else { return nil }
        return email.contains("@") && email.contains(".") ? nil : String(localized: "Adresse e-mail invalide.")
    }

    var canSubmitDetails: Bool {
        !firstName.trimmingCharacters(in: .whitespaces).isEmpty
            && !lastName.trimmingCharacters(in: .whitespaces).isEmpty
            && acceptedCGU && cgu != nil && emailError == nil
            && (!usePassword || (password.count >= 8 && password == passwordConfirmation))
            && !isLoading
    }

    func sendCode() async {
        if await otp.send() { step = .otp }
    }

    func verifyCode() async {
        if let token = await otp.verify() {
            verificationToken = token
            step = .details
            await loadCGU()
        }
    }

    func loadCGU() async {
        guard cgu == nil else { return }
        cgu = try? await api.legalDocuments().first { $0.id == "cgu" }
    }

    func submit() async {
        guard let verificationToken, let cgu else { return }
        isLoading = true
        defer { isLoading = false }
        let request = RegisterRequest(
            verificationToken: verificationToken,
            firstName: firstName.trimmingCharacters(in: .whitespaces),
            lastName: lastName.trimmingCharacters(in: .whitespaces),
            birthDate: LocalDay(date: birthDate), gender: gender,
            email: email.isEmpty ? nil : email, address: nil, city: city.isEmpty ? nil : city,
            password: usePassword ? password : nil, cguVersion: cgu.version)
        do {
            session.didAuthenticate(try await api.register(request))
        } catch {
            self.error = .wrap(error)
        }
    }
}

@MainActor
@Observable
final class ForgotPasswordViewModel {
    enum Step { case phone, otp, newPassword }

    private let api: AssurAPI
    private let session: AuthSession
    var otp: OTPViewModel
    var step: Step = .phone
    private var verificationToken: String?
    var password = ""
    var confirmation = ""
    private(set) var isLoading = false
    var error: APIError?

    init(api: AssurAPI, session: AuthSession) {
        self.api = api
        self.session = session
        otp = OTPViewModel(purpose: .resetPassword, api: api)
    }

    var canSave: Bool { password.count >= 8 && password == confirmation && !isLoading }

    func sendCode() async { if await otp.send() { step = .otp } }

    func verifyCode() async {
        if let token = await otp.verify() {
            verificationToken = token
            step = .newPassword
        }
    }

    func save() async {
        guard let verificationToken, let phone = PhoneNumber.e164(otp.phone) else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            try await api.resetPassword(PasswordResetRequest(verificationToken: verificationToken, newPassword: password))
            session.didAuthenticate(try await api.login(LoginRequest(phone: phone, password: password)))
        } catch {
            self.error = .wrap(error)
        }
    }
}
