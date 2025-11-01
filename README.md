# ProductGallery

Offline product catalogue and request manager tailored for field agents who need to browse inventory, capture customer demand, and import master data without network access.

The repository now bundles both the modern Compose application (`app/`) and the legacy RecyclerView gallery (`legacygallery/`) so you can evaluate, reuse, or redistribute either experience from a single Android Studio project.

## Key Features
- **Excel catalogue import** with header validation, row-by-row progress updates, and automatic Room population so the UI is refreshed as soon as parsing completes.[^excel]
- **Offline-first gallery** backed by a JSON cache and Compose UI, ensuring previously imported products remain visible even after app restarts.[^gallery]
- **Request tracking** powered by Room flows so sales requests update instantly and can be extended with editing/export features.[^requests]
- **Image cache preparation** that clears and stages an on-device cache whenever a new spreadsheet is ingested, ready for future image hydration.[^images]
- **Persistent user preferences** (price mode, sales line) stored with DataStore for per-device personalisation.[^prefs]

[^excel]: `ExcelService`, `ImportScreen`, and the import state models orchestrate Excel ingestion and progress feedback.
[^gallery]: `ProductService`, its cache serializer, and `GalleryScreen` expose the offline catalogue in Compose.
[^requests]: `RequestService` feeds `RequestsViewModel`/`RequestsScreen` with a real-time list of captured requests.
[^images]: `ImageCacheManager` clears and prepares a cache directory alongside Excel imports.
[^prefs]: `UserPreferencesRepository` encapsulates the DataStore-backed preference state.

## Modules & Layout
```
app/
  build.gradle                # Compose-first offline product catalogue
  src/main/java/com/example/productgallery/
    ProductGalleryApp.kt      # Dependency container and Application entry point
    data/                     # Room entities, DAOs, DataStore abstractions, cache models
    domain/                   # Excel import pipeline, services, error reporting
    ui/                       # Compose navigation graph and screen implementations
legacygallery/
  build.gradle                # Legacy RecyclerView gallery that reads JSON assets
  src/main/java/com/sobhan/offlinegallery/
    MainActivity.kt           # Activity + adapter wired to asset-backed data models
    ui/components/            # Legacy UI widgets and theming helpers
  src/main/assets/            # JSON catalogue stub ready for replacement
ANDROID_APP_SPEC.md           # Functional specification used by the test suite
tools/create_template_zip.py  # Helper to package the legacy gallery into a distributable zip
```

## Quick Setup & Run
1. **Install prerequisites**
   - Android Studio Giraffe (or newer) with Android SDK 34
   - JDK 17 (bundled with Android Studio)
2. **Open the project**
   - `File` → `Open...` and choose the repository root (`ProductGallery`)
   - Let Gradle sync dependencies on first launch
3. **Run the Compose app (`app/`)**
   - Select the `app` configuration from the run dropdown and click ▶️ `Run`
   - Command line: `./gradlew :app:assembleDebug`
   - APK output: `app/build/outputs/apk/debug/app-debug.apk`
4. **Run the legacy gallery (`legacygallery/`)**
   - Select the `legacygallery` configuration and click ▶️ `Run`
   - Command line: `./gradlew :legacygallery:assembleDebug`
   - APK output: `legacygallery/build/outputs/apk/debug/legacygallery-debug.apk`

## Excel Catalogue Requirements
- Provide a `.xlsx` file with the following header row (exact order):
  `Product Code`, `Description`, `Product Variant Index`, `Stock Quantity`, `Zahedan Price`, `Other Cities Price`, `Line`, `Brand Name`, `Customer Names`
- Numeric cells are parsed for quantities and prices; empty rows are skipped automatically
- The importer groups rows by product code, sorts variants, saves entities to Room, and updates the cached JSON catalogue in one transaction
- Import progress emits human-readable status messages so agents see how many rows have been processed

## Offline Data & Requests
- Room database (`products`, `variants`, `requests`) stores imported catalogue data and captured customer requests
- Product snapshots are mirrored to `filesDir/products.json` so the gallery boots instantly even without Room warm-up
- Requests flow to the UI via `Flow`, ensuring the requests tab reacts in real time to insertions or deletions
- Upcoming image support can rely on `cacheDir/images/` prepared by the cache manager after every import

- Legacy gallery reads static assets from `legacygallery/src/main/assets/catalog/index.json`, so you can swap the JSON payload without touching Kotlin sources.

## Package the Legacy Template
- Quickly build a distributable archive of the legacy module:
  ```bash
  python tools/create_template_zip.py --output dist/legacy_gallery_template
  ```
- The script creates `dist/legacy_gallery_template.zip` containing the `legacygallery/` project tree ready to share with agents who only need the lightweight gallery.

## Testing
- Kotlin unit tests can be executed with the standard Gradle task:
  ```bash
  ./gradlew test
  ```
- Specification smoke tests (used in CI) run via:
  ```bash
  pytest tests/
  ```

## License
Released under the [MIT License](LICENSE).
