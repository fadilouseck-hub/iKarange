import CryptoKit
import Foundation
import Security

/// Optional public-key pinning. Enabled when Info.plist `PINNED_PUBLIC_KEY_HASHES` lists base64 SHA-256
/// hashes of the server's SubjectPublicKeyInfo (include a backup key). Empty or absent → system trust only.
final class PinningDelegate: NSObject, URLSessionDelegate, Sendable {
    let pinnedHashes: Set<String>

    init(pinnedHashes: Set<String>) {
        self.pinnedHashes = pinnedHashes
    }

    static func fromInfoPlist(_ bundle: Bundle = .main) -> PinningDelegate? {
        let hashes = (bundle.object(forInfoDictionaryKey: "PINNED_PUBLIC_KEY_HASHES") as? [String] ?? []).filter { !$0.isEmpty }
        return hashes.isEmpty ? nil : PinningDelegate(pinnedHashes: Set(hashes))
    }

    func urlSession(_ session: URLSession, didReceive challenge: URLAuthenticationChallenge) async -> (URLSession.AuthChallengeDisposition, URLCredential?) {
        guard challenge.protectionSpace.authenticationMethod == NSURLAuthenticationMethodServerTrust,
              let trust = challenge.protectionSpace.serverTrust else {
            return (.performDefaultHandling, nil)
        }
        // Normal chain validation first, then the pin.
        guard SecTrustEvaluateWithError(trust, nil),
              let chain = SecTrustCopyCertificateChain(trust) as? [SecCertificate] else {
            return (.cancelAuthenticationChallenge, nil)
        }
        for certificate in chain {
            if let hash = Self.spkiHash(certificate), pinnedHashes.contains(hash) {
                return (.useCredential, URLCredential(trust: trust))
            }
        }
        return (.cancelAuthenticationChallenge, nil)
    }

    /// SHA-256 of the key with its ASN.1 SPKI header (RSA 2048 / EC P-256), base64 — same as `openssl ... | base64`.
    static func spkiHash(_ certificate: SecCertificate) -> String? {
        guard let key = SecCertificateCopyKey(certificate),
              let data = SecKeyCopyExternalRepresentation(key, nil) as Data?,
              let attributes = SecKeyCopyAttributes(key) as? [CFString: Any] else { return nil }
        let type = attributes[kSecAttrKeyType] as? String
        let size = attributes[kSecAttrKeySizeInBits] as? Int
        let header: [UInt8]
        switch (type, size) {
        case (String(kSecAttrKeyTypeRSA), 2048):
            header = [0x30, 0x82, 0x01, 0x22, 0x30, 0x0d, 0x06, 0x09, 0x2a, 0x86, 0x48, 0x86, 0xf7, 0x0d, 0x01, 0x01, 0x01, 0x05, 0x00, 0x03, 0x82, 0x01, 0x0f, 0x00]
        case (String(kSecAttrKeyTypeECSECPrimeRandom), 256):
            header = [0x30, 0x59, 0x30, 0x13, 0x06, 0x07, 0x2a, 0x86, 0x48, 0xce, 0x3d, 0x02, 0x01, 0x06, 0x08, 0x2a, 0x86, 0x48, 0xce, 0x3d, 0x03, 0x01, 0x07, 0x03, 0x42, 0x00]
        default:
            return nil
        }
        return Data(SHA256.hash(data: Data(header) + data)).base64EncodedString()
    }
}
