import Foundation

struct UploadFile: Sendable {
    let data: Data
    let fileName: String
    let mimeType: String
}

protocol Uploading: Sendable {
    /// Uploads the file and returns the server `uploadId`. `progress` is called with 0…1.
    func upload(_ file: UploadFile, purpose: String, progress: @escaping @Sendable (Double) -> Void) async throws -> String
}

/// tus-like resumable uploads: create a session, send chunks with `Upload-Offset`, and on any retryable
/// failure ask the server for the committed offset and continue from there (exponential backoff).
struct ResumableUploader: Uploading {
    let api: AssurAPI
    var maxAttempts = 6
    var baseDelay: Duration = .seconds(1)

    func upload(_ file: UploadFile, purpose: String, progress: @escaping @Sendable (Double) -> Void) async throws -> String {
        let total = file.data.count
        var session = try await withRetry {
            try await api.createUpload(UploadCreateRequest(fileName: file.fileName, mimeType: file.mimeType, size: total, purpose: purpose))
        }
        progress(Double(session.offset) / Double(max(total, 1)))

        var failures = 0
        while session.offset < total {
            try Task.checkCancellation()
            let end = min(session.offset + max(session.chunkSize, 64 * 1024), total)
            let chunk = file.data.subdata(in: session.offset..<end)
            do {
                session = try await api.uploadChunk(uploadId: session.uploadId, offset: session.offset, data: chunk)
                failures = 0
                progress(Double(session.offset) / Double(max(total, 1)))
            } catch let error as APIError where error.isRetryable || isOffsetConflict(error) {
                failures += 1
                if failures >= maxAttempts { throw error }
                try await Task.sleep(for: baseDelay * (1 << (failures - 1)))
                // The chunk may have been partially committed: resume from the server's offset.
                let uploadId = session.uploadId
                session = try await withRetry { try await api.uploadOffset(uploadId: uploadId) }
            }
        }
        progress(1)
        return session.uploadId
    }

    private func isOffsetConflict(_ error: APIError) -> Bool {
        if case .server(409, _, _, _) = error { return true }
        return false
    }

    private func withRetry<T: Sendable>(_ operation: @Sendable () async throws -> T) async throws -> T {
        var attempt = 0
        while true {
            do {
                return try await operation()
            } catch let error as APIError where error.isRetryable && attempt + 1 < maxAttempts {
                attempt += 1
                try await Task.sleep(for: baseDelay * (1 << (attempt - 1)))
            }
        }
    }
}
