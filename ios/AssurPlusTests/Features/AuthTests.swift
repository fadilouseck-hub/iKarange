import Foundation
import Testing
@testable import AssurPlus

@MainActor
@Suite("Auth")
struct AuthTests {
    @Test func fixturesDecode() throws {
        let account = try MockFixtures.account()
        let catalog = try MockFixtures.catalog()
        #expect(account.users.count == 2)
        #expect(catalog.products.map(\.name) == ["Essentiel", "Sérénité"])
        #expect(try !MockFixtures.providers().providers.isEmpty)
    }

    @Test func passwordLogin() async {
        let env = makeMockEnvironment()
        let model = LoginViewModel(api: env.api, session: env.session)
        model.otp.phone = "77 000 00 01"
        model.password = "assur1234"
        #expect(model.canSubmit)
        await model.submit()
        #expect(env.session.state == .signedIn)
        #expect(env.session.user?.firstName == "Awa")
        #expect(env.session.can(.familyManage))
    }

    @Test func wrongPasswordShowsServerMessage() async {
        let env = makeMockEnvironment()
        let model = LoginViewModel(api: env.api, session: env.session)
        model.otp.phone = "770000001"
        model.password = "nope"
        await model.submit()
        #expect(env.session.state != .signedIn)
        #expect(model.error?.userMessage == "Numéro ou mot de passe incorrect.")
    }

    @Test func otpLogin() async {
        let env = makeMockEnvironment()
        let model = LoginViewModel(api: env.api, session: env.session)
        model.mode = .otp
        model.otp.phone = "770000001"
        await model.submit()
        #expect(model.showOTPEntry)
        #expect(model.otp.challenge?.maskedPhone == "+221 77 *** ** 01")

        model.otp.setCode("12-34-56-99")
        #expect(model.otp.code == "123456")
        await model.completeOTP()
        #expect(env.session.state == .signedIn)
    }

    @Test func wrongOTPIsRejected() async {
        let env = makeMockEnvironment()
        let otp = OTPViewModel(purpose: .login, api: env.api)
        otp.phone = "770000001"
        #expect(await otp.send())
        otp.setCode("000000")
        #expect(await otp.verify() == nil)
        #expect(otp.error?.fieldErrors["code"] == "Code incorrect.")
        #expect(otp.code.isEmpty)
    }

    @Test func dependentHasRestrictedPermissions() async throws {
        let env = makeMockEnvironment()
        try await signIn(env, phone: "770000002")
        #expect(env.session.user?.role == .dependent)
        #expect(env.session.can(.cardView))
        #expect(!env.session.can(.familyManage))
        #expect(!env.session.can(.paymentsCreate))
    }

    @Test func registration() async {
        let env = makeMockEnvironment()
        let model = RegisterViewModel(api: env.api, session: env.session)
        model.otp.phone = "781234567"
        await model.sendCode()
        #expect(model.step == .otp)
        model.otp.setCode(MockServer.otpCode)
        await model.verifyCode()
        #expect(model.step == .details)
        #expect(model.cgu?.version == "CGU-2026-01")

        model.firstName = "Khady"
        model.lastName = "Sarr"
        model.password = "motdepasse"
        model.passwordConfirmation = "motdepass"
        #expect(model.passwordError == "Les mots de passe ne correspondent pas.")
        model.passwordConfirmation = "motdepasse"
        #expect(!model.canSubmitDetails) // CGU not accepted yet
        model.acceptedCGU = true
        #expect(model.canSubmitDetails)
        await model.submit()
        #expect(env.session.state == .signedIn)
        #expect(env.session.user?.hasActivePolicy == false)
    }

    @Test func registeringAnExistingPhoneFails() async {
        let env = makeMockEnvironment()
        let model = RegisterViewModel(api: env.api, session: env.session)
        model.otp.phone = "770000001"
        await model.sendCode()
        #expect(model.step == .phone)
        #expect(model.otp.error?.userMessage.contains("existe déjà") == true)
    }

    @Test func passwordResetThenLogin() async {
        let env = makeMockEnvironment()
        let model = ForgotPasswordViewModel(api: env.api, session: env.session)
        model.otp.phone = "770000001"
        await model.sendCode()
        model.otp.setCode(MockServer.otpCode)
        await model.verifyCode()
        #expect(model.step == .newPassword)
        model.password = "nouveau123"
        model.confirmation = "nouveau123"
        await model.save()
        #expect(env.session.state == .signedIn)
    }

    @Test func logoutClearsEverything() async throws {
        let env = makeMockEnvironment()
        try await signIn(env)
        env.cache.store(["x"], key: .dashboard)
        await env.session.logout()
        #expect(env.session.state == .signedOut)
        #expect(env.session.user == nil)
        #expect(env.cache.load([String].self, key: .dashboard) == nil)
    }
}
