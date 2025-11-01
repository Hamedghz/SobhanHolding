package com.example.productgallery.ui

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.example.productgallery.AppContainer
import com.example.productgallery.ProductGalleryApp
import com.example.productgallery.ui.navigation.AppDestination
import com.example.productgallery.ui.screens.gallery.GalleryScreen
import com.example.productgallery.ui.screens.gallery.GalleryViewModel
import com.example.productgallery.ui.screens.gallery.GalleryViewModelFactory
import com.example.productgallery.ui.screens.importer.ImportScreen
import com.example.productgallery.ui.screens.importer.ImportViewModel
import com.example.productgallery.ui.screens.importer.ImportViewModelFactory
import com.example.productgallery.ui.screens.requests.RequestsScreen
import com.example.productgallery.ui.screens.requests.RequestsViewModel
import com.example.productgallery.ui.screens.requests.RequestsViewModelFactory
import com.example.productgallery.ui.theme.ProductGalleryTheme

class MainActivity : ComponentActivity() {

    private val container: AppContainer by lazy {
        (application as ProductGalleryApp).container
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ProductGalleryTheme {
                Surface(color = MaterialTheme.colorScheme.background) {
                    ProductGalleryNavHost(container = container)
                }
            }
        }
    }
}

@Composable
private fun ProductGalleryNavHost(container: AppContainer) {
    val navController = rememberNavController()

    Scaffold(
        bottomBar = {
            val backStackEntry by navController.currentBackStackEntryAsState()
            val currentRoute = backStackEntry?.destination?.route
            NavigationBar {
                AppDestination.values().forEach { destination ->
                    val selected = currentRoute == destination.route
                    NavigationBarItem(
                        selected = selected,
                        onClick = {
                            navController.navigate(destination.route) {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        },
                        label = { Text(destination.label) },
                        icon = {},
                    )
                }
            }
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = AppDestination.Gallery.route,
            modifier = Modifier.padding(paddingValues)
        ) {
            composable(AppDestination.Gallery.route) {
                val viewModel: GalleryViewModel = viewModel(
                    factory = GalleryViewModelFactory(container.productService)
                )
                GalleryScreen(viewModel = viewModel)
            }
            composable(AppDestination.Requests.route) {
                val viewModel: RequestsViewModel = viewModel(
                    factory = RequestsViewModelFactory(container.requestService)
                )
                RequestsScreen(viewModel = viewModel)
            }
            composable(AppDestination.Import.route) {
                val viewModel: ImportViewModel = viewModel(
                    factory = ImportViewModelFactory(container.excelService)
                )
                ImportScreen(viewModel = viewModel)
            }
        }
    }
}
