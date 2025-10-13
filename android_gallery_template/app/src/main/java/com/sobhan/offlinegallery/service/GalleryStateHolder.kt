package com.sobhan.offlinegallery.service

import com.sobhan.offlinegallery.data.GalleryUiState
import com.sobhan.offlinegallery.data.ProductVariant

class GalleryStateHolder {
    val uiState: GalleryUiState = GalleryUiState(
        variants = listOf(
            ProductVariant(
                productCode = "DEMO-001",
                description = "Demo Product",
                variantIndex = 1,
                stockQuantity = 10,
                zahedanPrice = 100.0,
                otherCitiesPrice = 95.0,
                line = "A",
                brandName = "Demo Brand"
            )
        ),
        isLoading = false
    )
}
