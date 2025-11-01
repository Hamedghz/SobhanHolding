package com.example.productgallery.domain

/**
 * Describes the lifecycle of an Excel import action. The UI collects progress updates from the
 * exposed state flow so users can observe validation and parsing status in real time.
 */
sealed interface ImportState {
    object Idle : ImportState
    data class InProgress(val progress: Int, val message: String) : ImportState
    data class Success(val importedProducts: Int) : ImportState
    data class Error(val message: String) : ImportState
}
