import Foundation

/// Local files holding personal or medical data: `NSFileProtectionComplete` and excluded from backups.
enum ProtectedStorage {
    static func directory(_ name: String) -> URL {
        let base = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
        var url = base.appendingPathComponent(name, isDirectory: true)
        try? FileManager.default.createDirectory(
            at: url, withIntermediateDirectories: true,
            attributes: [.protectionKey: FileProtectionType.complete])
        var values = URLResourceValues()
        values.isExcludedFromBackup = true
        try? url.setResourceValues(values)
        return url
    }

    /// Writes a file (vault document, PDF) to a protected location and returns its URL.
    @discardableResult
    static func write(_ data: Data, named fileName: String, in folder: String = "Files") throws -> URL {
        let url = directory(folder).appendingPathComponent(sanitize(fileName))
        try data.write(to: url, options: [.atomic, .completeFileProtection])
        var values = URLResourceValues()
        values.isExcludedFromBackup = true
        var mutable = url
        try? mutable.setResourceValues(values)
        return url
    }

    /// Removes every locally cached file (called on logout). The SwiftData store in "Cache" stays open
    /// and is emptied by `ResponseCache.clear()` instead.
    static func wipe() {
        try? FileManager.default.removeItem(at: directory("Files"))
        try? FileManager.default.contentsOfDirectory(at: FileManager.default.temporaryDirectory, includingPropertiesForKeys: nil)
            .forEach { try? FileManager.default.removeItem(at: $0) }
    }

    static func sanitize(_ name: String) -> String {
        let allowed = CharacterSet.alphanumerics.union(CharacterSet(charactersIn: "._-"))
        let cleaned = String(name.unicodeScalars.map { allowed.contains($0) ? Character($0) : "_" })
        return cleaned.isEmpty ? UUID().uuidString : cleaned
    }
}
