import Foundation
import Observation

/// Dependency container injected through the SwiftUI environment. Every service is behind a protocol
/// (transport, token store, uploader, payment launcher, wallet, biometrics) so it can be mocked.
@MainActor
@Observable
final class AppEnvironment {
    let api: AssurAPI
    let uploader: Uploading
    let cache: ResponseCache
    let settings: AppSettings
    let session: AuthSession
    let router: Router
    let biometrics: BiometricAuthenticating
    let paymentLauncher: PaymentLaunching
    let wallet: WalletAdding
    let isMock: Bool
    let backgroundUploads: BackgroundUploadTransport?
    let language: LanguageSettings
    /// Backend capabilities: the tenant's in production, everything in MockAPI.
    var features: Tenant.Features { isMock ? .all : Tenant.current.features }
    var loginIdentifier: Tenant.LoginIdentifier { isMock ? .phone : Tenant.current.loginIdentifier }
    /// MockAPI only: demo account to sign in automatically at launch ("principal" or "dependent").
    var mockSignIn: String?

    init(
        api: AssurAPI, uploader: Uploading, cache: ResponseCache, settings: AppSettings, tokens: TokenStore,
        biometrics: BiometricAuthenticating, paymentLauncher: PaymentLaunching, wallet: WalletAdding,
        isMock: Bool, backgroundUploads: BackgroundUploadTransport? = nil, language: LanguageSettings? = nil
    ) {
        self.api = api
        self.uploader = uploader
        self.cache = cache
        self.settings = settings
        self.biometrics = biometrics
        self.paymentLauncher = paymentLauncher
        self.wallet = wallet
        self.isMock = isMock
        self.backgroundUploads = backgroundUploads
        self.language = language ?? LanguageSettings(defaults: settings.defaults)
        router = Router()
        session = AuthSession(api: api, tokens: tokens, cache: cache, settings: settings)
    }

    /// Launch arguments: `-UseMockAPI` (MockAPI without the build configuration), `-ResetState`
    /// (fresh in-memory session, used by UI tests), `-MockLatency <seconds>`, `-MockQRLifetime <seconds>`,
    /// `-MockSignIn principal|dependent` (skip the login screen, MockAPI only).
    static func make(arguments: [String] = ProcessInfo.processInfo.arguments) -> AppEnvironment {
        #if DEBUG
        let useMock = isMockBuild || arguments.contains("-UseMockAPI")
        #else
        let useMock = isMockBuild // Release (customer) builds never run on demo data
        #endif
        let reset = arguments.contains("-ResetState")

        let tokens: TokenStore = reset ? InMemoryTokenStore() : KeychainTokenStore(service: useMock ? "sn.assurplus.app.mock" : "sn.assurplus.app.tokens")
        let cache = SwiftDataCache(inMemory: reset)
        let settings = AppSettings(defaults: reset ? UserDefaults(suiteName: "uitests-\(UUID().uuidString)")! : .standard)

        if useMock {
            let latency = value(after: "-MockLatency", in: arguments).flatMap(Double.init) ?? 0.35
            let qrLifetime = value(after: "-MockQRLifetime", in: arguments).flatMap(Double.init) ?? 60
            let server = MockServer(latency: .milliseconds(Int(latency * 1000)), qrLifetime: qrLifetime)
            let client = APIClient(baseURL: MockServer.baseURL, transport: server, tokens: tokens)
            let api = AssurAPI(client: client)
            let environment = AppEnvironment(
                api: api, uploader: ResumableUploader(api: api), cache: cache, settings: settings, tokens: tokens,
                biometrics: AlwaysBiometrics(), paymentLauncher: MockPaymentLauncher(), wallet: MockWallet(), isMock: true)
            environment.mockSignIn = value(after: "-MockSignIn", in: arguments)
            return environment
        }

        guard var baseURL = Tenant.current.apiURL else { fatalError("Tenant.plist apiBaseURL is invalid") }
        #if DEBUG
        // Debug builds only: point at a local copy of the backend, e.g. `-APIBaseURL http://localhost:8080/api/mobile/v1`.
        if let override = value(after: "-APIBaseURL", in: arguments).flatMap(URL.init(string:)) { baseURL = override }
        #endif
        let transport = URLSessionTransport.makeDefault()
        let client = APIClient(baseURL: baseURL, transport: transport, tokens: tokens)
        let uploadTransport = BackgroundUploadTransport(fallback: transport)
        let uploadClient = APIClient(baseURL: baseURL, transport: uploadTransport, tokens: tokens)
        return AppEnvironment(
            api: AssurAPI(client: client), uploader: ResumableUploader(api: AssurAPI(client: uploadClient)), cache: cache,
            settings: settings, tokens: tokens, biometrics: DeviceBiometrics(), paymentLauncher: LivePaymentLauncher(),
            wallet: LiveWallet(), isMock: false, backgroundUploads: uploadTransport)
    }

    #if MOCK_API
    static let isMockBuild = true
    #else
    static let isMockBuild = false
    #endif

    private static func value(after flag: String, in arguments: [String]) -> String? {
        guard let index = arguments.firstIndex(of: flag), arguments.indices.contains(index + 1) else { return nil }
        return arguments[index + 1]
    }

    /// Signs in the MockAPI demo account requested by `-MockSignIn`.
    func performMockSignInIfRequested() async {
        guard isMock, let account = mockSignIn, session.state == .signedOut else { return }
        mockSignIn = nil
        let phone = account == "dependent" ? "+221770000002" : "+221770000001"
        if let response = try? await api.login(LoginRequest(phone: phone, password: "assur1234")) {
            session.didAuthenticate(response)
        }
    }
}
