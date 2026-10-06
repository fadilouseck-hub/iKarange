import Foundation
import Testing
import UIKit
@testable import AssurPlus

@Suite("Formatting")
struct FormattingTests {
    @Test(arguments: [
        (0, "0 FCFA"), (500, "500 FCFA"), (20_000, "20 000 FCFA"), (1_500_000, "1 500 000 FCFA"), (-4_000, "-4 000 FCFA"),
    ])
    func money(amount: Int, expected: String) {
        #expect(Money.format(amount).replacingOccurrences(of: "\u{00A0}", with: " ") == expected)
    }

    @Test func moneyNeverBreaks() {
        #expect(!Money.format(20_000).contains(" "))
    }

    @Test(arguments: ["77 123 45 67", "+221 77 123 45 67", "00221771234567", "221771234567", "771234567"])
    func validPhones(input: String) {
        #expect(PhoneNumber.e164(input) == "+221771234567")
        #expect(PhoneNumber.display(input) == "+221 77 123 45 67")
    }

    @Test(arguments: ["12345", "6612345678", "991234567", ""])
    func invalidPhones(input: String) {
        #expect(!PhoneNumber.isValid(input))
    }

    @Test func formatsPhoneWhileTyping() {
        #expect(PhoneNumber.formatInput("7712") == "77 12")
        #expect(PhoneNumber.formatInput("771234567999") == "77 123 45 67")
    }

    @Test func imagesAreDownscaledTo1600LongEdge() {
        #expect(ImageCompressor.targetSize(for: CGSize(width: 4032, height: 3024)) == CGSize(width: 1600, height: 1200))
        #expect(ImageCompressor.targetSize(for: CGSize(width: 800, height: 600)) == CGSize(width: 800, height: 600))
        let big = UIGraphicsImageRenderer(size: CGSize(width: 3200, height: 1000), format: {
            let f = UIGraphicsImageRendererFormat(); f.scale = 1; return f
        }()).image { _ in UIColor.red.setFill(); UIRectFill(CGRect(x: 0, y: 0, width: 3200, height: 1000)) }
        let data = ImageCompressor.jpeg(from: big)!
        let decoded = UIImage(data: data)!
        #expect(decoded.size.width * decoded.scale == 1600)
    }

    @Test func protectedFileNamesAreSanitized() {
        #expect(ProtectedStorage.sanitize("../../etc/passwd") == ".._.._etc_passwd")
        #expect(ProtectedStorage.sanitize("facture 01.pdf") == "facture_01.pdf")
    }
}
