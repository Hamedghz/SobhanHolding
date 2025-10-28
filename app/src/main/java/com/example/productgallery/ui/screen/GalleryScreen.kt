package com.example.productgallery.ui.screen

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavController
import com.example.productgallery.data.db.AppDatabase
import com.example.productgallery.ui.composables.ProductCard
import com.example.productgallery.ui.composables.SearchBar
import com.example.productgallery.ui.viewmodel.GalleryViewModel
import com.example.productgallery.ui.viewmodel.GalleryViewModelFactory
import androidx.compose.ui.platform.LocalContext

@Composable
fun GalleryScreen(navController: NavController) {
    val context = LocalContext.current
    val db = AppDatabase.getDatabase(context)
    val viewModel: GalleryViewModel = viewModel(factory = GalleryViewModelFactory(db.productDao()))

    val products by viewModel.products.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        MaterialTheme.colorScheme.primary.copy(alpha = 0.1f),
                        MaterialTheme.colorScheme.secondary.copy(alpha = 0.1f)
                    )
                )
            )
    ) {
        SearchBar(
            query = searchQuery,
            onQueryChanged = { viewModel.onSearchQueryChanged(it) },
            onSearch = { /* Not used in this implementation */ }
        )

        if (isLoading) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        } else if (products.isEmpty() && searchQuery.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text(
                    text = "No products found. Please import an Excel file.",
                    color = Color.White
                )
            }
        } else {
            LazyVerticalGrid(
                columns = GridCells.Fixed(2),
                contentPadding = PaddingValues(8.dp),
                modifier = Modifier.fillMaxSize()
            ) {
                items(products) { productWithVariants ->
                    ProductCard(
                        productWithVariants = productWithVariants,
                        onClick = {
                            navController.navigate("product_details/${productWithVariants.product.productCode}")
                        }
                    )
                }
            }
        }
    }
}