package com.example.productgallery.util

import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.LocalDateTime

class CatalogFileNamerTest {
    @Test
    fun `formats filename with timestamp`() {
        val fixedTime = LocalDateTime.of(2024, 5, 1, 14, 30)

        val result = CatalogFileNamer.generate(fixedTime)

        assertEquals("GloriaGlass_Catalog_20240501_1430.pdf", result)
    }
}

