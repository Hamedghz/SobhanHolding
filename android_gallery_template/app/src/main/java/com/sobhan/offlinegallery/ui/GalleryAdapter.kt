package com.sobhan.offlinegallery.ui

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.sobhan.offlinegallery.databinding.ItemGalleryBinding
import com.sobhan.offlinegallery.model.GalleryItem
import java.text.NumberFormat
import java.util.Locale

class GalleryAdapter(
    private val items: List<GalleryItem>
) : RecyclerView.Adapter<GalleryAdapter.VH>() {

    inner class VH(val b: ItemGalleryBinding) : RecyclerView.ViewHolder(b.root)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val inf = LayoutInflater.from(parent.context)
        return VH(ItemGalleryBinding.inflate(inf, parent, false))
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val it = items[position]
        holder.b.title.text = it.name
        val subtitle = buildString {
            append(it.brand)
            it.price?.let { p ->
                append("  •  ")
                append(NumberFormat.getInstance(Locale.getDefault()).format(p))
            }
        }
        holder.b.subtitle.text = subtitle

        val uri = "file:///android_asset/${it.img}"
        Glide.with(holder.b.image.context)
            .load(uri)
            .thumbnail(0.25f)
            .into(holder.b.image)
    }

    override fun getItemCount(): Int = items.size
}
