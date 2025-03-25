<?php
// Este script ayuda a restablecer la sesión y romper bucles de redirección

// Destruir la sesión actual
session_start();
session_destroy();

// Eliminar cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Eliminar cookies CSRF
if (isset($_COOKIE['csrf_cookie_name'])) {
    setcookie('csrf_cookie_name', '', time() - 42000, '/');
}

// Eliminar cookies de recuerdo
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 42000, '/');
}

// Eliminar cookies de sesión de CI
if (isset($_COOKIE['ci_session'])) {
    setcookie('ci_session', '', time() - 42000, '/');
}

// Redirigir al login con mensaje
header('Location: auth/login?reset=true');
echo "Sesión restablecida. <a href='/auth/login'>Haga clic aquí para ir a la página de login</a>";