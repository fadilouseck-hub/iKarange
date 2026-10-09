import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
}

android {
    namespace = "sn.assurplus.app"
    // Current AndroidX is published against 37; the app declares (targets) 36, the level Google Play requires.
    compileSdk = 37
    buildToolsVersion = "37.0.0"

    defaultConfig {
        // Same identity as the iOS bundle id.
        applicationId = "sn.assurplus.app"
        // Android 8.0: many insured members use older, entry-level phones.
        minSdk = 26
        targetSdk = 36
        versionCode = 1
        versionName = "1.0"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables { useSupportLibrary = true }
    }

    /*
     * Mirrors the iOS configurations: `mock` = MockAPI (in-process fake backend on JSON fixtures, demo accounts,
     * separate app id so both can be installed), `live` = the tenant backend. Release builds of `live` never run
     * on demo data.
     */
    flavorDimensions += "backend"
    productFlavors {
        create("mock") {
            dimension = "backend"
            applicationIdSuffix = ".mock"
            buildConfigField("boolean", "MOCK_API", "true")
            resValue("string", "app_name", "Assur Plus Mock")
        }
        create("live") {
            dimension = "backend"
            buildConfigField("boolean", "MOCK_API", "false")
            resValue("string", "app_name", "Assur Plus")
        }
    }

    /*
     * Release signing (Play upload key). The key and its passwords live outside git — the repository is public —
     * in `android/keystore.properties` (git-ignored): storeFile, storePassword, keyAlias, keyPassword.
     * Without that file, release builds are produced unsigned.
     */
    val keystoreFile = rootProject.file("keystore.properties")
    if (keystoreFile.exists()) {
        val keystore = Properties().apply { keystoreFile.inputStream().use { load(it) } }
        signingConfigs {
            create("upload") {
                storeFile = rootProject.file(keystore.getProperty("storeFile"))
                storePassword = keystore.getProperty("storePassword")
                keyAlias = keystore.getProperty("keyAlias")
                keyPassword = keystore.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            if (keystoreFile.exists()) signingConfig = signingConfigs.getByName("upload")
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }

    buildFeatures {
        compose = true
        buildConfig = true
        resValues = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // java.time back to API 26 edge cases (ISO-8601 parsing with offsets, formatting by locale).
        isCoreLibraryDesugaringEnabled = true
    }

    kotlin {
        compilerOptions {
            jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
            optIn.add("androidx.compose.material3.ExperimentalMaterial3Api")
            optIn.add("androidx.compose.foundation.layout.ExperimentalLayoutApi")
        }
    }

    packaging {
        resources { excludes += "/META-INF/{AL2.0,LGPL2.1}" }
    }

    testOptions {
        unitTests.isReturnDefaultValues = true
    }
}

dependencies {
    coreLibraryDesugaring(libs.desugar.jdk.libs)

    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.core.splashscreen)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.process)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.datastore.preferences)
    implementation(libs.androidx.biometric)
    implementation(libs.androidx.browser)

    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.material.icons.extended)

    implementation(libs.okhttp)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.kotlinx.coroutines.android)
    implementation(libs.coil.compose)
    implementation(libs.coil.network.okhttp)
    implementation(libs.zxing.core)
    implementation(libs.mlkit.document.scanner)
    implementation(libs.osmdroid)

    debugImplementation(libs.androidx.compose.ui.tooling)
    debugImplementation(libs.androidx.compose.ui.test.manifest)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)

    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
}
