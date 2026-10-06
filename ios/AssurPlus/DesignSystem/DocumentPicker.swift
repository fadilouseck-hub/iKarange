import PhotosUI
import SwiftUI
import UniformTypeIdentifiers
import VisionKit

/// A picked document, already compressed for upload, with an optional preview image.
struct PickedDocument: Sendable {
    let file: UploadFile
    let preview: UIImage?
}

enum DocumentSource {
    /// Builds an upload from images (document camera pages, photo): merged, JPEG, ≤1600 px.
    static func fromImages(_ images: [UIImage], baseName: String) -> PickedDocument? {
        guard let merged = ImageCompressor.merge(pages: images), let data = ImageCompressor.jpeg(from: merged) else { return nil }
        return PickedDocument(file: UploadFile(data: data, fileName: "\(baseName).jpg", mimeType: "image/jpeg"), preview: UIImage(data: data))
    }

    static func fromFile(_ url: URL, baseName: String) -> PickedDocument? {
        let scoped = url.startAccessingSecurityScopedResource()
        defer { if scoped { url.stopAccessingSecurityScopedResource() } }
        guard let data = try? Data(contentsOf: url) else { return nil }
        if UTType(filenameExtension: url.pathExtension)?.conforms(to: .pdf) == true {
            return PickedDocument(file: UploadFile(data: data, fileName: "\(baseName).pdf", mimeType: "application/pdf"), preview: nil)
        }
        guard let image = UIImage(data: data) else { return nil }
        return fromImages([image], baseName: baseName)
    }
}

/// Offers: document scanner (VisionKit, edge detection, multi-page), photo library, Files —
/// and, in MockAPI, a bundled sample invoice so the flow works in the Simulator and UI tests.
struct DocumentPickerModifier: ViewModifier {
    @Binding var isPresented: Bool
    var title: String
    var baseName: String
    var allowsPDF = true
    let onPick: (PickedDocument) -> Void

    @Environment(AppEnvironment.self) private var env
    @State private var showScanner = false
    @State private var showPhotos = false
    @State private var showFiles = false
    @State private var photoItem: PhotosPickerItem?

    func body(content: Content) -> some View {
        content
            .confirmationDialog(title, isPresented: $isPresented, titleVisibility: .visible) {
                if VNDocumentCameraViewController.isSupported {
                    Button("Scanner le document") { showScanner = true }
                }
                Button("Choisir une photo") { showPhotos = true }
                Button("Importer un fichier") { showFiles = true }
                if env.isMock {
                    Button("Utiliser une facture d'exemple") {
                        if let picked = DocumentSource.fromImages([MockDocuments.sampleInvoice()], baseName: baseName) { onPick(picked) }
                    }
                }
                Button("Annuler", role: .cancel) {}
            }
            .fullScreenCover(isPresented: $showScanner) {
                DocumentScannerView { pages in
                    showScanner = false
                    if !pages.isEmpty, let picked = DocumentSource.fromImages(pages, baseName: baseName) { onPick(picked) }
                }
                .ignoresSafeArea()
            }
            .photosPicker(isPresented: $showPhotos, selection: $photoItem, matching: .images)
            .onChange(of: photoItem) { _, item in
                guard let item else { return }
                Task {
                    if let data = try? await item.loadTransferable(type: Data.self), let image = UIImage(data: data),
                       let picked = DocumentSource.fromImages([image], baseName: baseName) {
                        onPick(picked)
                    }
                    photoItem = nil
                }
            }
            .fileImporter(isPresented: $showFiles, allowedContentTypes: allowsPDF ? [.image, .pdf] : [.image]) { result in
                if case .success(let url) = result, let picked = DocumentSource.fromFile(url, baseName: baseName) { onPick(picked) }
            }
    }
}

extension View {
    func documentPicker(isPresented: Binding<Bool>, title: String, baseName: String, allowsPDF: Bool = true, onPick: @escaping (PickedDocument) -> Void) -> some View {
        modifier(DocumentPickerModifier(isPresented: isPresented, title: title, baseName: baseName, allowsPDF: allowsPDF, onPick: onPick))
    }
}

/// VisionKit document camera: automatic edge detection, perspective correction, multiple pages.
struct DocumentScannerView: UIViewControllerRepresentable {
    let completion: ([UIImage]) -> Void

    func makeUIViewController(context: Context) -> VNDocumentCameraViewController {
        let controller = VNDocumentCameraViewController()
        controller.delegate = context.coordinator
        return controller
    }

    func updateUIViewController(_ uiViewController: VNDocumentCameraViewController, context: Context) {}

    func makeCoordinator() -> Coordinator { Coordinator(completion: completion) }

    final class Coordinator: NSObject, VNDocumentCameraViewControllerDelegate {
        let completion: ([UIImage]) -> Void
        init(completion: @escaping ([UIImage]) -> Void) { self.completion = completion }

        func documentCameraViewController(_ controller: VNDocumentCameraViewController, didFinishWith scan: VNDocumentCameraScan) {
            completion((0..<scan.pageCount).map { scan.imageOfPage(at: $0) })
        }

        func documentCameraViewControllerDidCancel(_ controller: VNDocumentCameraViewController) { completion([]) }

        func documentCameraViewController(_ controller: VNDocumentCameraViewController, didFailWithError error: Error) { completion([]) }
    }
}
