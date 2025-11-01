package com.example.productgallery.data.local

import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey
import java.math.BigDecimal

@Entity(
    tableName = "products"
)
data class ProductEntity(
    @PrimaryKey @ColumnInfo(name = "product_code") val productCode: String,
    val description: String,
    val line: String,
    val brand: String,
    @ColumnInfo(name = "image_file") val imageFile: String?
)

@Entity(
    tableName = "variants",
    primaryKeys = ["product_code", "variant_index"],
    foreignKeys = [
        ForeignKey(
            entity = ProductEntity::class,
            parentColumns = ["product_code"],
            childColumns = ["product_code"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["product_code", "variant_index"])]
)
data class VariantEntity(
    @ColumnInfo(name = "product_code") val productCode: String,
    @ColumnInfo(name = "variant_index") val variantIndex: Int,
    @ColumnInfo(name = "stock_quantity") val stockQuantity: Int,
    @ColumnInfo(name = "zahedan_price") val zahedanPrice: BigDecimal,
    @ColumnInfo(name = "other_cities_price") val otherCitiesPrice: BigDecimal,
    @ColumnInfo(name = "customer_names") val customerNames: List<String>
)

@Entity(
    tableName = "requests",
    foreignKeys = [
        ForeignKey(
            entity = VariantEntity::class,
            parentColumns = ["product_code", "variant_index"],
            childColumns = ["product_code", "variant_index"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["product_code", "variant_index"])]
)
data class RequestEntity(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    @ColumnInfo(name = "product_code") val productCode: String,
    @ColumnInfo(name = "variant_index") val variantIndex: Int,
    val customer: String,
    val quantity: Int,
    val date: String,
    val notes: String?,
    val error: String?
)
