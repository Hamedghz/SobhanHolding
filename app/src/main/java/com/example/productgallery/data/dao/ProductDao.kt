package com.example.productgallery.data.dao

import androidx.room.*
import com.example.productgallery.data.model.Product
import com.example.productgallery.data.model.ProductVariant
import com.example.productgallery.data.model.ProductWithVariants
import kotlinx.coroutines.flow.Flow

@Dao
interface ProductDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProducts(products: List<Product>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertVariants(variants: List<ProductVariant>)

    @Transaction
    @Query("SELECT * FROM products")
    fun getProductsWithVariants(): Flow<List<ProductWithVariants>>

    @Query("DELETE FROM products")
    suspend fun clearAllProducts()

    @Transaction
    suspend fun clearAndInsert(products: List<Product>, variants: List<ProductVariant>) {
        clearAllProducts()
        insertProducts(products)
        insertVariants(variants)
    }
}