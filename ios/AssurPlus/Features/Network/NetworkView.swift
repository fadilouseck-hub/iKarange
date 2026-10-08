import CoreLocation
import MapKit
import QuickLook
import SwiftUI

/// One-shot location, requested only when the user asks for nearby providers.
@MainActor
final class LocationProvider: NSObject, CLLocationManagerDelegate {
    private let manager = CLLocationManager()
    private var continuation: CheckedContinuation<CLLocationCoordinate2D?, Never>?

    override init() {
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyHundredMeters
    }

    var isDenied: Bool { [.denied, .restricted].contains(manager.authorizationStatus) }

    func currentLocation() async -> CLLocationCoordinate2D? {
        guard !isDenied else { return nil }
        continuation?.resume(returning: nil)
        return await withCheckedContinuation { continuation in
            self.continuation = continuation
            if manager.authorizationStatus == .notDetermined {
                manager.requestWhenInUseAuthorization()
            } else {
                manager.requestLocation()
            }
        }
    }

    nonisolated func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        let status = manager.authorizationStatus
        MainActor.assumeIsolated {
            switch status {
            case .authorizedWhenInUse, .authorizedAlways: self.manager.requestLocation()
            case .denied, .restricted: finish(nil)
            default: break
            }
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        let coordinate = locations.last?.coordinate
        MainActor.assumeIsolated { finish(coordinate) }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        MainActor.assumeIsolated { finish(nil) }
    }

    private func finish(_ coordinate: CLLocationCoordinate2D?) {
        continuation?.resume(returning: coordinate)
        continuation = nil
    }
}

@MainActor
@Observable
final class NetworkViewModel {
    enum Mode: String, CaseIterable, Identifiable {
        case map, list
        var id: Self { self }
        var label: String { self == .map ? String(localized: "network.mode.map", defaultValue: "Carte", bundle: .appLanguage, comment: "Map view (not the insurance card)") : String(localized: "Liste", bundle: .appLanguage) }
    }

    static let dakar = CLLocationCoordinate2D(latitude: 14.7167, longitude: -17.4677)
    static let radiusOptions: [Int?] = [nil, 2, 5, 10, 25]

    private let api: AssurAPI
    private let cache: ResponseCache
    private let location = LocationProvider()

    var mode: Mode = .list
    /// The map and distance search only make sense when the backend provides coordinates.
    var hasCoordinates: Bool { providers.contains { $0.coordinate != nil } }
    var type: ProviderType?
    var radiusKm: Int?
    var search = ""
    private(set) var userLocation: CLLocationCoordinate2D?
    private(set) var providers: [Provider]
    private(set) var isLoading = false
    private(set) var isLocating = false
    private(set) var locationDenied = false
    private(set) var isDownloadingPDF = false
    var error: APIError?
    var previewURL: URL?
    var selected: Provider?

    init(env: AppEnvironment) {
        api = env.api
        cache = env.cache
        providers = env.cache.load(ProvidersResponse.self, key: .providers)?.providers ?? []
    }

    func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.providers(
                type: type, latitude: userLocation?.latitude, longitude: userLocation?.longitude,
                radiusKm: userLocation == nil ? nil : radiusKm, search: search.isEmpty ? nil : search)
            providers = response.providers
            if type == nil, search.isEmpty, radiusKm == nil { cache.store(response, key: .providers) }
            error = nil
        } catch {
            let apiError = APIError.wrap(error)
            if apiError != .cancelled { self.error = apiError }
        }
    }

    /// Asks for location permission at the moment it is needed, never at launch.
    func locateMe() async {
        isLocating = true
        defer { isLocating = false }
        if let coordinate = await location.currentLocation() {
            userLocation = coordinate
            if radiusKm == nil { radiusKm = 10 }
            locationDenied = false
            await load()
        } else {
            locationDenied = location.isDenied
        }
    }

    func downloadPDF() async {
        isDownloadingPDF = true
        defer { isDownloadingPDF = false }
        do {
            let data = try await api.networkPDF(type: type)
            previewURL = try ProtectedStorage.write(data, named: "reseau-de-soins\(type.map { "-\($0.rawValue)" } ?? "").pdf")
        } catch {
            self.error = .wrap(error)
        }
    }
}

