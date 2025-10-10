package com.example.productgallery.ui

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.example.productgallery.ui.composables.BottomNavBar
import com.example.productgallery.ui.composables.NavigationItem
import com.example.productgallery.ui.composables.TopAppBar
import com.example.productgallery.ui.screen.GalleryScreen
import com.example.productgallery.ui.screen.ProductDetailsScreen
import com.example.productgallery.ui.screen.RequestFormScreen
import com.example.productgallery.ui.screen.RequestScreen
import com.example.productgallery.ui.theme.ProductGalleryTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ProductGalleryTheme {
                MainScreen()
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen() {
    val navController = rememberNavController()
    Scaffold(
        topBar = { TopAppBar(title = "Product Gallery") },
        bottomBar = { BottomNavBar(navController = navController) }
    ) { innerPadding ->
        NavHost(
            navController,
            startDestination = NavigationItem.Gallery.route,
            modifier = Modifier.padding(innerPadding)
        ) {
            composable(NavigationItem.Gallery.route) {
                GalleryScreen(navController)
            }
            composable(NavigationItem.Requests.route) {
                RequestScreen(
                    onAddRequest = {
                        // For simplicity, we'll navigate to a dummy product for new requests
                        navController.navigate("request_form/DUMMY/1")
                    },
                    onEditRequest = { request ->
                        navController.navigate("request_form/${request.productCode}/${request.variantIndex}")
                    }
                )
            }
            composable(
                "product_details/{productCode}",
                arguments = listOf(navArgument("productCode") { type = NavType.StringType })
            ) { backStackEntry ->
                ProductDetailsScreen(
                    navController = navController,
                    productCode = backStackEntry.arguments?.getString("productCode") ?: ""
                )
            }
            composable(
                "request_form/{productCode}/{variantIndex}",
                arguments = listOf(
                    navArgument("productCode") { type = NavType.StringType },
                    navArgument("variantIndex") { type = NavType.IntType }
                )
            ) { backStackEntry ->
                RequestFormScreen(
                    navController = navController,
                    productCode = backStackEntry.arguments?.getString("productCode") ?: "",
                    variantIndex = backStackEntry.arguments?.getInt("variantIndex") ?: 0
                )
            }
        }
    }
}