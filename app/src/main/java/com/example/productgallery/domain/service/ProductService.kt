package com.example.productgallery.domain.service

import android.content.Context
import com.example.productgallery.data.model.Product
import com.example.productgallery.util.ProductCacheSerializer
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.withContext

class ProductService(
    private val context: Context,
    private val cacheSerializer: ProductCacheSerializer,
    private val dispatcher: CoroutineDispatcher = Dispatchers.IO
) {
    private val _products = MutableStateFlow<List<Product>>(emptyList())
    val products: StateFlow<List<Product>> = _products

    suspend fun loadFromCache() {
        withContext(dispatcher) {
            val cached = cacheSerializer.load(context)
            _products.value = cached
        }
    }

    suspend fun updateCatalog(products: List<Product>) {
        withContext(dispatcher) {
            cacheSerializer.persist(context, products)
            _products.value = products
        }
    }
}
