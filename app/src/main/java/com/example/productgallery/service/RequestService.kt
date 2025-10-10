package com.example.productgallery.service

import android.content.Context
import android.os.Environment
import com.example.productgallery.data.dao.RequestDao
import com.example.productgallery.data.model.Request
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileWriter
import java.text.SimpleDateFormat
import java.util.*

class RequestService(
    private val context: Context,
    private val requestDao: RequestDao
) {

    suspend fun createRequest(
        productCode: String,
        variantIndex: Int,
        customer: String,
        quantity: Int,
        notes: String?
    ) = withContext(Dispatchers.IO) {
        if (customer.isBlank() || quantity <= 0) {
            throw IllegalArgumentException("Customer and quantity are required.")
        }

        val request = Request(
            productCode = productCode,
            variantIndex = variantIndex,
            customer = customer,
            quantity = quantity,
            date = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US).format(Date()),
            notes = notes,
            error = null
        )
        requestDao.insertRequest(request)
    }

    fun getAllRequests() = requestDao.getAllRequests()

    suspend fun updateRequest(request: Request) = withContext(Dispatchers.IO) {
        requestDao.updateRequest(request)
    }

    suspend fun deleteRequest(request: Request) = withContext(Dispatchers.IO) {
        requestDao.deleteRequest(request)
    }

    suspend fun exportRequestsToCsv(): File = withContext(Dispatchers.IO) {
        val requests = getAllRequests().first()
        val csvHeader = "id,product_code,variant_index,customer,quantity,date,notes\n"
        val csvBody = requests.joinToString(separator = "\n") {
            "${it.id},${it.productCode},${it.variantIndex},\"${it.customer}\",${it.quantity},${it.date},\"${it.notes ?: ""}\""
        }

        val file = File(context.getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS), "requests_export_${System.currentTimeMillis()}.csv")
        FileWriter(file).use {
            it.write(csvHeader)
            it.write(csvBody)
        }
        file
    }
}