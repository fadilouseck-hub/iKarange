package sn.assurplus.app.designsystem

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Article
import androidx.compose.material.icons.automirrored.outlined.NoteAdd
import androidx.compose.material.icons.automirrored.outlined.Send
import androidx.compose.material.icons.automirrored.rounded.ArrowBackIos
import androidx.compose.material.icons.automirrored.rounded.KeyboardArrowRight
import androidx.compose.material.icons.outlined.*
import androidx.compose.material.icons.rounded.*
import androidx.compose.ui.graphics.vector.ImageVector

/**
 * SF Symbol names used by the iOS app → closest Material icon. Feature code keeps the iOS names (fixtures and
 * models send them too, e.g. claim types), so both apps stay readable side by side. Unknown names fall back to a
 * neutral icon instead of crashing.
 */
fun sym(name: String): ImageVector = when (name) {
    "arrow.down.doc" -> Icons.Outlined.FileDownload
    "arrow.triangle.2.circlepath" -> Icons.Rounded.Sync
    "arrow.triangle.turn.up.right.diamond.fill" -> Icons.Rounded.Directions
    "arrow.up" -> Icons.Rounded.ArrowUpward
    "camera.fill" -> Icons.Rounded.PhotoCamera
    "arrow.up.circle.fill" -> Icons.Rounded.ArrowCircleUp
    "archivebox" -> Icons.Outlined.Archive
    "banknote" -> Icons.Outlined.Payments
    "bed.double" -> Icons.Outlined.Bed
    "bell" -> Icons.Outlined.Notifications
    "bell.badge" -> Icons.Outlined.NotificationsActive
    "building.2" -> Icons.Outlined.Business
    "camera.viewfinder" -> Icons.Outlined.DocumentScanner
    "camera.circle.fill" -> Icons.Rounded.PhotoCamera
    "checkmark" -> Icons.Rounded.Check
    "checkmark.circle" -> Icons.Outlined.CheckCircle
    "checkmark.circle.fill" -> Icons.Rounded.CheckCircle
    "checkmark.seal" -> Icons.Outlined.Verified
    "checkmark.seal.fill" -> Icons.Rounded.Verified
    "chevron.right" -> Icons.AutoMirrored.Rounded.KeyboardArrowRight
    "chevron.left" -> Icons.AutoMirrored.Rounded.ArrowBackIos
    "chevron.up.chevron.down" -> Icons.Rounded.UnfoldMore
    "circle.lefthalf.filled" -> Icons.Outlined.Contrast
    "clock" -> Icons.Outlined.Schedule
    "creditcard" -> Icons.Outlined.CreditCard
    "cross" -> Icons.Outlined.LocalHospital
    "cross.case" -> Icons.Outlined.MedicalServices
    "doc" -> Icons.Outlined.Description
    "doc.badge.clock" -> Icons.Outlined.PendingActions
    "doc.badge.plus" -> Icons.AutoMirrored.Outlined.NoteAdd
    "doc.on.doc" -> Icons.Outlined.ContentCopy
    "doc.plaintext" -> Icons.AutoMirrored.Outlined.Article
    "doc.richtext" -> Icons.Outlined.Image
    "doc.text" -> Icons.Outlined.Description
    "doc.text.magnifyingglass" -> Icons.Outlined.FindInPage
    "envelope" -> Icons.Outlined.Email
    "exclamationmark.arrow.circlepath" -> Icons.Rounded.SyncProblem
    "exclamationmark.triangle" -> Icons.Outlined.Warning
    "exclamationmark.triangle.fill" -> Icons.Rounded.Warning
    "eyeglasses" -> Icons.Outlined.Visibility
    "globe" -> Icons.Outlined.Language
    "hand.raised" -> Icons.Outlined.PanTool
    "heart.text.square" -> Icons.Outlined.MonitorHeart
    "house.fill" -> Icons.Rounded.Home
    "info.circle.fill" -> Icons.Rounded.Info
    "iphone.gen3" -> Icons.Outlined.PhoneAndroid
    "key" -> Icons.Outlined.Key
    "location" -> Icons.Outlined.NearMe
    "lock.doc" -> Icons.Outlined.EnhancedEncryption
    "lock.shield" -> Icons.Outlined.Security
    "magnifyingglass" -> Icons.Rounded.Search
    "map" -> Icons.Outlined.Map
    "map.fill" -> Icons.Rounded.Map
    "mappin" -> Icons.Rounded.Place
    "mappin.and.ellipse" -> Icons.Outlined.PinDrop
    "mappin.slash" -> Icons.Outlined.LocationOff
    "minus.circle" -> Icons.Outlined.RemoveCircleOutline
    "moon" -> Icons.Outlined.DarkMode
    "mouth" -> Icons.Outlined.Mood
    "opticid", "faceid" -> Icons.Outlined.Face
    "paperplane" -> Icons.AutoMirrored.Outlined.Send
    "person.3" -> Icons.Outlined.Groups
    "person.badge.minus" -> Icons.Outlined.PersonRemove
    "person.badge.plus" -> Icons.Outlined.PersonAdd
    "person.crop.circle.fill" -> Icons.Rounded.AccountCircle
    "person.text.rectangle" -> Icons.Outlined.Badge
    "phone" -> Icons.Outlined.Phone
    "phone.fill" -> Icons.Rounded.Phone
    "pills" -> Icons.Outlined.Medication
    "plus" -> Icons.Rounded.Add
    "plus.circle.fill" -> Icons.Rounded.AddCircle
    "qrcode" -> Icons.Rounded.QrCode2
    "rays" -> Icons.Outlined.Flare
    "shield.lefthalf.filled.badge.checkmark" -> Icons.Outlined.VerifiedUser
    "sparkles" -> Icons.Outlined.AutoAwesome
    "square.and.arrow.up" -> Icons.Outlined.IosShare
    "square.and.pencil" -> Icons.Outlined.EditNote
    "square.grid.2x2" -> Icons.Outlined.GridView
    "stethoscope" -> Icons.Outlined.HealthAndSafety
    "sun.max" -> Icons.Outlined.LightMode
    "syringe" -> Icons.Outlined.Vaccines
    "testtube.2" -> Icons.Outlined.Science
    "touchid" -> Icons.Outlined.Fingerprint
    "trash" -> Icons.Outlined.Delete
    "tray" -> Icons.Outlined.Inbox
    "tray.full" -> Icons.Outlined.AllInbox
    "viewfinder" -> Icons.Outlined.CropFree
    "water.waves" -> Icons.Outlined.Waves
    "wifi.slash" -> Icons.Outlined.WifiOff
    "xmark" -> Icons.Rounded.Close
    "xmark.octagon" -> Icons.Outlined.Dangerous
    "xmark.octagon.fill" -> Icons.Rounded.Dangerous
    "photo" -> Icons.Outlined.Image
    "folder" -> Icons.Outlined.Folder
    "ellipsis" -> Icons.Rounded.MoreHoriz
    else -> Icons.Outlined.Circle
}
