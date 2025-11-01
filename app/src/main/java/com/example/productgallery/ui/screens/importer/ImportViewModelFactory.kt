package com.example.productgallery.ui.screens.importer

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.example.productgallery.domain.service.ExcelService

class ImportViewModelFactory(
    private val excelService: ExcelService
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(ImportViewModel::class.java)) {
            return ImportViewModel(excelService) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