struct NetworkView: View {
    var body: some View {
        WithModel(NetworkViewModel.init) { model in
            NetworkContent(model: model)
        }
        .navigationTitle("Réseau de soins")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct NetworkContent: View {
    @Bindable var model: NetworkViewModel
    @Environment(\.openURL) private var openURL

    var body: some View {
        VStack(spacing: 0) {
            filters
            if let error = model.error {
                StaleDataBanner(error: error) { Task { await model.load() } }.padding(.horizontal, DS.Spacing.l)
            }
            if model.mode == .map && model.hasCoordinates { map } else { list }
        }
        .screenBackground()
        .searchable(text: $model.search, prompt: Text("Nom, ville, spécialité"))
        .onSubmit(of: .search) { Task { await model.load() } }
        .task { await model.load() }
        .onChange(of: model.type) { _, _ in Task { await model.load() } }
        .onChange(of: model.radiusKm) { _, _ in Task { await model.load() } }
        .quickLookPreview($model.previewURL)
        .sheet(item: $model.selected) { provider in
            ProviderDetail(provider: provider).presentationDetents([.medium])
        }
        .toolbar {
            if model.hasCoordinates { ToolbarItem(placement: .topBarLeading) {
                Picker("Affichage", selection: $model.mode) {
                    ForEach(NetworkViewModel.Mode.allCases) { Text($0.label).tag($0) }
                }
                .pickerStyle(.segmented)
                .frame(width: 140)
            } }
            ToolbarItem(placement: .topBarTrailing) {
                Button { Task { await model.downloadPDF() } } label: {
                    if model.isDownloadingPDF { ProgressView() } else { Label("Télécharger en PDF", systemImage: "arrow.down.doc") }
                }
                .accessibilityIdentifier("network.pdf")
            }
        }
    }

    private var filters: some View {
        VStack(spacing: DS.Spacing.s) {
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: DS.Spacing.s) {
                    chip(selected: model.type == nil, label: String(localized: "Tous", bundle: .appLanguage), symbol: "square.grid.2x2") { model.type = nil }
                    ForEach(ProviderType.allCases.filter { $0 != .other }) { type in
                        chip(selected: model.type == type, label: type.label, symbol: type.symbol) { model.type = type }
                            .accessibilityIdentifier("network.type.\(type.rawValue)")
                    }
                }
                .padding(.horizontal, DS.Spacing.l)
            }
            if model.hasCoordinates { HStack {
                Button { Task { await model.locateMe() } } label: {
                    if model.isLocating { ProgressView() } else {
                        Label(model.userLocation == nil ? "Autour de moi" : "Position mise à jour", systemImage: "location")
                    }
                }
                .font(.callout.weight(.semibold))
                Spacer()
                if model.userLocation != nil {
                    Picker("Distance", selection: $model.radiusKm) {
                        ForEach(NetworkViewModel.radiusOptions, id: \.self) { radius in
                            Text(radius.map { "\($0) km" } ?? String(localized: "Toute distance", bundle: .appLanguage)).tag(radius)
                        }
                    }
                    .pickerStyle(.menu)
                }
            }
            .padding(.horizontal, DS.Spacing.l) }
            if model.locationDenied {
                Text("Localisation refusée. Activez-la dans Réglages pour trier par distance.")
                    .font(.caption).foregroundStyle(DS.Palette.textSecondary).padding(.horizontal, DS.Spacing.l)
            }
        }
        .padding(.vertical, DS.Spacing.s)
    }

    private func chip(selected: Bool, label: String, symbol: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Label(label, systemImage: symbol)
                .font(.subheadline.weight(.semibold))
                .padding(.horizontal, DS.Spacing.m).padding(.vertical, DS.Spacing.s)
                .foregroundStyle(selected ? DS.Palette.onPrimary : DS.Palette.textPrimary)
                .background(selected ? DS.Palette.primary : DS.Palette.surface, in: Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(selected ? .isSelected : [])
    }

    private var list: some View {
        ScrollView {
            LazyVStack(spacing: DS.Spacing.m) {
                if model.providers.isEmpty {
                    if model.isLoading {
                        ForEach(0..<4, id: \.self) { _ in SkeletonCard(lines: 2) }
                    } else {
                        EmptyStateView(title: String(localized: "Aucun prestataire", bundle: .appLanguage), message: String(localized: "Modifiez les filtres ou élargissez la distance.", bundle: .appLanguage), symbol: "mappin.slash")
                    }
                }
                ForEach(model.providers) { provider in
                    ProviderRow(provider: provider)
                        .onTapGesture { model.selected = provider }
                        .accessibilityAddTraits(.isButton)
                        .accessibilityIdentifier("network.provider.\(provider.id)")
                }
            }
            .padding(DS.Spacing.l)
        }
        .refreshable { await Task { await model.load() }.value }
    }

    private var map: some View {
        Map(initialPosition: .region(MKCoordinateRegion(center: model.userLocation ?? NetworkViewModel.dakar, latitudinalMeters: 20_000, longitudinalMeters: 20_000))) {
            if let user = model.userLocation {
                Annotation("Vous", coordinate: user) {
                    Circle().fill(DS.Palette.info).frame(width: 14, height: 14).overlay(Circle().stroke(.white, lineWidth: 3))
                }
            }
            ForEach(model.providers.filter { $0.coordinate != nil }) { provider in
                Annotation(provider.name, coordinate: provider.coordinate!) {
                    Button { model.selected = provider } label: {
                        Image(systemName: provider.type.symbol)
                            .font(.caption.weight(.bold))
                            .foregroundStyle(.white)
                            .frame(width: 30, height: 30)
                            .background(DS.Palette.teal, in: Circle())
                            .overlay(Circle().stroke(DS.Palette.mint, lineWidth: 2))
                    }
                    .accessibilityLabel(Text("\(provider.name), \(provider.type.label)"))
                }
            }
        }
        .mapControls { MapCompass(); MapScaleView() }
    }
}

private struct ProviderRow: View {
    let provider: Provider

