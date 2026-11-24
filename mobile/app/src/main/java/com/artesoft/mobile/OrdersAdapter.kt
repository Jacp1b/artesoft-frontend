package com.artesoft.mobile

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.artesoft.mobile.databinding.ItemOrderBinding
import java.text.NumberFormat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

class OrdersAdapter : RecyclerView.Adapter<OrdersAdapter.OrderViewHolder>() {
    private val orders = mutableListOf<Order>()
    private val currency = NumberFormat.getCurrencyInstance(Locale("es", "CO")).apply {
        maximumFractionDigits = 0
    }

    fun submit(orders: List<Order>) {
        this.orders.clear()
        this.orders.addAll(orders)
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderViewHolder {
        val binding = ItemOrderBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return OrderViewHolder(binding)
    }

    override fun getItemCount(): Int = orders.size

    override fun onBindViewHolder(holder: OrderViewHolder, position: Int) {
        holder.bind(orders[position])
    }

    inner class OrderViewHolder(private val binding: ItemOrderBinding) :
        RecyclerView.ViewHolder(binding.root) {
        fun bind(order: Order) {
            binding.orderId.text = order.id
            binding.orderStatus.text = order.status
            binding.orderCustomer.text = order.customer.name
            binding.orderTotal.text = currency.format(order.total)
            binding.orderItems.text = order.items.joinToString(" · ") {
                "${it.quantity} × ${it.name}"
            }
            binding.orderDate.text = formatDate(order.createdAt)
        }
    }

    private fun formatDate(value: String): String {
        return try {
            val date = OffsetDateTime.parse(value)
            date.format(DateTimeFormatter.ofPattern("d MMM yyyy · h:mm a", Locale("es", "CO")))
        } catch (_: Exception) {
            value
        }
    }
}
