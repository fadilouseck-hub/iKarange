import UIKit

/// Shrinks photos before upload: any input format (HEIC included) → JPEG, long edge ≤ 1600 px, quality 0.7.
enum ImageCompressor {
    static let maxLongEdge: CGFloat = 1600
    static let quality: CGFloat = 0.7

    static func jpeg(from data: Data) -> Data? {
        guard let image = UIImage(data: data) else { return nil }
        return jpeg(from: image)
    }

    static func jpeg(from image: UIImage) -> Data? {
        resized(image).jpegData(compressionQuality: quality)
    }

    static func targetSize(for size: CGSize) -> CGSize {
        let longEdge = max(size.width, size.height)
        guard longEdge > maxLongEdge else { return size }
        let scale = maxLongEdge / longEdge
        return CGSize(width: (size.width * scale).rounded(), height: (size.height * scale).rounded())
    }

    static func resized(_ image: UIImage) -> UIImage {
        let pixelSize = CGSize(width: image.size.width * image.scale, height: image.size.height * image.scale)
        let target = targetSize(for: pixelSize)
        guard target != pixelSize else { return image }
        let format = UIGraphicsImageRendererFormat()
        format.scale = 1
        format.opaque = true
        return UIGraphicsImageRenderer(size: target, format: format).image { _ in
            image.draw(in: CGRect(origin: .zero, size: target))
        }
    }

    /// Merges document camera pages into one tall JPEG (keeps the upload contract to a single file).
    static func merge(pages: [UIImage]) -> UIImage? {
        guard pages.count > 1 else { return pages.first }
        let width = pages.map(\.size.width).max() ?? 0
        let height = pages.reduce(0) { $0 + $1.size.height * (width / max($1.size.width, 1)) }
        let format = UIGraphicsImageRendererFormat()
        format.scale = 1
        return UIGraphicsImageRenderer(size: CGSize(width: width, height: height), format: format).image { _ in
            var y: CGFloat = 0
            for page in pages {
                let pageHeight = page.size.height * (width / max(page.size.width, 1))
                page.draw(in: CGRect(x: 0, y: y, width: width, height: pageHeight))
                y += pageHeight
            }
        }
    }
}
