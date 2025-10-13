package com.sobhan.offlinegallery.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.material3.CardDefaults

private val LightColorPalette = lightColorScheme(
    primary = Color(0xFF4A90E2),
    secondary = Color(0xFF7F8C8D),
    tertiary = Color(0xFF50E3C2)
)

private val DarkColorPalette = darkColorScheme(
    primary = Color(0xFF50E3C2),
    secondary = Color(0xFF95A5A6),
    tertiary = Color(0xFF4A90E2)
)

@Composable
fun OfflineGalleryTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colors = if (darkTheme) DarkColorPalette else LightColorPalette

    MaterialTheme(
        colorScheme = colors,
        typography = Typography(),
        shapes = Shapes(),
        content = content
    )
}

@Immutable
fun glassCardColors() = CardDefaults.cardColors(
    containerColor = Color.White.copy(alpha = 0.2f),
    contentColor = Color.White
)
