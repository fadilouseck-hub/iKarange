import Foundation

/// Every error surfaced to the UI. Server messages are already in French and shown as-is.
enum APIError: Error, Equatable, Sendable {
    /// `{ "error": { "code", "message", "fields" } }` returned by the API.
    case server(status: Int, code: String, message: String, fields: [String: String])
    /// Refresh failed: the session is over and the user must log in again.
    case unauthorized
    case offline
    case timeout
    case decoding(String)
    case invalidResponse
    case transport(String)
    case cancelled

    var userMessage: String {
        switch self {
        case .server(_, _, let message, _):
            message
        case .unauthorized:
            String(localized: "Votre session a expiré. Veuillez vous reconnecter.")
        case .offline:
            String(localized: "Pas de connexion internet. Les données affichées peuvent ne pas être à jour.")
        case .timeout:
            String(localized: "Le réseau est lent. Veuillez réessayer.")
        case .decoding, .invalidResponse:
            String(localized: "Réponse inattendue du serveur. Veuillez réessayer plus tard.")
        case .transport:
            String(localized: "Une erreur réseau est survenue. Veuillez réessayer.")
        case .cancelled:
            String(localized: "Opération annulée.")
        }
    }

    /// Field-level validation messages, keyed by the request field name.
    var fieldErrors: [String: String] {
        if case .server(_, _, _, let fields) = self { return fields }
        return [:]
    }

    var isRetryable: Bool {
        switch self {
        case .offline, .timeout, .transport: true
        case .server(let status, _, _, _): status >= 500 || status == 429
        default: false
        }
    }

    static func wrap(_ error: Error) -> APIError {
        if let apiError = error as? APIError { return apiError }
        if error is CancellationError { return .cancelled }
        if let urlError = error as? URLError {
            switch urlError.code {
            case .notConnectedToInternet, .networkConnectionLost, .dataNotAllowed, .internationalRoamingOff:
                return .offline
            case .timedOut:
                return .timeout
            case .cancelled:
                return .cancelled
            default:
                return .transport(urlError.code.rawValue.description)
            }
        }
        if error is DecodingError { return .decoding(String(describing: error)) }
        return .transport(String(describing: type(of: error)))
    }
}

/// Wire format of API errors.
struct APIErrorEnvelope: Decodable, Sendable {
    struct Body: Decodable, Sendable {
        let code: String
        let message: String
        let fields: [String: String]?
    }
    let error: Body
}
