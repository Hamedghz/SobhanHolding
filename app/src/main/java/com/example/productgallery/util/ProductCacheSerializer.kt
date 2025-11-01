package com.example.productgallery.util

import android.content.Context
import com.example.productgallery.data.model.Product
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import java.io.File

class ProductCacheSerializer(private val gson: Gson = Gson()) {
    private val cacheDir = "products.json"

    fun load(context: Context): List<Product> {
        val file = File(context.filesDir, cacheDir)
        if (!file.exists()) return emptyList()
        return file.bufferedReader().use { reader ->
            val type = object : TypeToken<List<Product>>() {}.type
            gson.fromJson(reader, type) ?: emptyList()
        }
    }

    fun persist(context: Context, products: List<Product>) {
        val file = File(context.filesDir, cacheDir)
        file.parentFile?.mkdirs()
        file.bufferedWriter().use { writer ->
            gson.toJson(products, writer)
        }
    }
}
