# Porting iOS screens to Android — conventions

The Android app must **look and behave exactly like the iOS app** (`ios/AssurPlus`). Port each SwiftUI view
line by line: same sections, same order, same spacing tokens, same strings, same icons, same states.

## Where things are

| iOS | Android |
|---|---|
| `DS.Palette.x`, `DS.Spacing.x`, `DS.Radius.x`, `DS.Typography.x` | same names in `designsystem/Tokens.kt` (colours are `@Composable` getters) |
| iOS text styles `.headline`, `.callout`, `.caption`, `.title3`… | `DS.Typography.headline`, `.callout`, `.caption`, `.title3`… (iOS sizes) |
| `.font(.callout.weight(.semibold))` | `DS.Typography.callout.copy(fontWeight = FontWeight.SemiBold)` |
| `Image(systemName: "qrcode")` | `Icon(sym("qrcode"), null, tint = …)` — add missing names to `designsystem/Symbols.kt` |
| `.card()` / `.card(padding:)` | `Modifier.card()` or `Card { }` |
| `.buttonStyle(.primary)` / `.secondary` / `.primary(loading:)` | `PrimaryButton(title, onClick, loading = …)` / `SecondaryButton` |
| plain tinted `Button` | `TextLink(title, onClick)` |
| `StatusBadge`, `InfoRow`, `SectionHeader`, `MessageBanner`, `StepProgress`, `AmountTile`, `LabeledField` | same names in `designsystem/Components.kt` |
| `TextField` / `SecureField` inside `LabeledField` | `LabeledField(label, error = …) { InputText(value, onChange, placeholder, secure = …) }` |
| `Picker(.segmented)` | `SegmentedPicker` |
| `Toggle` | `ToggleRow` / `IOSSwitch` |
| `ProgressView(value:)` | `ProgressBar(value)` |
| `InitialsAvatar`, `ClaimRow` | `InitialsAvatar` in Components; `ClaimRow` in `features/home/HomeScreen.kt` (shared) |
| `EmptyStateView`, `ErrorStateView`, `SkeletonCard`, `LoadableContent` | `designsystem/StateViews.kt` |
| `List { Section { … } }` (inset grouped) / `Form` | `FormSection(header, footer) { FormRow(…) }` (`designsystem/Navigation.kt`) |
| `.navigationTitle` + large title, toolbar buttons | `Screen(title, onBack, actions = { GlassButton(symbol, onClick) })` |
| `.navigationBarTitleDisplayMode(.inline)` | `Screen(title, largeTitle = false, …)` |
| `.refreshable` | `Screen(onRefresh = { resource.refresh() })` |
| `.sheet` (full flows) | rendered by `MainTabView` from `router.presentedSheet` inside `SheetContainer`; inner screens use `Screen(largeTitle = false, leading = { GlassButton("xmark", onDismiss) })` |
| small `.sheet` / `.alert` / `.confirmationDialog` | `DSAlert(…)` / `ActionSheet(…)` / a full-height `Dialog` with `Screen` inside |
| `.documentPicker(…)` | `DocumentPicker(visible, onDismiss, title, baseName) { picked -> }` |
| `String(localized: "Bonjour \(name)")` / `Text("…")` | `t("Bonjour %@", name)` — **the key is the French text exactly as in Swift**, `%@` for values, `%lld` for integers. English comes from the iOS String Catalog (`tools/export_strings.py`). Strings not in the catalog: add the English to `tools/strings-overrides.json` and re-run the script |
| `Money.format`, `PhoneNumber`, `DateText`, `Percent` | `core/format/Formatters.kt` (amounts are `Long`) |
| `@Observable` view model | a plain class with `mutableStateOf` properties, created with `remember { … }`; launch work with `rememberCoroutineScope()` / `LaunchedEffect` |
| `RemoteResource(cache:key:) { … }` | `remember { RemoteResource(env.cache, CacheKey.x, X.serializer()) { env.api.x() } }` + `LaunchedEffect(Unit) { r.load() }` |
| `@Environment(AppEnvironment.self) env` | `val env = LocalEnv.current` (`env.api`, `env.session.can(Permission.x)`, `env.router`, `env.uploader`, `env.features`, `env.isMock`…) |
| `env.router.homePath.append(Route.family)` | `env.router.homePath.add(Route.Family)` or `env.router.push(Route.Family)` |
| `.accessibilityIdentifier("x.y")` | `Modifier.testTag("x.y")` (keep the same identifiers — UI tests use them) |
| QuickLook / ShareLink for PDFs | `Documents.save(bytes, name)` then `Documents.open(context, file)` / `Documents.share(…)` (`app/Services.kt`) |
| `openURL` | `Documents.openUrl(context, url)` |

## Rules

- No business logic: amounts, statuses, eligibility come from the API (same as iOS).
- Keep the test tags from the Swift `accessibilityIdentifier`s.
- Layout: screen content has 16 dp side padding (`Screen` default), sections are spaced `DS.Spacing.l`, matching iOS.
- Bottom content must stay clear of the floating tab bar: `Screen` handles it via `LocalBottomInset`.
- Build: `JAVA_HOME=/opt/homebrew/opt/openjdk@21 ./gradlew :app:assembleMockDebug` from `android/`.
- Reference screenshots of the iOS app (French, mock data): ask for the folder path in your task.
