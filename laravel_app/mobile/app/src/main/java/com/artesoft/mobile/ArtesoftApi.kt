package com.artesoft.mobile

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Query

interface ArtesoftApi {
    @GET("api/index.php")
    suspend fun me(
        @Query("resource") resource: String = "auth/me"
    ): MeResponse

    @POST("api/index.php")
    suspend fun login(
        @Query("resource") resource: String,
        @Header("X-CSRF-Token") csrfToken: String,
        @Body request: LoginRequest
    ): LoginResponse

    @GET("api/index.php")
    suspend fun orders(
        @Query("resource") resource: String = "orders"
    ): OrdersResponse
}
