package com.example.productgallery.domain.error

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * Collects [AppError] events and exposes them via a hot [SharedFlow] to be consumed by the UI.
 */
class ErrorHandler {
    private val _errors = MutableSharedFlow<AppError>(extraBufferCapacity = 1)
    val errors: SharedFlow<AppError> = _errors

    suspend fun emit(error: AppError) {
        _errors.emit(error)
    }
}
