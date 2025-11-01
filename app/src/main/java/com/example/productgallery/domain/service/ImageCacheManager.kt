package com.example.productgallery.domain.service

import android.content.Context
import android.net.Uri
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

class ImageCacheManager(
    private val context: Context,
    private val dispatcher: CoroutineDispatcher = Dispatchers.IO
) {
    private val cacheDirName = "images"

    suspend fun clearAndPrepare() {
        withContext(dispatcher) {
            val dir = File(context.cacheDir, cacheDirName)
            if (dir.exists()) {
                dir.deleteRecursively()
            }
            dir.mkdirs()
        }
    }

    suspend fun cacheImage(source: Uri, fileName: String) {
        withContext(dispatcher) {
            val dir = File(context.cacheDir, cacheDirName).apply { mkdirs() }
            context.contentResolver.openInputStream(source)?.use { input ->
                File(dir, fileName).outputStream().use { output ->
                    input.copyTo(output)
                }
            }
        }
    }
}
