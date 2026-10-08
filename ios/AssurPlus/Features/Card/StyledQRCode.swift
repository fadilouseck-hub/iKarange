import CoreImage.CIFilterBuiltins
import UIKit

/// The QR code's module grid, computed locally with CoreImage. The payload is the server's signed token only.
struct QRMatrix: Equatable {
    let size: Int
    private let modules: [Bool]

    init(size: Int, modules: [Bool]) {
        self.size = size
        self.modules = modules
    }

    subscript(x: Int, y: Int) -> Bool {
        guard (0..<size).contains(x), (0..<size).contains(y) else { return false }
        return modules[y * size + x]
    }

    /// Correction level "H" (≈30 % recoverable) leaves room for the centre logo.
    static func make(_ payload: String, correction: String = "H") -> QRMatrix? {
        let filter = CIFilter.qrCodeGenerator()
        filter.message = Data(payload.utf8)
        filter.correctionLevel = correction
        guard let output = filter.outputImage,
              let cgImage = CIContext().createCGImage(output, from: output.extent) else { return nil }

        // One pixel per module; read it back as greyscale.
        let width = cgImage.width, height = cgImage.height
        var pixels = [UInt8](repeating: 255, count: width * height)
        guard let context = CGContext(data: &pixels, width: width, height: height, bitsPerComponent: 8, bytesPerRow: width,
                                      space: CGColorSpaceCreateDeviceGray(), bitmapInfo: CGImageAlphaInfo.none.rawValue) else { return nil }
        context.draw(cgImage, in: CGRect(x: 0, y: 0, width: width, height: height))

        // Trim the quiet zone CoreImage adds around the symbol.
        var minX = width, minY = height, maxX = -1, maxY = -1
        for y in 0..<height {
            for x in 0..<width where pixels[y * width + x] < 128 {
                minX = min(minX, x); maxX = max(maxX, x)
                minY = min(minY, y); maxY = max(maxY, y)
            }
        }
        guard maxX >= minX, maxX - minX == maxY - minY else { return nil }
        let size = maxX - minX + 1
        var modules = [Bool](repeating: false, count: size * size)
        for y in 0..<size {
            for x in 0..<size { modules[y * size + x] = pixels[(minY + y) * width + (minX + x)] < 128 }
        }
        return QRMatrix(size: size, modules: modules)
    }

    /// Top-left corner of each 7×7 finder pattern.
    var finderOrigins: [(x: Int, y: Int)] { [(0, 0), (size - 7, 0), (0, size - 7)] }

    func isInFinder(_ x: Int, _ y: Int) -> Bool {
        finderOrigins.contains { x >= $0.x && x < $0.x + 7 && y >= $0.y && y < $0.y + 7 }
    }
}

enum QRCode {
    /// Plain square-module image (kept for simple uses and tests).
    static func image(for payload: String) -> UIImage? {
        let filter = CIFilter.qrCodeGenerator()
        filter.message = Data(payload.utf8)
        filter.correctionLevel = "M"
        guard let output = filter.outputImage?.transformed(by: CGAffineTransform(scaleX: 10, y: 10)),
              let cgImage = CIContext().createCGImage(output, from: output.extent) else { return nil }
        return UIImage(cgImage: cgImage)
    }

    struct Style {
        // Deep brand colour: high contrast on white for scanners.
        var foreground = UIColor(hex: Tenant.hex(Tenant.current.colors.brandDark))
        var eyeAccent = UIColor(hex: Tenant.hex(Tenant.current.colors.brandDark))
        var background = UIColor.white
        var logoBackground = UIColor(hex: Tenant.hex(Tenant.current.colors.brandDark))
        var logoMark = UIColor(hex: Tenant.hex(Tenant.current.colors.brandAccent))
        /// Dot diameter relative to the module size.
        var dotScale: CGFloat = 0.82
        /// Logo side relative to the symbol (≤ 0.24 keeps it well within "H" correction).
        var logoRatio: CGFloat = 0.22
        /// Quiet zone in modules (the spec requires 4; the white card padding adds to it).
        var quietZone = 2
    }

