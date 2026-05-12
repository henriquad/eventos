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

// Rotina para registrar acesso
$dataHora = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$registro = "$dataHora | $apelido | $ip\n";
$arquivo = __DIR__ . '/../acessos.txt';
file_put_contents($arquivo, $registro, FILE_APPEND | LOCK_EX);

header('Location: ../incluir.html?fresh=1', true, 303);
exit();
