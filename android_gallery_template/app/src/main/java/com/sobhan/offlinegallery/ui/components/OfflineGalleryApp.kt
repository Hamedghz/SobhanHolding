package com.sobhan.offlinegallery.ui.components

import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.sobhan.offlinegallery.service.GalleryStateHolder

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OfflineGalleryApp(stateHolder: GalleryStateHolder = GalleryStateHolder()) {
    Scaffold(
        topBar = {
            TopAppBar(title = { Text(text = "Offline Product Gallery") })
        }
    ) { padding ->
        GalleryScreen(
            modifier = Modifier.fillMaxSize(),
            paddingValues = padding,
            stateHolder = stateHolder
        )
    }
}
