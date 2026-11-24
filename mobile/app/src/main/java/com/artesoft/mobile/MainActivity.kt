package com.artesoft.mobile

import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.artesoft.mobile.databinding.ActivityMainBinding
import com.google.gson.Gson
import kotlinx.coroutines.launch
import okhttp3.JavaNetCookieJar
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.net.CookieManager
import java.net.CookiePolicy

class MainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding
    private lateinit var api: ArtesoftApi
    private val adapter = OrdersAdapter()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.ordersList.layoutManager = LinearLayoutManager(this)
        binding.ordersList.adapter = adapter
        binding.loginButton.setOnClickListener { login() }
        binding.refreshButton.setOnClickListener { loadOrders() }
        setLoading(false)
    }

    private fun login() {
        val email = binding.emailInput.text.toString().trim()
        val password = binding.passwordInput.text.toString()
        if (email.isEmpty() || password.isEmpty()) {
            showError("Ingresa el correo y la contraseña.")
            return
        }

        setLoading(true)
        lifecycleScope.launch {
            try {
                val client = createHttpClient()
                api = createApi(client)
                val me = api.me()
                val csrf = me.csrfToken
                    ?: throw IllegalStateException("El servidor no entregó el token de seguridad.")
                val response = api.login("auth/login", csrf, LoginRequest(email, password))
                if (response.ok && response.user != null) {
                    binding.loginGroup.visibility = View.GONE
                    binding.ordersGroup.visibility = View.VISIBLE
                    binding.userName.text = response.user.name
                    loadOrders()
                } else {
                    showError(response.message ?: "No fue posible iniciar sesión.")
                    setLoading(false)
                }
            } catch (error: Exception) {
                showError(error.message ?: "No se pudo conectar con ARTESOFT.")
                setLoading(false)
            }
        }
    }

    private fun loadOrders() {
        setLoading(true)
        lifecycleScope.launch {
            try {
                val response = api.orders()
                if (response.ok) {
                    val orders = response.data.orEmpty()
                        .filterNot { it.demo }
                        .sortedByDescending { it.createdAt }
                    adapter.submit(orders)
                    binding.ordersEmpty.visibility =
                        if (orders.isEmpty()) View.VISIBLE else View.GONE
                    binding.ordersCount.text = "${orders.size} pedidos"
                    showError("")
                } else {
                    showError(response.message ?: "No fue posible cargar los pedidos.")
                }
            } catch (error: Exception) {
                showError(error.message ?: "No se pudo cargar los pedidos.")
            } finally {
                setLoading(false)
            }
        }
    }

    private fun createHttpClient(): OkHttpClient {
        val cookieManager = CookieManager().apply {
            setCookiePolicy(CookiePolicy.ACCEPT_ALL)
        }
        return OkHttpClient.Builder()
            .cookieJar(JavaNetCookieJar(cookieManager))
            .build()
    }

    private fun createApi(client: OkHttpClient): ArtesoftApi {
        return Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(Gson()))
            .build()
            .create(ArtesoftApi::class.java)
    }

    private fun setLoading(loading: Boolean) {
        binding.progress.visibility = if (loading) View.VISIBLE else View.GONE
    }

    private fun showError(message: String) {
        binding.errorText.text = message
        binding.errorText.visibility = if (message.isBlank()) View.GONE else View.VISIBLE
    }
}
