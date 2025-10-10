package com.example.productgallery.ui.screen

import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavController
import com.example.productgallery.data.db.AppDatabase
import com.example.productgallery.service.RequestService
import com.example.productgallery.ui.composables.RequestForm
import com.example.productgallery.ui.viewmodel.RequestViewModel
import com.example.productgallery.ui.viewmodel.RequestViewModelFactory

@Composable
fun RequestFormScreen(
    navController: NavController,
    productCode: String,
    variantIndex: Int
) {
    val context = LocalContext.current
    val db = AppDatabase.getDatabase(context)
    val requestService = RequestService(db.requestDao())
    val viewModel: RequestViewModel = viewModel(factory = RequestViewModelFactory(requestService))

    RequestForm(
        productCode = productCode,
        variantIndex = variantIndex,
        onSave = { customer, quantity, notes ->
            viewModel.createRequest(
                productCode = productCode,
                variantIndex = variantIndex,
                customer = customer,
                quantity = quantity,
                notes = notes
            )
            navController.popBackStack()
        }
    )
}