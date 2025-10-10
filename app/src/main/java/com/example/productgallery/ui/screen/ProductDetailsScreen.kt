package com.example.productgallery.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Divider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavController
import com.example.productgallery.data.db.AppDatabase
import com.example.productgallery.ui.viewmodel.GalleryViewModel
import com.example.productgallery.ui.viewmodel.GalleryViewModelFactory

@Composable
fun ProductDetailsScreen(
    navController: NavController,
    productCode: String
) {
    val context = LocalContext.current
    val db = AppDatabase.getDatabase(context)
    val viewModel: GalleryViewModel = viewModel(factory = GalleryViewModelFactory(db.productDao()))
    val products by viewModel.products.collectAsState()
    val productWithVariants = products.find { it.product.productCode == productCode }

    if (productWithVariants != null) {
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
        ) {
            item {
                Text(text = productWithVariants.product.description, style = MaterialTheme.typography.headlineMedium)
                Text(text = "Brand: ${productWithVariants.product.brandName}", style = MaterialTheme.typography.bodyLarge)
                Spacer(modifier = Modifier.height(16.dp))
            }
            items(productWithVariants.variants) { variant ->
                Text("Variant Index: ${variant.variantIndex}")
                Text("Stock: ${variant.stockQuantity}")
                Text("Zahedan Price: ${variant.zahedanPrice}")
                Text("Other Cities Price: ${variant.otherCitiesPrice}")
                Button(onClick = {
                    navController.navigate("request_form/${variant.productCode}/${variant.variantIndex}")
                }) {
                    Text("Add Request")
                }
                Divider(modifier = Modifier.padding(vertical = 8.dp))
            }
        }
    } else {
        Text("Product not found")
    }
}