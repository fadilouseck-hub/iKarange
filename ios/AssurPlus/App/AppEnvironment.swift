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

    init(
        api: AssurAPI, uploader: Uploading, cache: ResponseCache, settings: AppSettings, tokens: TokenStore,
        biometrics: BiometricAuthenticating, paymentLauncher: PaymentLaunching, wallet: WalletAdding,
        isMock: Bool, backgroundUploads: BackgroundUploadTransport? = nil
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
        router = Router()
        session = AuthSession(api: api, tokens: tokens, cache: cache, settings: settings)
    }

    /// Launch arguments: `-UseMockAPI` (MockAPI without the build configuration), `-ResetState`
    /// (fresh in-memory session, used by UI tests), `-MockLatency <seconds>`.
    static func make(arguments: [String] = ProcessInfo.processInfo.arguments) -> AppEnvironment {
        #if MOCK_API
        let useMock = true
        #else
        let useMock = arguments.contains("-UseMockAPI")
        #endif
        let reset = arguments.contains("-ResetState")

        let tokens: TokenStore = reset ? InMemoryTokenStore() : KeychainTokenStore(service: useMock ? "sn.assurplus.app.mock" : "sn.assurplus.app.tokens")
        let cache = SwiftDataCache(inMemory: reset)
        let settings = AppSettings(defaults: reset ? UserDefaults(suiteName: "uitests-\(UUID().uuidString)")! : .standard)

        if useMock {
            let latency = arguments.firstIndex(of: "-MockLatency").flatMap { index in
                arguments.indices.contains(index + 1) ? Double(arguments[index + 1]) : nil
            } ?? 0.35
            let server = MockServer(latency: .milliseconds(Int(latency * 1000)))
            let client = APIClient(baseURL: MockServer.baseURL, transport: server, tokens: tokens)
            let api = AssurAPI(client: client)
            return AppEnvironment(
                api: api, uploader: ResumableUploader(api: api), cache: cache, settings: settings, tokens: tokens,
                biometrics: AlwaysBiometrics(), paymentLauncher: MockPaymentLauncher(), wallet: MockWallet(), isMock: true)
        }

        let baseURL = (Bundle.main.object(forInfoDictionaryKey: "API_BASE_URL") as? String).flatMap(URL.init(string:))
            ?? URL(string: "https://api.assurplus.sn/v1")!
        let transport = URLSessionTransport.makeDefault()
        let client = APIClient(baseURL: baseURL, transport: transport, tokens: tokens)
        let uploadTransport = BackgroundUploadTransport(fallback: transport)
        let uploadClient = APIClient(baseURL: baseURL, transport: uploadTransport, tokens: tokens)
        return AppEnvironment(
            api: AssurAPI(client: client), uploader: ResumableUploader(api: AssurAPI(client: uploadClient)), cache: cache,
            settings: settings, tokens: tokens, biometrics: DeviceBiometrics(), paymentLauncher: LivePaymentLauncher(),
            wallet: LiveWallet(), isMock: false, backgroundUploads: uploadTransport)
    }
}
