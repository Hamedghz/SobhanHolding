package com.example.productgallery.ui.viewmodel

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.productgallery.service.ErrorHandler
import com.example.productgallery.service.ExcelService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ExcelImportViewModel(
    private val excelService: ExcelService,
    private val errorHandler: ErrorHandler
) : ViewModel() {

    private val _importState = MutableStateFlow<ImportState>(ImportState.Idle)
    val importState = _importState.asStateFlow()

    fun importExcelFile(uri: Uri) {
        viewModelScope.launch {
            _importState.value = ImportState.Importing(0f)
            try {
                excelService.importProductsFromExcel(uri) { progress ->
                    _importState.value = ImportState.Importing(progress)
                }
                _importState.value = ImportState.Success
            } catch (e: Exception) {
                val errorMessage = errorHandler.handle(e)
                _importState.value = ImportState.Error(errorMessage)
            }
        }
    }
}

sealed class ImportState {
    object Idle : ImportState()
    data class Importing(val progress: Float) : ImportState()
    object Success : ImportState()
    data class Error(val message: String) : ImportState()
}

class ExcelImportViewModelFactory(
    private val excelService: ExcelService,
    private val errorHandler: ErrorHandler
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(ExcelImportViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return ExcelImportViewModel(excelService, errorHandler) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}