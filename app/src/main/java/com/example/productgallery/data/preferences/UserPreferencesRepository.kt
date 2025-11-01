package com.example.productgallery.data.preferences

import android.content.Context
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.example.productgallery.data.model.SalesLine
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private const val DATASTORE_NAME = "user_preferences"

val Context.userPreferencesDataStore by preferencesDataStore(name = DATASTORE_NAME)

class UserPreferencesRepository(private val context: Context) {
    private val priceModeKey = intPreferencesKey("price_mode")
    private val salesLineKey = stringPreferencesKey("sales_line")

    fun observePriceMode(): Flow<Int> = context.userPreferencesDataStore.data.map { prefs ->
        prefs[priceModeKey] ?: 0
    }

    fun observeSalesLine(): Flow<SalesLine> = context.userPreferencesDataStore.data.map { prefs ->
        prefs[salesLineKey]?.let { runCatching { SalesLine.valueOf(it) }.getOrNull() } ?: SalesLine.A
    }

    suspend fun setPriceMode(index: Int) {
        context.userPreferencesDataStore.edit { prefs ->
            prefs[priceModeKey] = index
        }
    }

    suspend fun setSalesLine(salesLine: SalesLine) {
        context.userPreferencesDataStore.edit { prefs ->
            prefs[salesLineKey] = salesLine.name
        }
    }
}
