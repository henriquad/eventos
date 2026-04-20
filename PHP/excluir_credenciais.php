<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/eventos_schema.php';
include_once __DIR__ . '/Conectar_BaseH.php';

$apelidoPost = trim((string)($_POST['username'] ?? ''));
$senhaPost = trim((string)($_POST['password'] ?? ''));

if (eventosLoginValido()) {
    $apelido = trim(eventosApelidoSessao());
    $senha = trim(eventosSenhaSessao());
} else {
    $apelido = $apelidoPost;
    $senha = $senhaPost;
}

if ($apelido === '' || $senha === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Apelido e senha sao obrigatorios para excluir registros.'
    ]);
    mysqli_close($dbcon);
    exit();
}

eventosGarantirSchema($dbcon);

mysqli_begin_transaction($dbcon);

try {
    $eventosExcluidos = 0;
    $movtosExcluidos = 0;

    if (eventosTableExists($dbcon, 'eventos')) {
        $sqlEventos = 'DELETE FROM eventos WHERE apelido = ? AND senha = ?';
        $stmtEventos = mysqli_prepare($dbcon, $sqlEventos);

        if (!$stmtEventos) {
            throw new Exception('Falha ao preparar exclusao em eventos.');
        }

        mysqli_stmt_bind_param($stmtEventos, 'ss', $apelido, $senha);

        if (!mysqli_stmt_execute($stmtEventos)) {
            $erro = mysqli_stmt_error($stmtEventos);
            mysqli_stmt_close($stmtEventos);
            throw new Exception('Falha ao excluir eventos: ' . $erro);
        }

        $eventosExcluidos = mysqli_stmt_affected_rows($stmtEventos);
        mysqli_stmt_close($stmtEventos);
    }

    if (eventosTableExists($dbcon, 'movtos')) {
        $sqlMovtos = 'DELETE FROM movtos WHERE Apelido = ? AND senha = ?';
        $stmtMovtos = mysqli_prepare($dbcon, $sqlMovtos);

        if (!$stmtMovtos) {
            throw new Exception('Falha ao preparar exclusao em movtos.');
        }

        mysqli_stmt_bind_param($stmtMovtos, 'ss', $apelido, $senha);

        if (!mysqli_stmt_execute($stmtMovtos)) {
            $erro = mysqli_stmt_error($stmtMovtos);
            mysqli_stmt_close($stmtMovtos);
            throw new Exception('Falha ao excluir movtos: ' . $erro);
        }

        $movtosExcluidos = mysqli_stmt_affected_rows($stmtMovtos);
        mysqli_stmt_close($stmtMovtos);
    }

    mysqli_commit($dbcon);

    echo json_encode([
        'success' => true,
        'eventosExcluidos' => $eventosExcluidos,
        'movtosExcluidos' => $movtosExcluidos,
        'message' => 'Registros relacionados excluidos com sucesso.'
    ]);
} catch (Throwable $erro) {
    mysqli_rollback($dbcon);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $erro->getMessage()
    ]);
}

mysqli_close($dbcon);
