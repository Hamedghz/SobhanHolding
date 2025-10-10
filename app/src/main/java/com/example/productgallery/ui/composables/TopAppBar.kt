package com.example.productgallery.ui.composables

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.productgallery.data.db.AppDatabase
import com.example.productgallery.service.ErrorHandler
import com.example.productgallery.service.ExcelService
import com.example.productgallery.ui.viewmodel.ExcelImportViewModel
import com.example.productgallery.ui.viewmodel.ExcelImportViewModelFactory
import com.example.productgallery.ui.viewmodel.ImportState
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TopAppBar(title: String) {
    var showMenu by remember { mutableStateOf(false) }
    val context = LocalContext.current
    val db = AppDatabase.getDatabase(context)
    val excelService = ExcelService(context, db.productDao())
    val errorHandler = ErrorHandler()
    val viewModel: ExcelImportViewModel = viewModel(factory = ExcelImportViewModelFactory(excelService, errorHandler))
    val coroutineScope = rememberCoroutineScope()
    val importState by viewModel.importState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(importState) {
        when (val state = importState) {
            is ImportState.Success -> {
                coroutineScope.launch {
                    snackbarHostState.showSnackbar("File imported successfully.")
                }
            }
            is ImportState.Error -> {
                coroutineScope.launch {
                    snackbarHostState.showSnackbar("Import failed: ${state.message}")
                }
            }
            else -> {}
        }
    }

    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri ->
        uri?.let {
            coroutineScope.launch {
                viewModel.importExcelFile(it)
            }
        }
    }

    TopAppBar(
        title = { Text(text = title) },
        actions = {
            IconButton(onClick = { showMenu = !showMenu }) {
                Icon(Icons.Default.MoreVert, contentDescription = "More")
            }
            DropdownMenu(
                expanded = showMenu,
                onDismissRequest = { showMenu = false }
            ) {
                DropdownMenuItem(
                    text = { Text("Update File") },
                    onClick = {
                        filePickerLauncher.launch("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
                        showMenu = false
                    }
                )
            }
        }
    )
}