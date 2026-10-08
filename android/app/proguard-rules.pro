# kotlinx.serialization looks serializers up reflectively; keep them in release builds.
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.**
-keepclassmembers class sn.assurplus.app.** {
    *** Companion;
}
-keepclasseswithmembers class sn.assurplus.app.** {
    kotlinx.serialization.KSerializer serializer(...);
}
-keep,includedescriptorclasses class sn.assurplus.app.**$$serializer { *; }

-dontwarn okhttp3.internal.platform.**
-dontwarn org.conscrypt.**
-dontwarn org.bouncycastle.**
-dontwarn org.openjsse.**
