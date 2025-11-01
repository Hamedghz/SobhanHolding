package com.example.productgallery.ui.screens.requests

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.productgallery.data.local.RequestEntity
import com.example.productgallery.domain.service.RequestService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.launch

class RequestsViewModel(
    private val requestService: RequestService
) : ViewModel() {

    private val _requests = MutableStateFlow<List<RequestEntity>>(emptyList())
    val requests: StateFlow<List<RequestEntity>> = _requests

    init {
        viewModelScope.launch {
            requestService.observeRequests().collectLatest { entities ->
                _requests.value = entities
            }
        }
    }
}
