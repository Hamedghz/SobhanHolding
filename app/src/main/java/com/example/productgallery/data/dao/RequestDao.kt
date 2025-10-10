package com.example.productgallery.data.dao

import androidx.room.*
import com.example.productgallery.data.model.Request
import kotlinx.coroutines.flow.Flow

@Dao
interface RequestDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertRequest(request: Request)

    @Update
    suspend fun updateRequest(request: Request)

    @Delete
    suspend fun deleteRequest(request: Request)

    @Query("SELECT * FROM requests ORDER BY date DESC")
    fun getAllRequests(): Flow<List<Request>>

    @Query("SELECT * FROM requests WHERE id = :id")
    suspend fun getRequestById(id: Int): Request?
}