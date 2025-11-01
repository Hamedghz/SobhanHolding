package com.example.productgallery.domain.service

import com.example.productgallery.data.local.RequestDao
import com.example.productgallery.data.local.RequestEntity
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext

class RequestService(
    private val requestDao: RequestDao,
    private val dispatcher: CoroutineDispatcher = Dispatchers.IO
) {
    fun observeRequests(): Flow<List<RequestEntity>> = requestDao.observeRequests()

    suspend fun upsert(request: RequestEntity) = withContext(dispatcher) {
        requestDao.upsertRequest(request)
    }

    suspend fun delete(id: Int) = withContext(dispatcher) {
        requestDao.deleteRequest(id)
    }
}
