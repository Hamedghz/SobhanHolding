package com.example.productgallery.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.productgallery.data.model.Request
import com.example.productgallery.service.ErrorHandler
import com.example.productgallery.service.RequestService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

class RequestViewModel(
    private val requestService: RequestService,
    private val errorHandler: ErrorHandler
) : ViewModel() {

    val requests = requestService.getAllRequests()
        .stateIn(viewModelScope, SharingStarted.Lazily, emptyList())

    private val _exportState = MutableStateFlow<ExportState>(ExportState.Idle)
    val exportState = _exportState.asStateFlow()

    fun exportRequests() {
        viewModelScope.launch {
            _exportState.value = ExportState.Exporting
            try {
                val file = requestService.exportRequestsToCsv()
                _exportState.value = ExportState.Success(file.absolutePath)
            } catch (e: Exception) {
                val errorMessage = errorHandler.handle(e)
                _exportState.value = ExportState.Error(errorMessage)
            }
        }
    }

    fun resetExportState() {
        _exportState.value = ExportState.Idle
    }

    fun createRequest(
        productCode: String,
        variantIndex: Int,
        customer: String,
        quantity: Int,
        notes: String?
    ) {
        viewModelScope.launch {
            try {
                requestService.createRequest(productCode, variantIndex, customer, quantity, notes)
            } catch (e: Exception) {
                errorHandler.handle(e)
            }
        }
    }

    fun updateRequest(request: Request) {
        viewModelScope.launch {
            try {
                requestService.updateRequest(request)
            } catch (e: Exception) {
                errorHandler.handle(e)
            }
        }
    }

    fun deleteRequest(request: Request) {
        viewModelScope.launch {
            try {
                requestService.deleteRequest(request)
            } catch (e: Exception) {
                errorHandler.handle(e)
            }
        }
    }
}

sealed class ExportState {
    object Idle : ExportState()
    object Exporting : ExportState()
    data class Success(val filePath: String) : ExportState()
    data class Error(val message: String) : ExportState()
}

class RequestViewModelFactory(
    private val requestService: RequestService,
    private val errorHandler: ErrorHandler
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(RequestViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return RequestViewModel(requestService, errorHandler) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}