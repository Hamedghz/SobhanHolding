package com.example.productgallery.data.local

import androidx.room.TypeConverter
import java.math.BigDecimal

class Converters {
    @TypeConverter
    fun fromStringList(value: List<String>?): String? = value?.joinToString(separator = "|")

    @TypeConverter
    fun toStringList(value: String?): List<String> = value?.split('|')?.map { it.trim() }?.filter { it.isNotEmpty() } ?: emptyList()

    @TypeConverter
    fun fromBigDecimal(value: BigDecimal?): String? = value?.toPlainString()

    @TypeConverter
    fun toBigDecimal(value: String?): BigDecimal? = value?.let { BigDecimal(it) }
}
