package com.example.productgallery.data.model

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "requests",
    foreignKeys = [
        ForeignKey(
            entity = ProductVariant::class,
            parentColumns = ["productCode", "variantIndex"],
            childColumns = ["productCode", "variantIndex"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["productCode", "variantIndex"])]
)
data class Request(
    @PrimaryKey(autoGenerate = true) val id: Int = 0,
    val productCode: String,
    val variantIndex: Int,
    val customer: String,
    val quantity: Int,
    val date: String, // ISO 8601 format
    val notes: String?,
    val error: String?
)