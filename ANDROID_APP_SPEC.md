# Android Product Gallery & Offline Request App Specification

## 1. Overview
- **Platform**: Android (minimum SDK 26, target SDK 34) using Kotlin, Jetpack Compose, and Material 3.
- **Purpose**: Deliver a self-contained offline-capable product gallery and request management tool for sales teams.
- **Key Features**: Excel-driven catalog import, cached product images, glassmorphism UI, Room-backed offline requests, export to CSV/JSON, centralized error handling, and state management.
- **Operating Modes**: Works fully offline; when network available it may optionally sync exported files manually.

## 2. User Personas & Scenarios
| Persona | Goals | Key Workflows |
| --- | --- | --- |
| **Sales Representative** | Browse catalog offline, prepare customer requests, export requests. | Launch app → choose price mode → filter gallery → inspect variants → add request → export summary. |
| **Sales Manager** | Maintain catalog accuracy, ensure team has updated pricing. | Open Update File → import new Excel → resolve validation issues → distribute device with updated data. |

## 3. Application Architecture
```
┌───────────────────────────────┐
│           UI Layer            │
│ Jetpack Compose surfaces:     │
│ - GalleryScreen               │
│ - RequestScreen               │
│ - ImportScreen                │
│ - Dialogs/Sheets/Toasts       │
│   (Glassmorphism styling)     │
├───────────┬───────────────────┤
│ Service   │   Shared State    │
│ Layer     │ (ViewModels/Flows)│
│ - ProductService              │
│ - ExcelService                │
│ - RequestService              │
│ - ErrorHandler                │
│ - ImageCacheManager           │
├───────────┴───────────────────┤
│            Data Layer         │
│ - Room (Requests DB)          │
│ - ProductStore (Cached JSON)  │
│ - FileSystem (Excel, images)   │
│ - Preferences (user choices)  │
└───────────────────────────────┘
```

### 3.1 UI Layer Responsibilities
- Compose screens observe state from ViewModels using Kotlin Flows.
- Integrates Coil for image loading with shimmer placeholders and fallback assets.
- Provides global search, filter chips, sorting dropdowns, and navigation between Gallery and Requests.
- Displays modal sheets for variant selection and request editing.
- Includes a `TopLevelScaffold` that hosts snackbar/error banners supplied by `ErrorHandler`.

### 3.2 Service Layer Responsibilities
- **ProductService**
  - Maintains in-memory list of `ProductWithVariants`.
  - Exposes filter/sort operations and search suggestions.
  - Logs missing image filenames to local file `logs/missing_images.txt`.
- **ExcelService**
  - Validates file headers (columns A–I) and data integrity before parsing.
  - Implements coroutine-based importer with `Mutex` lock to prevent concurrent jobs.
  - Emits progress via `Flow<ImportProgress>` (0–100%).
- **RequestService**
  - Provides CRUD API for Room entities and handles CSV/JSON export.
  - Ensures referential integrity using foreign key references to product cache.
- **ErrorHandler**
  - Collects `AppError` objects and exposes them to UI as `SharedFlow` for consistent messaging.
- **ImageCacheManager**
  - Prefetches images to `context.cacheDir/images` on import and caches resizing metadata.

### 3.3 Data Layer Responsibilities
- **Room Database**
  - Entities: `RequestEntity`, `ProductEntity` (flattened variant info), `VariantEntity`.
  - DAO: `RequestDao` with composite index `(product_code, variant_index)`.
  - Database exports to backup file on request.
- **ProductStore**
  - JSON file stored at `files/products.json` generated after Excel import for fast reloads.
- **Excel Storage**
  - Latest imported Excel stored under `files/imports/<timestamp>.xlsx` with metadata for auditing.
- **Preferences**
  - `DataStore` to remember last selected price mode and sales line.

## 4. Data Models
### 4.1 Kotlin Data Classes
```kotlin
data class Product(
    val productCode: String,
    val description: String,
    val line: SalesLine,
    val brand: String,
    val imageFile: String?,
    val variants: List<ProductVariant>
)

data class ProductVariant(
    val variantIndex: Int,
    val stockQuantity: Int,
    val zahedanPrice: BigDecimal,
    val otherCitiesPrice: BigDecimal,
    val customerNames: List<String>
)

@Entity(
    tableName = "requests",
    foreignKeys = [
        ForeignKey(
            entity = VariantEntity::class,
            parentColumns = ["product_code", "variant_index"],
            childColumns = ["product_code", "variant_index"],
            onDelete = CASCADE
        )
    ],
    indices = [Index(value = ["product_code", "variant_index"])])
data class RequestEntity(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    @ColumnInfo(name = "product_code") val productCode: String,
    @ColumnInfo(name = "variant_index") val variantIndex: Int,
    val customer: String,
    val quantity: Int,
    val date: LocalDate,
    val notes: String?,
    val error: String?
)
```

### 4.2 Excel Schema Validation
- Required headers: `Product Code`, `Description`, `Product Variant Index`, `Stock Quantity`, `Zahedan Price`, `Other Cities Price`, `Line`, `Brand Name`, `Customer Names`.
- Validation rules:
  - `Product Code`: non-empty, unique across rows.
  - `Product Variant Index`: integer ≥ 1.
  - `Stock Quantity`: integer ≥ 0.
  - Prices: decimal ≥ 0.
  - `Line`: must be one of {A, B, C, D}.
  - `Customer Names`: optional comma-separated list trimmed of whitespace.
