package com.example.productgallery.data.model

import java.math.BigDecimal

/**
 * Represents a product with its variants loaded from the Excel catalog. The data classes mirror
 * the schema described in the functional specification so the same models can be reused across
 * the data, domain and presentation layers.
 */
data class Product(
    val productCode: String,
    val description: String,
    val line: SalesLine,
    val brand: String,
    val imageFile: String?,
    val variants: List<ProductVariant>
)

/**
 * Individual purchasable variant belonging to a [Product].
 */
data class ProductVariant(
    val variantIndex: Int,
    val stockQuantity: Int,
    val zahedanPrice: BigDecimal,
    val otherCitiesPrice: BigDecimal,
    val customerNames: List<String>
)

enum class SalesLine { A, B, C, D }
