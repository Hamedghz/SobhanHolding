package com.example.productgallery.ui.screens.importer

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.example.productgallery.domain.ImportState

@Composable
fun ImportScreen(
    viewModel: ImportViewModel,
    modifier: Modifier = Modifier
) {
    val importState by viewModel.state.collectAsState()
    val launcher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenDocument(),
        onResult = { uri: Uri? ->
            uri?.let(viewModel::import)
        }
    )

    LaunchedEffect(Unit) {
        launcher.launch(arrayOf("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel"))
    }

    Surface(modifier = modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = when (importState) {
                    ImportState.Idle -> "Select an Excel file to import the catalog."
                    is ImportState.InProgress -> (importState as ImportState.InProgress).message
                    is ImportState.Success -> "Import completed. ${(importState as ImportState.Success).importedProducts} products processed."
                    is ImportState.Error -> "Import failed: ${(importState as ImportState.Error).message}"
                },
                style = MaterialTheme.typography.bodyLarge,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(16.dp))
            when (importState) {
                ImportState.Idle -> Button(onClick = {
                    launcher.launch(arrayOf("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel"))
                }) {
                    Text("Select Excel File")
                }
                is ImportState.InProgress -> CircularProgressIndicator()
                is ImportState.Success -> Button(onClick = {
                    launcher.launch(arrayOf("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel"))
                }) {
                    Text("Import Another File")
                }
                is ImportState.Error -> Button(onClick = {
                    launcher.launch(arrayOf("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel"))
                }) {
                    Text("Retry")
                }
            }
        }
    }
}
