package com.sobhan.offlinegallery.data

data class ProductVariant(
    val productCode: String,
    val description: String,
    val variantIndex: Int,
    val stockQuantity: Int,
    val zahedanPrice: Double,
    val otherCitiesPrice: Double,
    val line: String,
    val brandName: String,
    val customerNames: List<String> = emptyList(),
    val imageFile: String = ""
)

data class GalleryUiState(
    val variants: List<ProductVariant> = emptyList(),
    val isLoading: Boolean = true,
    val errorMessage: String? = null
)
