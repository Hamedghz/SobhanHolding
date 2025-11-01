package com.example.productgallery

import android.app.Application
import com.example.productgallery.data.local.ProductDatabase
import com.example.productgallery.data.preferences.UserPreferencesRepository
import com.example.productgallery.domain.error.ErrorHandler
import com.example.productgallery.domain.service.ExcelService
import com.example.productgallery.domain.service.ImageCacheManager
import com.example.productgallery.domain.service.ProductService
import com.example.productgallery.domain.service.RequestService
import com.example.productgallery.util.ProductCacheSerializer

class ProductGalleryApp : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        val database = ProductDatabase.build(this)
        val errorHandler = ErrorHandler()
        val productService = ProductService(this, ProductCacheSerializer())
        val imageCacheManager = ImageCacheManager(this)
        container = AppContainer(
            requestService = RequestService(database.requestDao()),
            productService = productService,
            excelService = ExcelService(
                contentResolver = contentResolver,
                requestDao = database.requestDao(),
                productService = productService,
                imageCacheManager = imageCacheManager,
                errorHandler = errorHandler
            ),
            errorHandler = errorHandler,
            userPreferencesRepository = UserPreferencesRepository(this)
        )
    }
}

data class AppContainer(
    val requestService: RequestService,
    val productService: ProductService,
    val excelService: ExcelService,
    val errorHandler: ErrorHandler,
    val userPreferencesRepository: UserPreferencesRepository
)
