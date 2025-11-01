package com.example.productgallery.ui.screens.requests

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.example.productgallery.domain.service.RequestService

class RequestsViewModelFactory(
    private val requestService: RequestService
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(RequestsViewModel::class.java)) {
            return RequestsViewModel(requestService) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
