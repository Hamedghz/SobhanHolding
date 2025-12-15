package com.example.productgallery.domain.service

/**
 * Helper that recognises both the legacy and V2 Excel header rows and exposes
 * the column indexes that the importer should read from.
 */
class ExcelHeaderMapper {
    sealed class HeaderMapping(val requiredColumns: Set<String>) {
        abstract val columnIndex: Map<String, Int>

        object V2 : HeaderMapping(
            setOf(
                "ProductCode",
                "ProductName",
                "Description",
                "VariantIndex",
                "StockQty",
                "RetailPrice",
                "CountyPrice",
                "ImageFile"
            )
        ) {
            override val columnIndex: Map<String, Int> = mapOf(
                "ProductCode" to 0,
                "ProductName" to 1,
                "Description" to 2,
                "VariantIndex" to 3,
                "StockQty" to 4,
                "RetailPrice" to 5,
                "CountyPrice" to 6,
                "ImageFile" to 7
            )
        }

        object Legacy : HeaderMapping(
            setOf(
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
        ) {
            override val columnIndex: Map<String, Int> = mapOf(
                "Product Code" to 0,
                "Description" to 1,
                "Product Variant Index" to 2,
                "Stock Quantity" to 3,
                "Zahedan Price" to 4,
                "Other Cities Price" to 5,
                "Line" to 6,
                "Brand Name" to 7,
                "Customer Names" to 8
            )
        }
    }

    fun map(headerRow: List<String>): HeaderMapping? {
        return when {
            headerRow.take(HeaderMapping.V2.columnIndex.size) == HeaderMapping.V2.columnIndex.entries
                .sortedBy { it.value }.map { it.key } -> HeaderMapping.V2
            headerRow.take(HeaderMapping.Legacy.columnIndex.size) == HeaderMapping.Legacy.columnIndex.entries
                .sortedBy { it.value }.map { it.key } -> HeaderMapping.Legacy
            else -> null
        }
    }
}

