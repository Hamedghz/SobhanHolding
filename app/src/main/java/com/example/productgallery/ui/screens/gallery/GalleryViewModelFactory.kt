package com.example.productgallery.ui.screens.gallery

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.example.productgallery.domain.service.ProductService

class GalleryViewModelFactory(
    private val productService: ProductService
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(GalleryViewModel::class.java)) {
            return GalleryViewModel(productService) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
