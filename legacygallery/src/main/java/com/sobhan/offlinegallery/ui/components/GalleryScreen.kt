package com.sobhan.offlinegallery.ui.components

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.sobhan.offlinegallery.data.ProductVariant
import com.sobhan.offlinegallery.service.GalleryStateHolder
import com.sobhan.offlinegallery.ui.theme.glassCardColors

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GalleryScreen(
    modifier: Modifier = Modifier,
    paddingValues: PaddingValues,
    stateHolder: GalleryStateHolder
) {
    val uiState = stateHolder.uiState

    LazyVerticalGrid(
        modifier = modifier.fillMaxSize(),
        columns = GridCells.Adaptive(180.dp),
        contentPadding = PaddingValues(16.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        items(uiState.variants) { variant ->
            VariantCard(variant)
        }
    }
}

@Composable
private fun VariantCard(variant: ProductVariant) {
    Card(colors = glassCardColors()) {
        Column(
            modifier = Modifier.fillMaxSize(),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            val placeholder = painterResource(id = com.sobhan.offlinegallery.R.drawable.ic_placeholder)
            Image(
                painter = placeholder,
                contentDescription = variant.productCode,
                contentScale = ContentScale.Crop
            )
            Text(
                text = variant.description,
                style = MaterialTheme.typography.titleMedium,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = "Variant #${variant.variantIndex}",
                style = MaterialTheme.typography.bodyMedium
            )
        }
    }
}
