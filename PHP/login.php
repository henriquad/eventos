<?php

require_once __DIR__ . '/auth_session.php';

$apelido = trim((string)($_POST['username'] ?? ''));
$senha = trim((string)($_POST['password'] ?? ''));

if ($apelido === '' || $senha === '') {
    header('Location: ../index.html?erro=credenciais');
    exit();
}

$_SESSION['apelido'] = $apelido;
$_SESSION['senha'] = $senha;

header('Location: ../incluir.html?fresh=1', true, 303);
exit();
