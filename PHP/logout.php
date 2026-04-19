<?php

require_once __DIR__ . '/auth_session.php';

$limparLogin = (string)($_GET['limpar'] ?? '') === '1';

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

$destino = '../index.html';
if ($limparLogin) {
    $destino .= '?limparLogin=1';
}

header('Location: ' . $destino, true, 303);
exit();
