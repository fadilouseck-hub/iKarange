import UIKit

/// Generated stand-ins for server files (PDFs, sample invoice) so MockAPI ships no binary fixtures.
enum MockDocuments {
    static func pdf(title: String, lines: [String]) -> Data {
        let page = CGRect(x: 0, y: 0, width: 595, height: 842) // A4 in points
        return UIGraphicsPDFRenderer(bounds: page).pdfData { context in
            context.beginPage()
            let titleAttributes: [NSAttributedString.Key: Any] = [
                .font: UIFont.boldSystemFont(ofSize: 20), .foregroundColor: UIColor(hex: Tenant.hex(Tenant.current.colors.brandDark)),
            ]
            let bodyAttributes: [NSAttributedString.Key: Any] = [.font: UIFont.systemFont(ofSize: 12)]
            let noteAttributes: [NSAttributedString.Key: Any] = [
                .font: UIFont.italicSystemFont(ofSize: 10), .foregroundColor: UIColor.gray,
            ]
            (Tenant.current.displayName + " — " + title as NSString).draw(at: CGPoint(x: 48, y: 48), withAttributes: titleAttributes)
            var y: CGFloat = 96
            for line in lines {
                if y > page.height - 72 {
                    context.beginPage()
                    y = 48
                }
                let rect = CGRect(x: 48, y: y, width: page.width - 96, height: 200)
                let height = (line as NSString).boundingRect(with: rect.size, options: .usesLineFragmentOrigin, attributes: bodyAttributes, context: nil).height
                (line as NSString).draw(in: rect, withAttributes: bodyAttributes)
                y += height + 8
            }
            ("Document de démonstration — sans valeur contractuelle." as NSString)
                .draw(at: CGPoint(x: 48, y: page.height - 40), withAttributes: noteAttributes)
        }
    }

    /// A fake pharmacy receipt, used in MockAPI when no camera is available (Simulator, UI tests).
    static func sampleInvoice() -> UIImage {
        let size = CGSize(width: 900, height: 1300)
        let format = UIGraphicsImageRendererFormat()
        format.scale = 1
        return UIGraphicsImageRenderer(size: size, format: format).image { context in
            UIColor.white.setFill()
            context.fill(CGRect(origin: .zero, size: size))
            let mono = UIFont.monospacedSystemFont(ofSize: 30, weight: .regular)
            let bold = UIFont.monospacedSystemFont(ofSize: 36, weight: .bold)
            let rows: [(String, UIFont)] = [
                ("PHARMACIE DÉMO MERMOZ", bold),
                ("VDN, Mermoz — Dakar", mono),
                ("", mono),
                ("Facture F-2026-10-0457", mono),
                ("Date : 03/10/2026", mono),
                ("", mono),
                ("Paracétamol 500mg  2 x 1500   3000", mono),
                ("Amoxicilline 1g    1 x 12500 12500", mono),
                ("Sirop antitussif   1 x 4500   4500", mono),
                ("", mono),
                ("TOTAL                     20000 FCFA", bold),
                ("", mono),
                ("*** EXEMPLE — DOCUMENT FICTIF ***", mono),
            ]
            var y: CGFloat = 80
            for (text, font) in rows {
                (text as NSString).draw(at: CGPoint(x: 60, y: y), withAttributes: [.font: font, .foregroundColor: UIColor.black])
                y += 64
            }
        }
    }
}
