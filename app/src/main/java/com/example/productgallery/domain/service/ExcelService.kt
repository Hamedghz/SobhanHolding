package com.example.productgallery.domain.service

import android.content.ContentResolver
import android.net.Uri
import com.example.productgallery.data.local.ProductEntity
import com.example.productgallery.data.local.RequestDao
import com.example.productgallery.data.local.VariantEntity
import com.example.productgallery.data.model.Product
import com.example.productgallery.data.model.ProductVariant
import com.example.productgallery.data.model.SalesLine
import com.example.productgallery.domain.ImportState
import com.example.productgallery.domain.error.AppError
import com.example.productgallery.domain.error.ErrorHandler
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import org.apache.poi.ss.usermodel.CellType
import org.apache.poi.ss.usermodel.Row
import org.apache.poi.ss.usermodel.WorkbookFactory
import java.io.InputStream
import java.math.BigDecimal
import java.util.Locale

class ExcelService(
    private val contentResolver: ContentResolver,
    private val requestDao: RequestDao,
    private val productService: ProductService,
    private val imageCacheManager: ImageCacheManager,
    private val errorHandler: ErrorHandler,
    private val dispatcher: CoroutineDispatcher = Dispatchers.IO
) {
    private val mutex = Mutex()

    fun import(uri: Uri): Flow<ImportState> = flow {
        emit(ImportState.InProgress(progress = 0, message = "Starting validation"))
        try {
            mutex.withLock {
                val expectedHeaders = listOf(
                    "Product Code",
                    "Description",
                    "Product Variant Index",
                    "Stock Quantity",
                    "Zahedan Price",
                    "Other Cities Price",
                    "Line",
                    "Brand Name",
                    "Customer Names"
                )

                val inputStream = openInputStream(uri)
                val workbook = inputStream.use { stream -> WorkbookFactory.create(stream) }
                workbook.use { wb ->
                    val sheet = wb.getSheetAt(0)
                    val headerRow = sheet.getRow(sheet.firstRowNum)
                    val headers = expectedHeaders.indices.map { index ->
                        headerRow.getCell(index)?.stringCellValue?.trim().orEmpty()
                    }
                    if (headers != expectedHeaders) {
                        emit(ImportState.Error("Unexpected header row. Expected ${expectedHeaders.joinToString()}"))
                        return@withLock
                    }

                    val totalRows = (sheet.physicalNumberOfRows - 1).coerceAtLeast(0)
                    if (totalRows == 0) {
                        emit(ImportState.Error("Excel file is empty."))
                        return@withLock
                    }

                    val productMap = mutableMapOf<String, ProductBuilder>()

                    for (index in (sheet.firstRowNum + 1)..sheet.lastRowNum) {
                        val row = sheet.getRow(index) ?: continue
                        val productCode = row.readString(0)
                        if (productCode.isEmpty()) continue

                        val variantIndex = row.readInt(2)
                        val stockQuantity = row.readInt(3)
                        val zahedanPrice = row.readDecimal(4)
                        val otherCitiesPrice = row.readDecimal(5)
                        val salesLine = runCatching {
                            SalesLine.valueOf(row.readString(6).uppercase(Locale.US))
                        }.getOrElse {
                            error("Invalid sales line at row $index")
                        }
                        val brand = row.readString(7)
                        val customers = row.readString(8).takeIf { it.isNotBlank() }?.split(',')
                            ?.map { it.trim() } ?: emptyList()

                        val builder = productMap.getOrPut(productCode) {
                            ProductBuilder(
                                productCode = productCode,
                                description = row.readString(1),
                                line = salesLine,
                                brand = brand,
                                imageFile = null
                            )
                        }

                        builder.variants.add(
                            ProductVariant(
                                variantIndex = variantIndex,
                                stockQuantity = stockQuantity,
                                zahedanPrice = zahedanPrice,
                                otherCitiesPrice = otherCitiesPrice,
                                customerNames = customers
                            )
                        )

                        if (totalRows > 0) {
                            val processed = index - sheet.firstRowNum
                            val progress = (processed.toFloat() / totalRows * 100).toInt().coerceIn(0, 100)
                            emit(
                                ImportState.InProgress(
                                    progress,
                                    "Processed ${processed.coerceAtMost(totalRows)} of $totalRows rows"
                                )
                            )
                        }
                    }

                    val products = productMap.values.map { it.build() }.sortedBy { it.productCode }

                    val productEntities = products.map {
                        ProductEntity(
                            productCode = it.productCode,
                            description = it.description,
                            line = it.line.name,
                            brand = it.brand,
                            imageFile = it.imageFile
                        )
                    }

                    val variantEntities = products.flatMap { product ->
                        product.variants.map { variant ->
                            VariantEntity(
                                productCode = product.productCode,
                                variantIndex = variant.variantIndex,
                                stockQuantity = variant.stockQuantity,
                                zahedanPrice = variant.zahedanPrice,
                                otherCitiesPrice = variant.otherCitiesPrice,
                                customerNames = variant.customerNames
                            )
                        }
                    }

                    requestDao.replaceAllProducts(productEntities, variantEntities)
                    productService.updateCatalog(products)
                    imageCacheManager.clearAndPrepare()

                    emit(ImportState.Success(importedProducts = products.size))
                }
            }
        } catch (ex: Exception) {
            errorHandler.emit(
                AppError(
                    title = "Excel import failed",
                    message = ex.message ?: "Unknown error",
                    cause = ex
                )
            )
            emit(ImportState.Error(ex.message ?: "Excel import failed"))
        }
    }.flowOn(dispatcher)

    private fun openInputStream(uri: Uri): InputStream =
        requireNotNull(contentResolver.openInputStream(uri)) { "Unable to open Excel file" }

    private fun Row.readString(index: Int): String {
        val cell = getCell(index) ?: return ""
        return when (cell.cellType) {
            CellType.STRING -> cell.stringCellValue.trim()
            CellType.NUMERIC -> BigDecimal.valueOf(cell.numericCellValue).stripTrailingZeros().toPlainString()
            CellType.BLANK -> ""
            else -> cell.toString().trim()
        }
    }

    private fun Row.readInt(index: Int): Int = readString(index).toIntOrNull()
        ?: error("Invalid integer at column $index")

    private fun Row.readDecimal(index: Int): BigDecimal = readString(index).takeIf { it.isNotBlank() }
        ?.let { BigDecimal(it) } ?: BigDecimal.ZERO

    private data class ProductBuilder(
        val productCode: String,
        val description: String,
        val line: SalesLine,
        val brand: String,
        val imageFile: String?,
        val variants: MutableList<ProductVariant> = mutableListOf()
    ) {
        fun build(): Product = Product(
            productCode = productCode,
            description = description,
            line = line,
            brand = brand,
            imageFile = imageFile,
            variants = variants.sortedBy { it.variantIndex }
        )
    }
}