- On validation failure: `ExcelService` emits `ImportState.Error` with detail message and options to retry or cancel.

## 5. Workflows
### 5.1 Startup & Onboarding
1. Launch application.
2. Show price selection dialog (Zahedan vs Other Cities) → persisted in DataStore.
3. Show sales line selection (A/B/C/D) → defaults to previous selection.
4. Load cached `products.json` and images.
5. If cache missing, prompt for Excel import before showing gallery.

### 5.2 Gallery Browsing
1. Display `GalleryScreen` with search bar and filter chips (Line, Supplier, Brand).
2. Default sort by product code; allow sorting by description, brand, stock quantity via dropdown.
3. Product card shows image (with glassmorphism glass card), description, brand, available variants count, and stock badge.
4. Swiping horizontally inside card toggles between variants or uses vertical tab list.
5. Missing images show default placeholder and log entry.

### 5.3 Request Creation
1. Tap product → `ProductDetailScreen` with variant list.
2. Choose variant → `AddRequestSheet` opens with pre-filled date.
3. User selects customer from chips (from `customerNames`) or taps “Other” to input custom text.
4. Input quantity (validated ≥1) and optional notes.
5. Submit → `RequestService` inserts entity and returns ID; UI shows confirmation `Request #ID added for Product X`.

### 5.4 Request Management
- `RequestsScreen` displays list grouped by product with editing and deletion options.
- Each entry shows variant info, quantity, customer, date, notes, and error status (if any).
- Export actions: `Export CSV` and `Export JSON` trigger background job writing to `Documents/Requests_<timestamp>.csv`/`.json`.
- Provide share intent for exported file.

### 5.5 Excel Import Update Flow
1. User taps `Update File` menu item.
2. File picker returns `.xlsx` Uri.
3. `ExcelService` checks headers before parsing; if mismatch show modal with expected vs actual columns.
4. During import, UI shows progress indicator with stage text (Validating → Parsing → Writing cache → Refreshing gallery).
5. On success, service writes new `products.json`, caches images, and notifies `ProductService` to refresh state.
6. Requests remain intact; prompt user optionally to clear requests if data set drastically changed.

## 6. Error Handling & Logging
- All exceptions converted to `AppError` with category (Validation, IO, Database, Unknown).
- `ErrorHandler` provides localized user messages and logs detailed stack trace to `logs/app.log`.
- Missing image detection writes to `logs/missing_images.txt` with product code.
- Import cancellations or lock conflicts show `"Another import is already in progress."` message.

## 7. Offline Strategy & Caching
- Products cached as JSON with last-updated timestamp; rehydrated on startup before any network call.
- Images copied from `/assets/images` into cache directory to enable Coil offline loading.
- Requests stored locally; exports saved in shared storage for manual sharing.
- App gracefully handles absence of new Excel file by relying on latest cache.

## 8. UI/UX Guidelines (Glassmorphism)
- Background gradient (#0F172A → #1F2937) with blurred translucent cards (alpha 0.65, backdrop blur radius 20dp).
- Cards have 24dp rounded corners and subtle white borders at 10% opacity.
- Use Material 3 dynamic color tokens for text to maintain contrast.
- Shimmer placeholders using `Modifier.placeholder` until Coil image load completes.
- Loading overlays for long tasks; use `CircularProgressIndicator` blended with blur.

## 9. State Management & Concurrency
- ViewModels maintain `UiState` sealed classes: `Loading`, `Success`, `Empty`, `Error`.
- Import progress tracked with `StateFlow<ImportState>` to feed Compose progress UI.
- Use `Mutex` and `CoroutineScope(SupervisorJob())` for service operations to avoid cancellations cascading.
- Disk IO performed with `Dispatchers.IO`.

## 10. Performance Considerations
- Room queries use `Flow<List<RequestEntity>>` for automatic UI updates.
- Preprocess Excel rows into chunked batches for parsing to reduce memory spikes.
- Debounced search (300ms) to avoid recomputation on each keystroke.
- Image loading uses resizing (`size(240)`) and memory cache policies.
- Provide instrumentation tests for import flow and DAO operations.

## 11. Testing Strategy
- **Unit Tests**: Product filtering/sorting, Excel header validation, RequestService export formatting.
- **Instrumented Tests**: Room database migrations, Import workflow, UI state transitions.
- **Manual QA Checklist**:
  - Import valid/invalid Excel files and observe messaging.
  - Browse gallery with missing images to ensure placeholders.
  - Create, edit, delete requests; verify persistence across restarts.
  - Export files and open to verify schema.
  - Simulate concurrent import attempts.

## 12. Deployment & Distribution
- Sign release build with offline keystore.
- Bundle assets (baseline Excel & images) with initial APK for first-run experience.
- Provide update path: import new Excel from USB/Downloads.

## 13. Future Enhancements
- Optional cloud sync endpoint for sharing requests in real time.
- Analytics for most viewed products.
- Biometric authentication for sensitive pricing.

