import SwiftUI
import UIKit

/// Design tokens. Brand colours come from the I'KARANGE back-office (mint #2DD4A8, deep teal #1A3A3A);
/// to be reconciled with the ASSUR+ mockups when they are provided.
enum DS {
    enum Palette {
        static let mint = Color(hex: 0x2DD4A8)
        static let mintDark = Color(hex: 0x25B890)
        static let teal = Color(hex: 0x1A3A3A)
        static let tealMid = Color(hex: 0x2D5454)

        /// Primary action colour: deep teal on light (contrast 12:1 with white text), mint on dark.
        static let primary = dynamic(light: 0x1A3A3A, dark: 0x2DD4A8)
        static let onPrimary = dynamic(light: 0xFFFFFF, dark: 0x0E2222)
        static let accent = dynamic(light: 0x1C8F72, dark: 0x2DD4A8)
        static let accentSoft = dynamic(light: 0xE3F8F2, dark: 0x163A33)

        static let background = dynamic(light: 0xF5F5F9, dark: 0x0B1414)
        static let surface = dynamic(light: 0xFFFFFF, dark: 0x152222)
        static let surfaceMuted = dynamic(light: 0xEEF1F3, dark: 0x1D2D2D)
        static let border = dynamic(light: 0xDDE3E6, dark: 0x2A3C3C)

        static let textPrimary = dynamic(light: 0x0F1F1F, dark: 0xF1F6F5)
        static let textSecondary = dynamic(light: 0x4B5C5C, dark: 0xA9BDBB)

        static let success = dynamic(light: 0x157F5B, dark: 0x4ADE9F)
        static let successSoft = dynamic(light: 0xDDF5EA, dark: 0x173A2B)
        static let warning = dynamic(light: 0x9A5B00, dark: 0xF5B94A)
        static let warningSoft = dynamic(light: 0xFFF1D6, dark: 0x3D2E12)
        static let danger = dynamic(light: 0xB3261E, dark: 0xFF8A80)
        static let dangerSoft = dynamic(light: 0xFCE4E2, dark: 0x3F1C1A)
        static let info = dynamic(light: 0x1F5FA8, dark: 0x8AB8F0)
        static let infoSoft = dynamic(light: 0xE2EDFA, dark: 0x1A2A3F)

        static func dynamic(light: UInt32, dark: UInt32) -> Color {
            Color(UIColor { traits in
                UIColor(hex: traits.userInterfaceStyle == .dark ? dark : light)
            })
        }
    }

    enum Spacing {
        static let xxs: CGFloat = 2
        static let xs: CGFloat = 4
        static let s: CGFloat = 8
        static let m: CGFloat = 12
        static let l: CGFloat = 16
        static let xl: CGFloat = 24
        static let xxl: CGFloat = 32
    }

    enum Radius {
        static let s: CGFloat = 8
        static let m: CGFloat = 12
        static let l: CGFloat = 20
        static let pill: CGFloat = 999
    }

    /// Dynamic Type text styles only, so everything scales with the user's setting.
    enum Typography {
        static let display = Font.system(.largeTitle, design: .rounded).weight(.bold)
        static let title = Font.system(.title2, design: .rounded).weight(.bold)
        static let headline = Font.headline
        static let body = Font.body
        static let callout = Font.callout
        static let caption = Font.caption
        static let amount = Font.system(.title, design: .rounded).weight(.semibold).monospacedDigit()
        static let amountSmall = Font.system(.headline, design: .rounded).monospacedDigit()
    }
}

extension StatusTone {
    var foreground: Color {
        switch self {
        case .success: DS.Palette.success
        case .warning: DS.Palette.warning
        case .danger: DS.Palette.danger
        case .info: DS.Palette.info
        case .neutral: DS.Palette.textSecondary
        }
    }

    var background: Color {
        switch self {
        case .success: DS.Palette.successSoft
        case .warning: DS.Palette.warningSoft
        case .danger: DS.Palette.dangerSoft
        case .info: DS.Palette.infoSoft
        case .neutral: DS.Palette.surfaceMuted
        }
    }
}

extension Message.Level {
    var tone: StatusTone {
        switch self {
        case .info: .info
        case .warning: .warning
        case .error: .danger
        case .success: .success
        }
    }
}

extension Color {
    init(hex: UInt32) { self.init(UIColor(hex: hex)) }
}

extension UIColor {
    convenience init(hex: UInt32) {
        self.init(
            red: CGFloat((hex >> 16) & 0xFF) / 255,
            green: CGFloat((hex >> 8) & 0xFF) / 255,
            blue: CGFloat(hex & 0xFF) / 255,
            alpha: 1)
    }
}
