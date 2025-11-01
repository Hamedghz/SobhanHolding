package com.sobhan.offlinegallery

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.GridLayoutManager
import com.google.gson.Gson
import com.google.gson.JsonParser
import com.google.gson.reflect.TypeToken
import com.sobhan.offlinegallery.databinding.ActivityMainBinding
import com.sobhan.offlinegallery.model.GalleryItem
import com.sobhan.offlinegallery.ui.GalleryAdapter

class MainActivity : AppCompatActivity() {

    private lateinit var b: ActivityMainBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityMainBinding.inflate(layoutInflater)
        setContentView(b.root)

        val items = loadCatalogItems()
        b.recycler.layoutManager = GridLayoutManager(this, 2)
        b.recycler.adapter = GalleryAdapter(items)
    }

    private fun loadCatalogItems(): List<GalleryItem> {
        val json = assets.open("catalog/index.json").bufferedReader().use { it.readText() }
        val root = JsonParser.parseString(json).asJsonObject
        val arr = root.getAsJsonArray("items")
        val type = object : TypeToken<List<GalleryItem>>() {}.type
        return Gson().fromJson(arr, type)
    }
}
