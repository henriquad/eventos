<?php

function extratoFalha($dbcon, $mensagem, $stmt = null)
{
    if ($stmt) {
        mysqli_stmt_close($stmt);
    }

    if ($dbcon) {
        mysqli_close($dbcon);
    }

    http_response_code(500);
    exit($mensagem);
}

function extratoExecutar($dbcon, $sql, $types = '', ...$params)
{
    $stmt = mysqli_prepare($dbcon, $sql);
    if (!$stmt) {
        extratoFalha($dbcon, 'Falha ao preparar SQL do extrato: ' . mysqli_error($dbcon));
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $erro = mysqli_stmt_error($stmt);
        extratoFalha($dbcon, 'Falha ao executar SQL do extrato: ' . $erro, $stmt);
    }

    $afetados = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $afetados;
}

function extratoNormalizarData($valor)
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $dtBrCurto = DateTime::createFromFormat('j/n/Y', $valor);
    if ($dtBrCurto && $dtBrCurto->format('j/n/Y') === $valor) {
        return $dtBrCurto->format('Y-m-d');
    }

    $dtBr = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dtBr && $dtBr->format('d/m/Y') === $valor) {
        return $dtBr->format('Y-m-d');
    }

    $dtIso = DateTime::createFromFormat('Y-m-d', $valor);
    if ($dtIso && $dtIso->format('Y-m-d') === $valor) {
        return $valor;
    }

    return null;
}

function extratoPrepararMovtos($dbcon, $apelido, $senha, $dataInicial, $dataFinal)
{
    $insertBase = "INSERT INTO movtos (dataM, diaCorreto, diaU, Util, evento, M, A, Apelido, ValorE, Prorroga, senha, grupo, DC, Ativo, `COL 16`) ";
    $selectBase = "SELECT datas.DataMes, CAST(datas.DiaUtil AS UNSIGNED), IFNULL(eventos.diaU, 0), CAST(datas.Util AS UNSIGNED), eventos.evento, CAST(datas.M AS UNSIGNED), CAST(datas.A AS UNSIGNED), eventos.apelido, eventos.valorE, eventos.prorroga, eventos.senha, eventos.grupo, eventos.DC, eventos.ativo, datas.DiaUtil FROM eventos ";
    $filtroAtivos = " WHERE eventos.apelido = ? AND eventos.senha = ? AND eventos.ativo = 'Sim'";
    $filtroPeriodoDatas = " AND datas.DataMes BETWEEN ? AND ?";
    $caseMesNumero = "CASE eventos.mesR WHEN 'Jan' THEN 1 WHEN 'Fev' THEN 2 WHEN 'Mar' THEN 3 WHEN 'Abr' THEN 4 WHEN 'Mai' THEN 5 WHEN 'Jun' THEN 6 WHEN 'Jul' THEN 7 WHEN 'Ago' THEN 8 WHEN 'Set' THEN 9 WHEN 'Out' THEN 10 WHEN 'Nov' THEN 11 WHEN 'Dez' THEN 12 ELSE 0 END";
    $caseSemNumero = "CASE eventos.semM WHEN 'Jan' THEN 1 WHEN 'Fev' THEN 2 WHEN 'Mar' THEN 3 WHEN 'Abr' THEN 4 WHEN 'Mai' THEN 5 WHEN 'Jun' THEN 6 WHEN 'Jul' THEN 7 WHEN 'Ago' THEN 8 WHEN 'Set' THEN 9 WHEN 'Out' THEN 10 WHEN 'Nov' THEN 11 WHEN 'Dez' THEN 12 ELSE 0 END";

    mysqli_begin_transaction($dbcon);

    try {
        extratoExecutar(
            $dbcon,
            "DELETE FROM movtos WHERE Apelido = ? AND senha = ?",
            'ss',
            $apelido,
            $senha
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON eventos.dataF = datas.DataMes" . $filtroAtivos . $filtroPeriodoDatas,
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON CAST(eventos.diaM AS UNSIGNED) = CAST(datas.N AS UNSIGNED)" . $filtroAtivos . $filtroPeriodoDatas,
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON BINARY eventos.diaS = BINARY datas.Sem" . $filtroAtivos . $filtroPeriodoDatas,
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON " . $caseSemNumero . " = CAST(datas.M AS UNSIGNED) AND CAST(eventos.semN AS UNSIGNED) = CAST(datas.SemN AS UNSIGNED) AND BINARY eventos.semD = BINARY datas.Sem" . $filtroAtivos . $filtroPeriodoDatas,
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON " . $caseMesNumero . " = CAST(datas.M AS UNSIGNED) AND CAST(eventos.diaR AS UNSIGNED) = CAST(datas.N AS UNSIGNED)" . $filtroAtivos . $filtroPeriodoDatas,
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "JOIN datas ON CAST(datas.Util AS UNSIGNED) = 1 WHERE eventos.diario = 'Sim' AND eventos.apelido = ? AND eventos.senha = ? AND eventos.ativo = 'Sim' AND datas.DataMes BETWEEN ? AND ?",
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON eventos.diaU = CAST(datas.DiaUtil AS UNSIGNED) WHERE eventos.apelido = ? AND eventos.senha = ? AND CAST(datas.Util AS UNSIGNED) = 1 AND eventos.ativo = 'Sim' AND datas.DataMes BETWEEN ? AND ?",
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            $insertBase . $selectBase . "INNER JOIN datas ON eventos.UPA = CAST(datas.UPAm AS UNSIGNED) WHERE eventos.apelido = ? AND eventos.senha = ? AND CAST(datas.UPAm AS UNSIGNED) > 0 AND eventos.ativo = 'Sim' AND datas.DataMes BETWEEN ? AND ?",
            'ssss',
            $apelido,
            $senha,
            $dataInicial,
            $dataFinal
        );

        extratoExecutar(
            $dbcon,
            "UPDATE movtos SET diaCorreto = CASE
            WHEN IFNULL(diaU, 0) > 0 THEN diaCorreto
            WHEN IFNULL(Util, 0) = 1 THEN diaCorreto
            WHEN Prorroga = 'Sim' THEN diaCorreto + 1
            WHEN Prorroga = 'Nulo' THEN 0
            ELSE diaCorreto
        END
        WHERE Apelido = ? AND senha = ? AND Ativo = 'Sim'",
            'ss',
            $apelido,
            $senha
        );

        extratoExecutar(
            $dbcon,
            "UPDATE movtos
        INNER JOIN datas ON movtos.dataM = datas.DataMes
        SET movtos.M = IF(movtos.M = 12, 1, movtos.M + 1),
            movtos.A = IF(movtos.M = 12, movtos.A + 1, movtos.A),
            movtos.diaCorreto = 1,
            movtos.evento = CONCAT(' * ', movtos.evento)
        WHERE movtos.diaCorreto > CAST(datas.U AS UNSIGNED)
          AND CAST(datas.U AS UNSIGNED) > 0
          AND movtos.Apelido = ?
          AND movtos.senha = ?
          AND movtos.Ativo = 'Sim'",
            'ss',
            $apelido,
            $senha
        );

        mysqli_commit($dbcon);
    } catch (Throwable $erro) {
        mysqli_rollback($dbcon);
        mysqli_close($dbcon);
        http_response_code(500);
        exit('Falha ao gerar extrato: ' . $erro->getMessage());
    }
}
