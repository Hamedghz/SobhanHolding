package com.example.productgallery.ui.screens.importer

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.productgallery.domain.ImportState
import com.example.productgallery.domain.service.ExcelService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

class ImportViewModel(
    private val excelService: ExcelService
) : ViewModel() {

    private val _state = MutableStateFlow<ImportState>(ImportState.Idle)
    val state: StateFlow<ImportState> = _state

    fun import(uri: Uri) {
        viewModelScope.launch {
            excelService.import(uri).collectLatest { importState ->
                _state.value = importState
            }
        }
    }
}
