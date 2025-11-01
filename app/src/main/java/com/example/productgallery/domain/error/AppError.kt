package com.example.productgallery.domain.error

/**
 * Shared app level error surface so the UI can present consistent messaging. Errors originate
 * from services and are funnelled through [ErrorHandler].
 */
data class AppError(
    val title: String,
    val message: String,
    val cause: Throwable? = null
)
