package com.example.productgallery.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.productgallery.data.dao.ProductDao
import com.example.productgallery.data.model.ProductWithVariants
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

class GalleryViewModel(private val productDao: ProductDao) : ViewModel() {

    private val _allProducts = MutableStateFlow<List<ProductWithVariants>>(emptyList())
    private val _searchQuery = MutableStateFlow("")
    val searchQuery = _searchQuery.asStateFlow()

    private val _isLoading = MutableStateFlow(true)
    val isLoading = _isLoading.asStateFlow()

    val products: StateFlow<List<ProductWithVariants>> =
        combine(_allProducts, _searchQuery) { products, query ->
            if (query.isBlank()) {
                products
            } else {
                products.filter {
                    it.product.description.contains(query, ignoreCase = true) ||
                            it.product.productCode.contains(query, ignoreCase = true)
                }
            }
        }.stateIn(viewModelScope, SharingStarted.Lazily, emptyList())

    init {
        fetchProducts()
    }

    private fun fetchProducts() {
        viewModelScope.launch {
            productDao.getProductsWithVariants()
                .catch { exception ->
                    // Handle error
                    _isLoading.value = false
                }
                .collect { productList ->
                    _allProducts.value = productList
                    _isLoading.value = false
                }
        }
    }

    fun onSearchQueryChanged(query: String) {
        _searchQuery.value = query
    }
}

class GalleryViewModelFactory(private val productDao: ProductDao) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(GalleryViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return GalleryViewModel(productDao) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}