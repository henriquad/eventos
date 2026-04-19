<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function eventosLoginValido()
{
    return isset($_SESSION['apelido'], $_SESSION['senha'])
        && trim((string)$_SESSION['apelido']) !== ''
        && trim((string)$_SESSION['senha']) !== '';
}

function eventosExigirLogin()
{
    if (eventosLoginValido()) {
        return;
    }

    header('Location: ../index.html?erro=login');
    exit();
}

function eventosApelidoSessao()
{
    return isset($_SESSION['apelido']) ? (string)$_SESSION['apelido'] : '';
}

function eventosSenhaSessao()
{
    return isset($_SESSION['senha']) ? (string)$_SESSION['senha'] : '';
}
