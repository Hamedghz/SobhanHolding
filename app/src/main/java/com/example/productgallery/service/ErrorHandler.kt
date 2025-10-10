package com.example.productgallery.service

import android.util.Log

class ErrorHandler {
    fun handle(e: Exception, tag: String = "ProductGalleryError"): String {
        Log.e(tag, "An error occurred: ${e.message}", e)
        // In a real-world application, you could have more specific error messages
        // based on the type of exception. For now, a generic message will suffice.
        return e.message ?: "An unexpected error occurred. Please try again."
    }
}