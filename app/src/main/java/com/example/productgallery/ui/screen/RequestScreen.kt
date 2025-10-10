package com.example.productgallery.ui.screen

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.productgallery.data.db.AppDatabase
import com.example.productgallery.data.model.Request
import com.example.productgallery.service.ErrorHandler
import com.example.productgallery.service.RequestService
import com.example.productgallery.ui.viewmodel.ExportState
import com.example.productgallery.ui.viewmodel.RequestViewModel
import com.example.productgallery.ui.viewmodel.RequestViewModelFactory
import kotlinx.coroutines.launch

@Composable
fun RequestScreen(
    onAddRequest: () -> Unit,
    onEditRequest: (Request) -> Unit
) {
    val context = LocalContext.current
    val db = AppDatabase.getDatabase(context)
    val requestService = RequestService(context, db.requestDao())
    val errorHandler = ErrorHandler()
    val viewModel: RequestViewModel = viewModel(factory = RequestViewModelFactory(requestService, errorHandler))
    val requests by viewModel.requests.collectAsState()
    val exportState by viewModel.exportState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    val coroutineScope = rememberCoroutineScope()

    LaunchedEffect(exportState) {
        when (val state = exportState) {
            is ExportState.Success -> {
                coroutineScope.launch {
                    snackbarHostState.showSnackbar("Export successful: ${state.filePath}")
                }
                viewModel.resetExportState()
            }
            is ExportState.Error -> {
                coroutineScope.launch {
                    snackbarHostState.showSnackbar("Export failed: ${state.message}")
                }
                viewModel.resetExportState()
            }
            is ExportState.Exporting -> {
                coroutineScope.launch {
                    snackbarHostState.showSnackbar("Exporting...")
                }
            }
            else -> {}
        }
    }

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        floatingActionButton = {
            FloatingActionButton(onClick = onAddRequest) {
                Icon(Icons.Default.Add, contentDescription = "Add Request")
            }
        }
    ) { padding ->
        Column(modifier = Modifier.padding(padding)) {
            Button(
                onClick = { viewModel.exportRequests() },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Text("Export to CSV")
            }
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 16.dp)
            ) {
                items(requests) { request ->
                    RequestItem(
                        request = request,
                        onEdit = { onEditRequest(request) },
                        onDelete = { viewModel.deleteRequest(request) }
                    )
                    Divider()
                }
            }
        }
    }
}

@Composable
fun RequestItem(
    request: Request,
    onEdit: () -> Unit,
    onDelete: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column {
            Text(text = "Product: ${request.productCode} - Variant: ${request.variantIndex}", style = MaterialTheme.typography.bodyLarge)
            Text(text = "Customer: ${request.customer}", style = MaterialTheme.typography.bodyMedium)
            Text(text = "Quantity: ${request.quantity}", style = MaterialTheme.typography.bodyMedium)
        }
        Row {
            IconButton(onClick = onEdit) {
                Icon(Icons.Default.Edit, contentDescription = "Edit Request")
            }
            IconButton(onClick = onDelete) {
                Icon(Icons.Default.Delete, contentDescription = "Delete Request")
            }
        }
    }
}