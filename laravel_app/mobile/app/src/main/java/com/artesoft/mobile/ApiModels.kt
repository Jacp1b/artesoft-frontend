package com.artesoft.mobile

import com.google.gson.annotations.SerializedName

data class LoginRequest(
    val email: String,
    val password: String
)

data class MeResponse(
    val ok: Boolean,
    val user: UserInfo?,
    val csrfToken: String?
)

data class LoginResponse(
    val ok: Boolean,
    val user: UserInfo?,
    val message: String?,
    val csrfToken: String?
)

data class OrdersResponse(
    val ok: Boolean,
    val data: List<Order>? = emptyList(),
    val message: String?
)

data class UserInfo(
    val id: Int,
    val name: String,
    val email: String,
    val role: String
)

data class Order(
    val id: String,
    val customer: Customer,
    val items: List<OrderItem>,
    val subtotal: Int,
    val shipping: Int,
    val total: Int,
    val status: String,
    val payment: String,
    val seller: Seller?,
    val createdAt: String,
    val demo: Boolean,
    val test: Boolean?
)

data class Customer(
    val name: String,
    val email: String,
    val phone: String?,
    val city: String?,
    val address: String?,
    val notes: String?
)

data class OrderItem(
    val productId: String?,
    val name: String,
    val price: Int,
    val quantity: Int
)

data class Seller(
    val id: Int,
    val name: String
)
