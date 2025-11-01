package com.example.productgallery.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.TypeConverters

@Database(
    entities = [ProductEntity::class, VariantEntity::class, RequestEntity::class],
    version = 1
)
@TypeConverters(Converters::class)
abstract class ProductDatabase : RoomDatabase() {
    abstract fun requestDao(): RequestDao

    companion object {
        fun build(context: Context): ProductDatabase = Room.databaseBuilder(
            context,
            ProductDatabase::class.java,
            "product-gallery.db"
        ).fallbackToDestructiveMigration().build()
    }
}
