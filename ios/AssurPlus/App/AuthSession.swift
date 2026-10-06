import Foundation
import Observation

/// Who is logged in and what they may do. Rights come from the server (`permissions` in `/me`).
@MainActor
@Observable
final class AuthSession {
    enum State: Equatable { case launching, signedOut, locked, signedIn }

    private(set) var state: State = .launching
    private(set) var user: Me?

    private let api: AssurAPI
    private let tokens: TokenStore
    private let cache: ResponseCache
    private let settings: AppSettings
    private var expiryObserver: NSObjectProtocol?

    init(api: AssurAPI, tokens: TokenStore, cache: ResponseCache, settings: AppSettings) {
        self.api = api
        self.tokens = tokens
        self.cache = cache
        self.settings = settings
        expiryObserver = NotificationCenter.default.addObserver(forName: .sessionExpired, object: nil, queue: .main) { [weak self] _ in
            MainActor.assumeIsolated { self?.endSession() }
        }
    }

    var permissions: Set<Permission> { user?.grantedPermissions ?? [] }
    func can(_ permission: Permission) -> Bool { permissions.contains(permission) }

    /// Called at launch: restores the session from the Keychain (cached profile when offline).
    func bootstrap() async {
        guard tokens.load() != nil else {
            state = .signedOut
            return
        }
        user = cache.load(Me.self, key: .me)
        state = settings.biometricLockEnabled ? .locked : .signedIn
        await refreshUser()
    }

    func refreshUser() async {
        do {
            let me = try await api.me()
            user = me
            cache.store(me, key: .me)
            if state == .launching { state = .signedIn }
        } catch APIError.unauthorized {
            endSession()
        } catch {
            if user == nil, state != .signedOut {
                // Offline on first launch with no cached profile: still let the user in to cached data.
                state = settings.biometricLockEnabled ? .locked : .signedIn
            }
        }
    }

    func didAuthenticate(_ response: AuthResponse) {
        tokens.save(response.tokens)
        user = response.user
        cache.store(response.user, key: .me)
        state = .signedIn
    }

    func updateUser(_ me: Me) {
        user = me
        cache.store(me, key: .me)
    }

    func lock() {
        if state == .signedIn, settings.biometricLockEnabled { state = .locked }
    }

    func unlock() { if state == .locked { state = .signedIn } }

    func logout() async {
        if let refresh = tokens.load()?.refreshToken {
            try? await api.logout(refreshToken: refresh)
        }
        endSession()
    }

    /// Clears tokens, caches and protected files.
    func endSession() {
        tokens.clear()
        cache.clear()
        ProtectedStorage.wipe()
        settings.biometricLockEnabled = false
        user = nil
        state = .signedOut
    }
}
