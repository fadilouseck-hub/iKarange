// Assur Plus Android — root build. AGP 9 applies Kotlin itself ("built-in Kotlin"); only the Compose and
// serialization compiler plugins are declared.
plugins {
    alias(libs.plugins.android.application) apply false
    alias(libs.plugins.kotlin.compose) apply false
    alias(libs.plugins.kotlin.serialization) apply false
}
