import Foundation

/// XOF amounts: integers, no decimals, displayed as `20 000 FCFA`.
enum Money {
    static func format(_ amount: Int) -> String {
        let digits = String(abs(amount))
        var groups: [Substring] = []
        var end = digits.endIndex
        while end > digits.startIndex {
            let start = digits.index(end, offsetBy: -3, limitedBy: digits.startIndex) ?? digits.startIndex
            groups.insert(digits[start..<end], at: 0)
            end = start
        }
        // Non-breaking spaces so an amount never wraps across lines.
        return (amount < 0 ? "-" : "") + groups.joined(separator: "\u{00A0}") + "\u{00A0}FCFA"
    }
}

/// Senegalese phone numbers: 9 national digits, +221 prefix, displayed as `+221 77 123 45 67`.
enum PhoneNumber {
    static let countryCode = "221"
    private static let validPrefixes = ["70", "71", "75", "76", "77", "78", "33"]

    /// The 9 national digits, or nil when the input is not a Senegalese number.
    static func nationalDigits(_ input: String) -> String? {
        var digits = input.filter(\.isNumber)
        if digits.hasPrefix("00221") { digits.removeFirst(5) } else if digits.hasPrefix(countryCode), digits.count == 12 { digits.removeFirst(3) }
        guard digits.count == 9, validPrefixes.contains(String(digits.prefix(2))) else { return nil }
        return digits
    }

    static func isValid(_ input: String) -> Bool { nationalDigits(input) != nil }

    /// E.164 form sent to the API.
    static func e164(_ input: String) -> String? { nationalDigits(input).map { "+\(countryCode)\($0)" } }

    static func display(_ input: String) -> String {
        guard let d = nationalDigits(input) else { return input }
        let c = Array(d)
        return "+221 \(String(c[0...1])) \(String(c[2...4])) \(String(c[5...6])) \(String(c[7...8]))"
    }

    /// Formats as the user types (national part only, max 9 digits).
    static func formatInput(_ input: String) -> String {
        let digits = String(input.filter(\.isNumber).prefix(9))
        var result = ""
        for (index, char) in digits.enumerated() {
            if [2, 5, 7].contains(index) { result.append(" ") }
            result.append(char)
        }
        return result
    }
}

enum DateText {
    private static var locale: Locale { AppLanguage.locale }

    static func day(_ day: LocalDay) -> String { self.day(day.date) }

    static func day(_ date: Date) -> String {
        date.formatted(.dateTime.day().month(.wide).year().locale(locale))
    }

    static func short(_ date: Date) -> String {
        date.formatted(.dateTime.day(.twoDigits).month(.twoDigits).year().locale(locale))
    }

    static func dateTime(_ date: Date) -> String {
        date.formatted(.dateTime.day().month(.abbreviated).year().hour().minute().locale(locale))
    }

    static func relative(_ date: Date, now: Date = .now) -> String {
        date.formatted(.relative(presentation: .named).locale(locale))
    }
}

enum Percent {
    static func format(_ value: Int) -> String { "\(value)\u{00A0}%" }
    static func confidence(_ value: Double) -> String { "\(Int((value * 100).rounded()))\u{00A0}%" }
}
