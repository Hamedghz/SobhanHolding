# Offline Gallery Android Template

This template contains the project skeleton for the offline product gallery and request manager described in the specification.
It is ready for you to add assets and business logic before building the APK.

## Project Highlights
- Jetpack Compose UI with a placeholder gallery screen and glassmorphism-inspired colors.
- Room, Apache POI, and Coil dependencies preconfigured.
- Assets directories for Excel files (`app/src/main/assets/excel`) and product images (`app/src/main/assets/images`).
- Kotlin source packages aligned with the proposed architecture (data, service, ui).

## Preparing Your Assets
1. Copy the product Excel file into `app/src/main/assets/excel`.
2. Place all product images in `app/src/main/assets/images`.
   - The naming convention should match your product codes (e.g., `12345.jpg`).
   - Any missing images will fall back to `ic_placeholder`.

## Creating a Distributable ZIP Package
You can package the template (after adding images/assets) into `offline_gallery_template.zip` using either method below.

### Option A: Cross-Platform Python Script
From the repository root run:
```bash
python tools/create_template_zip.py
```
This generates `offline_gallery_template.zip` next to the repository. Pass `--output` to control the destination.

### Option B: Windows PowerShell
If you prefer not to use Python, open Windows Terminal in the repository root and run:
```powershell
Compress-Archive -Path android_gallery_template -DestinationPath offline_gallery_template.zip -Force
```
The resulting archive can be copied to other machines before building the APK.

## Building on Windows 11

### Option A: Android Studio (Recommended)
1. Install [Android Studio](https://developer.android.com/studio) if you have not already.
2. Open Android Studio and select **Open an Existing Project**, then choose the `android_gallery_template` folder (or the folder you extracted from the generated ZIP).
3. Allow Android Studio to download the required Android SDK components when prompted.
4. If the Gradle wrapper is missing its JAR, let Android Studio run **File > Sync Project with Gradle Files**; it will download the wrapper automatically.
5. Once the Gradle sync completes, select **Build > Make Project** to ensure the project compiles.
6. To generate an APK, choose **Build > Build Bundle(s) / APK(s) > Build APK(s)**. The release/debug APK will be located in `app/build/outputs/apk/`.

### Option B: Command Line (Windows Terminal / PowerShell)
1. Install [Microsoft OpenJDK 17](https://learn.microsoft.com/java/openjdk/download) (or any JDK 17 distribution) and ensure `java -version` reports 17.x.
2. Install Gradle 8.5 from [gradle.org/install](https://gradle.org/install/) and add it to your PATH.
3. Open **Windows Terminal** and navigate to the project folder (either the repository checkout or an extracted ZIP):
   ```powershell
   cd C:\\Projects\\OfflineGallery
   ```
4. Generate the Gradle wrapper (only required once):
   ```powershell
   gradle wrapper
   ```
5. Use the wrapper to assemble the debug APK:
   ```powershell
   .\\gradlew.bat assembleDebug
   ```
6. The generated APK will be located at `app\\build\\outputs\\apk\\debug\\app-debug.apk`.

## Next Steps
- Replace the placeholder implementations in `service` and `ui` packages with real Excel parsing, Room database integration, and request management logic.
- Implement the Excel import workflow, request persistence, and export functions per the specification.
- Update tests and add CI workflows as needed.
