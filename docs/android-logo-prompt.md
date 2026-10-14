# Prompt — Apply the new assur+ logo to the Android app

> Paste everything below the line into the Android Claude Code session (working directory: the iKarangue repo).

---

The Assur Plus brand has new logos in `Logos/` at the repo root. The iOS app was updated first (commit "iOS: new assur+ logo, app icon and brand colours"). Bring the Android app (`android/`) to the same result. Do **not** edit `Logos/`; treat those files as the source of truth.

## Source files

- `Logos/logo-assur-plus-icon.svg`: square mark, 96×96. Green `#00C896` rounded square, white "+" (rounded bars), dark `#003D2E` heartbeat line across the "+" (stroke 3.5, 90 % opacity).
- `Logos/logo-assur-plus.svg`: horizontal logo, the mark + wordmark **"assur+"** (Inter ExtraBold/800, `#0B1F1A`, the "+" in `#00C896`, tight letter-spacing) + tagline **"SANTÉ POUR TOUS"** (Inter Medium, 11 px, `#4A5A54`, letter-spacing 2).
- `Logos/logo-assur-plus-forest.svg`: alternative dark-green editorial variant. **Not used in the app.**

## Changes to make

1. **Brand colours**: in `app/src/main/assets/tenant.json` and `app/src/main/res/values/colors.xml` (plus `values-night` if it repeats them), use the same values as iOS `ios/AssurPlus/Tenant/Tenant.plist`:
   - `brandAccent` / `brand_accent` = `00C896`
   - `brandDark` / `brand_dark` = `003D2E`
   - `brandDarkSecondary` = `0B5A45`
   - `accentOnLight` = `007A5E` (needed for contrast: `00C896` on white is only 2.2:1; `007A5E` is 5.3:1)
2. **Wordmark and tagline**: in `tenant.json`, set `"wordmark": "assur+"` and add `"tagline": "Santé pour tous"`. Add `tagline: String = ""` to `tenant/Tenant.kt` (with a default so older JSON still parses).
3. **Logo mark as a vector drawable**: convert `logo-assur-plus-icon.svg` to `res/drawable/brand_logo.xml` (VectorDrawable, viewport 96×96, keep the rounded corners, the "+" and the heartbeat path exactly).
4. **`BrandMark` composable** (`app/RootView.kt`): replace the hand-drawn "+" with `Image(painterResource(R.drawable.brand_logo))`. Next to it, show a `Wordmark` composable: `"assur"` in `textPrimary` + `"+"` in `DS.Palette.mint`, Inter ExtraBold, size ≈ 0.5 × mark size, letter-spacing ≈ −0.035 em. Under it, show the tagline in uppercase (size ≈ 0.14 × mark, Medium, letter-spacing ≈ 0.03 × mark, `textSecondary`) when `tagline` is not empty. Add a `showsTagline` parameter (default true). Keep the single merged content description = `displayName`.
5. **Member card** (`features/card/CardScreen.kt`): replace the plain wordmark text in the card header with the 26 dp logo mark + `Wordmark(size = 20.sp, color = White)`.
6. **Launcher icon (adaptive)**: background = `#00C896`; foreground = white "+" and `#003D2E` heartbeat, taken from the icon SVG, scaled to sit inside the 66 dp safe zone of the 108 dp canvas. **Monochrome** layer = the "+" and heartbeat only, as a single-colour shape (themed icons on Android 13+). Keep `ic_launcher.xml` and add `ic_launcher_round` if the manifest references it.
7. **Splash screen** (`values-v31/themes.xml`, `drawable/splash_icon.xml`): icon background `#00C896`, icon = the same foreground as the launcher.
8. **Play Store icon**: export a 512×512 opaque PNG of the full-bleed icon to `android/store/icon-512.png`, and mention it in the hand-off to ChatGPT (it uploads the store listing assets; do not upload anything yourself).
9. **QR code centre** (`StyledQRCode.kt`): draw the **logo mark** (`R.drawable.brand_logo`) in the centre square instead of the hand-drawn "+", like iOS (`ios/AssurPlus/Features/Card/StyledQRCode.swift`: `Style.logo`, drawn into the cleared centre area; the old "+" stays as a fallback). Keep error correction "H", the logo at ≤ 22 % of the symbol, and the 1-module white margin around it. Then **prove it still scans**: decode the rendered bitmap in a unit or instrumented test (ZXing or ML Kit) and check the payload equals the token.

## Check

- Build the MockAPI flavour and run it on an emulator. Take screenshots of: the launcher (normal + themed icons), the splash screen, the welcome/login screen, the dashboard, and the card screen. Check light and dark mode.
- Run the unit tests and the instrumented tests. Note: uncommitted demo-data changes in `android/app/src/main/assets/mock/` and `mock/*.kt` existed before this task. If a failure comes from them and not from the logo work, say so rather than "fixing" it.
- Commit only the files you changed for the logo, with a clear message.
