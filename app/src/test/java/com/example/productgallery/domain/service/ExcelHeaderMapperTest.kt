package com.example.productgallery.domain.service

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ExcelHeaderMapperTest {
    private val mapper = ExcelHeaderMapper()

    @Test
    fun `detects v2 header`() {
        val header = listOf(
            "ProductCode",
            "ProductName",
            "Description",
            "VariantIndex",
            "StockQty",
            "RetailPrice",
            "CountyPrice",
            "ImageFile"
        )

        val mapping = mapper.map(header)

        assertEquals(ExcelHeaderMapper.HeaderMapping.V2, mapping)
    }

    @Test
    fun `detects legacy header`() {
        val header = listOf(
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

        val mapping = mapper.map(header)

        assertEquals(ExcelHeaderMapper.HeaderMapping.Legacy, mapping)
    }

    @Test
    fun `returns null for unknown header`() {
        val mapping = mapper.map(listOf("Foo", "Bar"))

        assertNull(mapping)
    }
}

