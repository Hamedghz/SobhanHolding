package com.example.productgallery.ui.composables

import androidx.compose.foundation.layout.*
import androidx.compose.material3.Button
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

@Composable
fun RequestForm(
    productCode: String,
    variantIndex: Int,
    initialCustomer: String = "",
    initialQuantity: String = "",
    initialNotes: String = "",
    onSave: (customer: String, quantity: Int, notes: String) -> Unit
) {
    var customer by remember { mutableStateOf(initialCustomer) }
    var quantity by remember { mutableStateOf(initialQuantity) }
    var notes by remember { mutableStateOf(initialNotes) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp)
    ) {
        Text("Product Code: $productCode")
        Text("Variant Index: $variantIndex")
        Spacer(modifier = Modifier.height(16.dp))
        OutlinedTextField(
            value = customer,
            onValueChange = { customer = it },
            label = { Text("Customer") },
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(modifier = Modifier.height(8.dp))
        OutlinedTextField(
            value = quantity,
            onValueChange = { quantity = it },
            label = { Text("Quantity") },
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(modifier = Modifier.height(8.dp))
        OutlinedTextField(
            value = notes,
            onValueChange = { notes = it },
            label = { Text("Notes") },
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(modifier = Modifier.height(16.dp))
        Button(
            onClick = {
                onSave(customer, quantity.toIntOrNull() ?: 0, notes)
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Save Request")
        }
    }
}