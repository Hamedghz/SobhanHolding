package com.example.productgallery.data.model

import androidx.room.Embedded
import androidx.room.Entity
import androidx.room.PrimaryKey
import androidx.room.Relation

@Entity(tableName = "products")
data class Product(
    @PrimaryKey val productCode: String,
    val description: String,
    val line: String,
    val brandName: String
)

data class ProductWithVariants(
    @Embedded val product: Product,
    @Relation(
        parentColumn = "productCode",
        entityColumn = "productCode"
    )
    val variants: List<ProductVariant>
)