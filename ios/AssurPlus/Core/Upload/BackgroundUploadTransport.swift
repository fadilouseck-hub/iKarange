import Foundation

/// Sends request bodies with a background `URLSession` upload task, so a chunk in flight keeps going
/// when the app is suspended. Requests without a body fall back to the regular transport.
final class BackgroundUploadTransport: NSObject, HTTPTransport, URLSessionDataDelegate, @unchecked Sendable {
    static let identifier = "sn.assurplus.app.uploads"

    private let fallback: HTTPTransport
    private let lock = NSLock()
    private var pending: [Int: (continuation: CheckedContinuation<(Data, HTTPURLResponse), Error>, data: Data, file: URL)] = [:]
    private lazy var session: URLSession = {
        let configuration = URLSessionConfiguration.background(withIdentifier: Self.identifier)
        configuration.sessionSendsLaunchEvents = true
        configuration.isDiscretionary = false
        configuration.urlCache = nil
        return URLSession(configuration: configuration, delegate: self, delegateQueue: nil)
    }()

    /// Set by the app delegate when iOS relaunches the app to deliver background session events.
    var backgroundCompletionHandler: (@Sendable () -> Void)?

    init(fallback: HTTPTransport) {
        self.fallback = fallback
    }

    func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        guard let body = request.httpBody else { return try await fallback.send(request) }

        let file = FileManager.default.temporaryDirectory.appendingPathComponent("chunk-\(UUID().uuidString)")
        try body.write(to: file, options: [.completeFileProtectionUntilFirstUserAuthentication])
        var bodiless = request
        bodiless.httpBody = nil

        return try await withCheckedThrowingContinuation { continuation in
            let task = session.uploadTask(with: bodiless, fromFile: file)
            lock.withLock { pending[task.taskIdentifier] = (continuation, Data(), file) }
            task.resume()
        }
    }

    func urlSession(_ session: URLSession, dataTask: URLSessionDataTask, didReceive data: Data) {
        lock.withLock { pending[dataTask.taskIdentifier]?.data.append(data) }
    }

    func urlSession(_ session: URLSession, task: URLSessionTask, didCompleteWithError error: Error?) {
        guard let entry = lock.withLock({ pending.removeValue(forKey: task.taskIdentifier) }) else { return }
        try? FileManager.default.removeItem(at: entry.file)
        if let error {
            entry.continuation.resume(throwing: APIError.wrap(error))
        } else if let response = task.response as? HTTPURLResponse {
            entry.continuation.resume(returning: (entry.data, response))
        } else {
            entry.continuation.resume(throwing: APIError.invalidResponse)
        }
    }

    func urlSessionDidFinishEvents(forBackgroundURLSession session: URLSession) {
        let handler = lock.withLock { backgroundCompletionHandler }
        DispatchQueue.main.async { handler?() }
    }
}
