package com.sobhan.offlinegallery

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.material3.Surface
import com.sobhan.offlinegallery.ui.theme.OfflineGalleryTheme
import com.sobhan.offlinegallery.ui.components.OfflineGalleryApp

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            OfflineGalleryTheme {
                Surface {
                    OfflineGalleryApp()
                }
            }
        }
    }
}
