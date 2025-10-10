package com.example.productgallery.data.db

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.TypeConverters
import com.example.productgallery.data.dao.ProductDao
import com.example.productgallery.data.dao.RequestDao
import com.example.productgallery.data.model.Product
import com.example.productgallery.data.model.ProductVariant
import com.example.productgallery.data.model.Request

@Database(
    entities = [Product::class, ProductVariant::class, Request::class],
    version = 1,
    exportSchema = false
)
@TypeConverters(Converters::class)
abstract class AppDatabase : RoomDatabase() {

    abstract fun productDao(): ProductDao
    abstract fun requestDao(): RequestDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "product_gallery_db"
                ).build()
                INSTANCE = instance
                instance
            }
        }
    }
}