    /// Rounded "dots" modules, rounded finder eyes and the tenant mark in the centre.
    static func styledImage(for payload: String, side: CGFloat = 720, style: Style = Style()) -> UIImage? {
        guard let matrix = QRMatrix.make(payload) else { return nil }
        let total = CGFloat(matrix.size + style.quietZone * 2)
        let cell = side / total
        let origin = CGFloat(style.quietZone) * cell

        // Modules hidden behind the logo, rounded out to whole modules plus a 1-module margin.
        let logoModules = Int((CGFloat(matrix.size) * style.logoRatio).rounded(.up)) | 1 // odd → centred
        let logoStart = (matrix.size - logoModules) / 2
        let clearRange = (logoStart - 1)...(logoStart + logoModules)

        let format = UIGraphicsImageRendererFormat()
        format.scale = 1
        format.opaque = true
        return UIGraphicsImageRenderer(size: CGSize(width: side, height: side), format: format).image { context in
            let cg = context.cgContext
            style.background.setFill()
            cg.fill(CGRect(x: 0, y: 0, width: side, height: side))

            // Data modules as dots.
            style.foreground.setFill()
            let inset = cell * (1 - style.dotScale) / 2
            for y in 0..<matrix.size {
                for x in 0..<matrix.size where matrix[x, y] && !matrix.isInFinder(x, y) {
                    if clearRange.contains(x) && clearRange.contains(y) { continue }
                    let rect = CGRect(x: origin + CGFloat(x) * cell + inset, y: origin + CGFloat(y) * cell + inset,
                                      width: cell - 2 * inset, height: cell - 2 * inset)
                    cg.fillEllipse(in: rect)
                }
            }

            // Finder eyes: rounded 7×7 ring and rounded 3×3 pupil.
            for finder in matrix.finderOrigins {
                let outer = CGRect(x: origin + CGFloat(finder.x) * cell, y: origin + CGFloat(finder.y) * cell, width: 7 * cell, height: 7 * cell)
                let ring = UIBezierPath(roundedRect: outer.insetBy(dx: cell / 2, dy: cell / 2), cornerRadius: cell * 2)
                ring.lineWidth = cell
                style.foreground.setStroke()
                ring.stroke()
                style.eyeAccent.setFill()
                UIBezierPath(roundedRect: outer.insetBy(dx: 2 * cell, dy: 2 * cell), cornerRadius: cell * 1.1).fill()
            }

            // Centre logo: teal rounded square with the mint "+".
            let logoSide = CGFloat(logoModules) * cell
            let logoRect = CGRect(x: origin + CGFloat(logoStart) * cell, y: origin + CGFloat(logoStart) * cell, width: logoSide, height: logoSide)
            style.logoBackground.setFill()
            UIBezierPath(roundedRect: logoRect, cornerRadius: logoSide * 0.28).fill()
            style.logoMark.setFill()
            let bar = logoSide * 0.17, length = logoSide * 0.56
            let center = CGPoint(x: logoRect.midX, y: logoRect.midY)
            UIBezierPath(roundedRect: CGRect(x: center.x - bar / 2, y: center.y - length / 2, width: bar, height: length), cornerRadius: bar * 0.35).fill()
            UIBezierPath(roundedRect: CGRect(x: center.x - length / 2, y: center.y - bar / 2, width: length, height: bar), cornerRadius: bar * 0.35).fill()
        }
    }
}

/// Re-rendering every second (countdown) would be wasteful: keep the last styled image per token.
@MainActor
enum StyledQRCache {
    private static var last: (token: String, image: UIImage)?

    static func image(for token: String) -> UIImage? {
        if let last, last.token == token { return last.image }
        guard let image = QRCode.styledImage(for: token) else { return nil }
        last = (token, image)
        return image
    }
}
