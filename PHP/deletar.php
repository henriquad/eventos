<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

include_once "Conectar_BaseH.php";

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

eventosGarantirSchema($dbcon);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=id_invalido');
    exit();
}

$sql = "DELETE FROM eventos WHERE id = ? AND apelido = ? AND senha = ?";
$stmt = mysqli_prepare($dbcon, $sql);

if (!$stmt) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=preparo_delete');
    exit();
}

mysqli_stmt_bind_param($stmt, "iss", $id, $apelido, $senha);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=execucao_delete');
    exit();
}

$registrosAfetados = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);
mysqli_close($dbcon);

if ($registrosAfetados > 0) {
    header('Location: ListaEventos.php?excluido=1&id=' . $id);
    exit();
}

header('Location: ListaEventos.php?erro=1&msg=registro_nao_encontrado&id=' . $id);
exit();
