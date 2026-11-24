# ARTESOFT Mobile

Aplicacion Android en Kotlin para consultar pedidos de la API ARTESOFT.

## Requisitos

- Android Studio Iguana o posterior.
- JDK 17.
- API ARTESOFT ejecutandose con PHP/Laravel.
- El wrapper descarga Gradle 8.9 automaticamente la primera vez que se ejecuta.

## Configurar la URL

Configura `artesoftApiBaseUrl` en `gradle.properties`:

- Emulador Android apuntando a XAMPP local:
  `http://10.0.2.2/artesoft-frontend/`
- Dispositivo fisico en la misma red:
  `http://IP_DE_TU_PC/artesoft-frontend/`
- Servidor HTTPS publico:
  `https://tu-dominio.com/`

Para un dominio HTTPS o fisico, mantén el puerto y la ruta exacta de la API.

La app consulta `api/index.php` para obtener el token CSRF, iniciar sesión y cargar
los pedidos. El cliente conserva la cookie de sesión para que la API identifique
al usuario al consultar sus pedidos.

## Ejecutar

1. Abre la carpeta `mobile/` como proyecto en Android Studio y sincroniza Gradle.
2. Desde una terminal en `mobile/`, ejecuta `.\gradlew.bat :app:assembleDebug` para compilar.
3. Ejecuta la app en un emulador o dispositivo.
4. Inicia sesion con una cuenta de administrador, operador, consulta o cliente.

Las cuentas cliente ven sus pedidos. Administrador, operador y consulta pueden ver los pedidos disponibles segun la API actual.
