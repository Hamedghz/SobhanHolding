package com.example.productgallery.service

import android.content.Context
import android.net.Uri
import com.example.productgallery.data.dao.ProductDao
import com.example.productgallery.data.model.Product
import com.example.productgallery.data.model.ProductVariant
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import org.apache.poi.ss.usermodel.CellType
import org.apache.poi.xssf.usermodel.XSSFWorkbook
import java.math.BigDecimal

class ExcelService(
    private val context: Context,
    private val productDao: ProductDao
) {
    private val importMutex = Mutex()

    suspend fun importProductsFromExcel(uri: Uri, onProgress: (Float) -> Unit) = withContext(Dispatchers.IO) {
        importMutex.withLock {
            try {
                onProgress(0f)
                val products = mutableMapOf<String, Product>()
                val variants = mutableListOf<ProductVariant>()

                context.contentResolver.openInputStream(uri)?.use { inputStream ->
                    val workbook = XSSFWorkbook(inputStream)
                    val sheet = workbook.getSheetAt(0)
                    val headerRow = sheet.getRow(0)
                    val expectedHeaders = listOf(
                        "Product Code", "Description", "Product Variant Index", "Stock Quantity",
                        "Zahedan Price", "Other Cities Price", "Line", "Brand Name", "Customer Names"
                    )
                    val actualHeaders = (0 until headerRow.physicalNumberOfCells).map { headerRow.getCell(it).stringCellValue }
                    if (actualHeaders != expectedHeaders) {
                        throw IllegalArgumentException("Invalid Excel file format. Please check the column headers.")
                    }

                    val totalRows = sheet.lastRowNum
                    for (i in 1..totalRows) {
                        val row = sheet.getRow(i) ?: continue
                        val productCode = row.getCell(0)?.stringCellValue ?: continue
                        val description = row.getCell(1)?.stringCellValue ?: ""
                        val variantIndex = row.getCell(2)?.numericCellValue?.toInt() ?: 1
                        val stockQuantity = row.getCell(3)?.numericCellValue?.toInt() ?: 0
                        val zahedanPrice = row.getCell(4)?.numericCellValue?.toBigDecimal() ?: BigDecimal.ZERO
                        val otherCitiesPrice = row.getCell(5)?.numericCellValue?.toBigDecimal() ?: BigDecimal.ZERO
                        val line = row.getCell(6)?.stringCellValue ?: ""
                        val brandName = row.getCell(7)?.stringCellValue ?: ""
                        val customerNames = row.getCell(8)?.stringCellValue ?: ""

                        if (!products.containsKey(productCode)) {
                            products[productCode] = Product(
                                productCode = productCode,
                                description = description,
                                line = line,
                                brandName = brandName
                            )
                        }

                        variants.add(
                            ProductVariant(
                                productCode = productCode,
                                variantIndex = variantIndex,
                                stockQuantity = stockQuantity,
                                zahedanPrice = zahedanPrice,
                                otherCitiesPrice = otherCitiesPrice,
                                customerNames = customerNames
                            )
                        )
                        onProgress(i.toFloat() / totalRows.toFloat())
                    }
                }
                productDao.clearAndInsert(products.values.toList(), variants)
                onProgress(1f)
            } catch (e: Exception) {
                // In a real app, we would have a more robust error handling mechanism
                e.printStackTrace()
                throw e
            }
        }
    }

    private fun Double.toBigDecimal(): BigDecimal {
        return BigDecimal.valueOf(this)
    }
}