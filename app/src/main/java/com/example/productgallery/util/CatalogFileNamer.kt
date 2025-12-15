package com.example.productgallery.util

import java.time.LocalDateTime
import java.time.format.DateTimeFormatter

object CatalogFileNamer {
    private val formatter = DateTimeFormatter.ofPattern("yyyyMMdd_HHmm")

    fun generate(now: LocalDateTime = LocalDateTime.now()): String {
        return "GloriaGlass_Catalog_${formatter.format(now)}.pdf"
    }
}

