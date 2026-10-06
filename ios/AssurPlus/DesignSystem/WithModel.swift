import SwiftUI

/// Creates a feature view model once from the app environment, then renders `content` with it.
struct WithModel<Model: AnyObject, Content: View>: View {
    @Environment(AppEnvironment.self) private var env
    @State private var model: Model?
    let make: @MainActor (AppEnvironment) -> Model
    @ViewBuilder let content: (Model) -> Content

    init(_ make: @escaping @MainActor (AppEnvironment) -> Model, @ViewBuilder content: @escaping (Model) -> Content) {
        self.make = make
        self.content = content
    }

    var body: some View {
        if let model {
            content(model)
        } else {
            Color.clear.onAppear { model = make(env) }
        }
    }
}
