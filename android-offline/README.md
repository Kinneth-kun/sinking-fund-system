# Staycation Savings Offline Android App

This Android wrapper embeds the standalone offline app from `../standalone`.

The APK does not need the PHP source code, MySQL, XAMPP, or a running local server. Data is stored inside the Android WebView storage on the phone. Use the app's Backup screen to export JSON copies of the data.

## Build

An APK has already been built in this workspace:

```text
build/outputs/apk/debug/app-debug.apk
```

Send that file to your Android phone and install it.

You can also open this `android-offline` folder in Android Studio and choose **Build > Build APK(s)**.

If Gradle is available from the command line:

```bash
gradle assembleDebug
```

The debug APK will be created at:

```text
app/build/outputs/apk/debug/app-debug.apk
```

Install that APK on the phone.

## Notes

- This is a debug-signed APK, so Android may ask you to allow installs from the file manager/browser you use.
- The app does not request internet access.
- If you uninstall the app, Android may remove its local data. Export backups from the Backup screen before uninstalling or moving phones.
