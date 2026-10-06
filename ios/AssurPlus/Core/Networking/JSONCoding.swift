import Foundation

/// JSON conventions shared with the API: camelCase keys, ISO-8601 timestamps, `yyyy-MM-dd` calendar days
/// (see `LocalDay`), amounts as integer XOF.
enum JSONCoding {
    static func decoder() -> JSONDecoder {
        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .custom { decoder in
            let container = try decoder.singleValueContainer()
            let string = try container.decode(String.self)
            if let date = parseTimestamp(string) { return date }
            throw DecodingError.dataCorruptedError(in: container, debugDescription: "Date ISO-8601 invalide: \(string)")
        }
        return decoder
    }

    static func encoder() -> JSONEncoder {
        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .custom { date, encoder in
            var container = encoder.singleValueContainer()
            try container.encode(date.formatted(.iso8601))
        }
        encoder.outputFormatting = [.sortedKeys]
        return encoder
    }

    static func parseTimestamp(_ string: String) -> Date? {
        if let date = try? Date(string, strategy: Date.ISO8601FormatStyle(includingFractionalSeconds: true)) {
            return date
        }
        if let date = try? Date(string, strategy: .iso8601) { return date }
        return LocalDay(string: string)?.date
    }
}

/// A calendar day without time (birth dates, contract start/end). Encoded as `yyyy-MM-dd`.
struct LocalDay: Codable, Hashable, Comparable, Sendable {
    let year: Int
    let month: Int
    let day: Int

    init(year: Int, month: Int, day: Int) {
        self.year = year
        self.month = month
        self.day = day
    }

    init?(string: String) {
        let parts = string.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3, (1...12).contains(parts[1]), (1...31).contains(parts[2]) else { return nil }
        self.init(year: parts[0], month: parts[1], day: parts[2])
    }

    init(date: Date, calendar: Calendar = .current) {
        let components = calendar.dateComponents([.year, .month, .day], from: date)
        self.init(year: components.year ?? 1970, month: components.month ?? 1, day: components.day ?? 1)
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        let string = try container.decode(String.self)
        guard let value = LocalDay(string: String(string.prefix(10))) else {
            throw DecodingError.dataCorruptedError(in: container, debugDescription: "Jour invalide: \(string)")
        }
        self = value
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        try container.encode(isoString)
    }

    var isoString: String { String(format: "%04d-%02d-%02d", year, month, day) }

    var date: Date {
        Calendar.current.date(from: DateComponents(year: year, month: month, day: day)) ?? .distantPast
    }

    static func < (lhs: LocalDay, rhs: LocalDay) -> Bool {
        (lhs.year, lhs.month, lhs.day) < (rhs.year, rhs.month, rhs.day)
    }
}

/// Decodes an empty or ignored response body (204, or bodies the app does not need).
struct EmptyResponse: Codable, Sendable {}
