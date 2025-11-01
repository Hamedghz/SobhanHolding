package com.example.productgallery.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import kotlinx.coroutines.flow.Flow

@Dao
interface RequestDao {
    @Query("SELECT * FROM requests ORDER BY date DESC")
    fun observeRequests(): Flow<List<RequestEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertRequest(request: RequestEntity): Long

    @Query("DELETE FROM requests WHERE id = :id")
    suspend fun deleteRequest(id: Int)

    @Transaction
    suspend fun replaceAllProducts(
        products: List<ProductEntity>,
        variants: List<VariantEntity>
    ) {
        clearProducts()
        clearVariants()
        insertProducts(products)
        insertVariants(variants)
    }

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertProducts(products: List<ProductEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertVariants(variants: List<VariantEntity>)

    @Query("DELETE FROM products")
    suspend fun clearProducts()

    @Query("DELETE FROM variants")
    suspend fun clearVariants()
}
