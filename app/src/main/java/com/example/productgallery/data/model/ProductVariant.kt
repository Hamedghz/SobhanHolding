package com.example.productgallery.data.model

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import java.math.BigDecimal

@Entity(
    tableName = "product_variants",
    primaryKeys = ["productCode", "variantIndex"],
    foreignKeys = [
        ForeignKey(
            entity = Product::class,
            parentColumns = ["productCode"],
            childColumns = ["productCode"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["productCode"])]
)
data class ProductVariant(
    val productCode: String,
    val variantIndex: Int,
    val stockQuantity: Int,
    val zahedanPrice: BigDecimal,
    val otherCitiesPrice: BigDecimal,
    val customerNames: String // Stored as a comma-separated string
)