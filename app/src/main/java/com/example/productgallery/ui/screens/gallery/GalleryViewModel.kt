package com.example.productgallery.ui.screens.gallery

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.productgallery.data.model.Product
import com.example.productgallery.domain.service.ProductService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

class GalleryViewModel(
    private val productService: ProductService
) : ViewModel() {

    private val _products = MutableStateFlow<List<Product>>(emptyList())
    val products: StateFlow<List<Product>> = _products

    init {
        viewModelScope.launch {
            productService.loadFromCache()
            productService.products.collectLatest { productList ->
                _products.value = productList
            }
        }
    }
}