    var body: some View {
        HStack(alignment: .top, spacing: DS.Spacing.m) {
            Image(systemName: provider.type.symbol)
                .foregroundStyle(DS.Palette.accent)
                .frame(width: 40, height: 40)
                .background(DS.Palette.accentSoft, in: RoundedRectangle(cornerRadius: DS.Radius.s))
            VStack(alignment: .leading, spacing: DS.Spacing.xxs) {
                Text(provider.name).font(.subheadline.weight(.semibold))
                Text(provider.specialty ?? provider.type.label).font(.caption).foregroundStyle(DS.Palette.textSecondary)
                Text("\(provider.address), \(provider.city)").font(.caption).foregroundStyle(DS.Palette.textSecondary)
                if provider.tiersPayant {
                    StatusBadge(text: String(localized: "Tiers-payant", bundle: .appLanguage), tone: .success)
                }
            }
            Spacer()
            if let distance = provider.distanceMeters {
                Text(Measurement(value: Double(distance), unit: UnitLength.meters).formatted(.measurement(width: .abbreviated, usage: .road).locale(AppLanguage.locale)))
                    .font(.caption.monospacedDigit())
                    .foregroundStyle(DS.Palette.textSecondary)
            }
        }
        .card(padding: DS.Spacing.m)
        .accessibilityElement(children: .combine)
    }
}

private struct ProviderDetail: View {
    let provider: Provider
    @Environment(\.openURL) private var openURL

    var body: some View {
        VStack(alignment: .leading, spacing: DS.Spacing.m) {
            Text(provider.name).font(DS.Typography.title)
            Text(provider.specialty ?? provider.type.label).foregroundStyle(DS.Palette.textSecondary)
            InfoRow(label: String(localized: "Adresse", bundle: .appLanguage), value: "\(provider.address), \(provider.city)")
            if let hours = provider.openingHours { InfoRow(label: String(localized: "Horaires", bundle: .appLanguage), value: hours) }
            InfoRow(label: String(localized: "Tiers-payant", bundle: .appLanguage), value: provider.tiersPayant ? String(localized: "Accepté", bundle: .appLanguage) : String(localized: "Non", bundle: .appLanguage))
            if provider.locationApproximate == true {
                Label("Position approximative sur la carte : l'itinéraire utilise l'adresse.", systemImage: "mappin.and.ellipse")
                    .font(.footnote).foregroundStyle(DS.Palette.textSecondary)
            }
            HStack(spacing: DS.Spacing.m) {
                if let phone = provider.phone, let url = URL(string: "tel:\(phone.filter { $0.isNumber || $0 == "+" })") {
                    Button { openURL(url) } label: { Label("Appeler", systemImage: "phone.fill") }
                        .buttonStyle(.secondary)
                }
                Button {
                    if let coordinate = provider.coordinate, provider.locationApproximate != true {
                        let item = MKMapItem(placemark: MKPlacemark(coordinate: coordinate))
                        item.name = provider.name
                        item.openInMaps(launchOptions: [MKLaunchOptionsDirectionsModeKey: MKLaunchOptionsDirectionsModeDriving])
                    } else if let url = URL(string: "maps://?q=" + "\(provider.name), \(provider.address), \(provider.city)".addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed)!) {
                        openURL(url)
                    }
                } label: { Label("Itinéraire", systemImage: "arrow.triangle.turn.up.right.diamond.fill") }
                    .buttonStyle(.primary)
            }
            Spacer()
        }
        .padding(DS.Spacing.xl)
    }
}

extension Provider {
    var coordinate: CLLocationCoordinate2D? {
        guard let latitude, let longitude else { return nil }
        return CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
    }
}

extension Provider: Hashable {
    func hash(into hasher: inout Hasher) { hasher.combine(id) }
}
