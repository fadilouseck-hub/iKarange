import SwiftUI
import UserNotifications

@main
struct AssurPlusApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @State private var environment: AppEnvironment

    init() {
        let environment = AppEnvironment.make()
        _environment = State(initialValue: environment)
        AppDelegate.environment = environment
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(environment)
                .tint(DS.Palette.accent)
                .onOpenURL { environment.router.open(url: $0) }
        }
    }
}

/// APNs registration and notification taps. Push payloads carry only `{ "target": { "kind", "id" } }`
/// and a generic title — never personal or medical data.
final class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    static var environment: AppEnvironment?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        return true
    }

    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        let token = deviceToken.map { String(format: "%02x", $0) }.joined()
        guard let api = Self.environment?.api else { return }
        let version = Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "1.0"
        Task {
            try? await api.registerDevice(DeviceRegistration(token: token, platform: "ios", locale: Locale.current.identifier, appVersion: version))
        }
    }

    func application(_ application: UIApplication, handleEventsForBackgroundURLSession identifier: String, completionHandler: @escaping () -> Void) {
        nonisolated(unsafe) let handler = completionHandler
        Self.environment?.backgroundUploads?.backgroundCompletionHandler = { handler() }
    }

    nonisolated func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification) async -> UNNotificationPresentationOptions {
        [.banner, .list, .sound]
    }

    nonisolated func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse) async {
        let userInfo = response.notification.request.content.userInfo
        guard let target = Self.target(from: userInfo) else { return }
        await MainActor.run { Self.environment?.router.open(target) }
    }

    nonisolated static func target(from userInfo: [AnyHashable: Any]) -> DeepLinkTarget? {
        guard let raw = userInfo["target"] as? [String: Any],
              let kindRaw = raw["kind"] as? String,
              let kind = DeepLinkTarget.Kind(rawValue: kindRaw) else { return nil }
        return DeepLinkTarget(kind: kind, id: raw["id"] as? String)
    }

    @MainActor
    static func requestPushAuthorization() async {
        let granted = (try? await UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound])) ?? false
        if granted { UIApplication.shared.registerForRemoteNotifications() }
    }
}
