package com.example.productgallery.ui.screens.requests

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp

@Composable
fun RequestsScreen(
    viewModel: RequestsViewModel,
    modifier: Modifier = Modifier
) {
    val requests by viewModel.requests.collectAsState()

    Surface(modifier = modifier.fillMaxSize()) {
        if (requests.isEmpty()) {
            Column(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "No requests captured yet.",
                    style = MaterialTheme.typography.bodyLarge
                )
            }
        } else {
            LazyColumn(modifier = Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(requests, key = { it.id }) { request ->
                    Card(elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)) {
                        Column {
                            Text(
                                text = "Request #${request.id}",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.SemiBold
                            )
                            Text("Product: ${request.productCode} - Variant ${request.variantIndex}")
                            Text("Customer: ${request.customer}")
                            Text("Quantity: ${request.quantity}")
                            request.notes?.takeIf { it.isNotEmpty() }?.let {
                                Text("Notes: $it")
                            }
                        }
                    }
                }
            }
        }
    }
}
