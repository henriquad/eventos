<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();

include_once "Conectar_BaseH.php";

$apelido = eventosApelidoSessao();
$senha   = eventosSenhaSessao();

$sql = "
    SELECT
        id,
        dataM,
        evento,
        grupo,
        DC,
        ValorE
    FROM movtos
    WHERE Apelido = ? AND senha = ?
    ORDER BY dataM ASC, id ASC
";

$stmt = mysqli_prepare($dbcon, $sql);
if (!$stmt) {
    mysqli_close($dbcon);
    exit('Falha ao preparar consulta: ' . mysqli_error($dbcon));
}

mysqli_stmt_bind_param($stmt, 'ss', $apelido, $senha);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$linhas = [];
$saldo  = 0.0;

while ($row = mysqli_fetch_assoc($result)) {
    $valor = (float)$row['ValorE'];
    if ($row['DC'] === 'D') {
        $saldo -= $valor;
    } else {
        $saldo += $valor;
    }
    $row['saldo'] = $saldo;
    $linhas[] = $row;
}

mysqli_stmt_close($stmt);
mysqli_close($dbcon);

function fmtData(string $d): string
{
    // converte YYYY-MM-DD para DD/MM/YYYY
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d/m/Y') : $d;
}

function fmtVal(float $v): string
{
    return number_format($v, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eaecef;
            color: #222;
            font-size: 13px;
        }

        .wrapper {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 16px;
        }

        /* cabeçalho estilo banco */
        .extrato-header {
            background: #1a3a5c;
            color: #fff;
            border-radius: 8px 8px 0 0;
            padding: 18px 24px 14px;
        }

        .extrato-header h1 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: .5px;
        }

        .extrato-header .sub {
            font-size: 12px;
            color: #a8c0d6;
            margin-top: 4px;
        }

        .extrato-header .total-info {
            margin-top: 10px;
            font-size: 12px;
            color: #c8dae8;
        }

        /* tabela */
        .extrato-card {
            background: #fff;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #2c5282;
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            position: sticky;
            top: 0;
        }

        thead th.r {
            text-align: right;
        }

        tbody tr {
            border-bottom: 1px solid #edf0f5;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #f0f5ff;
        }

        td {
            padding: 9px 14px;
            vertical-align: middle;
        }

        td.r {
            text-align: right;
            font-family: 'Courier New', monospace;
        }

        td.data {
            font-weight: 600;
            color: #444;
            white-space: nowrap;
        }

        td.desc {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        td.grupo {
            color: #666;
            font-size: 12px;
        }

        td.debito {
            color: #c0392b;
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        td.credito {
            color: #1a7a4a;
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        td.vazio {
            color: #ccc;
            text-align: right;
            font-family: 'Courier New', monospace;
        }

        td.saldo-pos {
            color: #1a7a4a;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        td.saldo-neg {
            color: #c0392b;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            text-align: right;
        }

        /* rodapé saldo */
        tfoot tr {
            background: #f7f9fc;
        }

        tfoot td {
            padding: 12px 14px;
            font-weight: 700;
            border-top: 2px solid #2c5282;
            font-size: 13px;
        }

        tfoot td.label {
            color: #2c5282;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        tfoot td.saldo-final-pos {
            color: #1a7a4a;
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 15px;
        }

        tfoot td.saldo-final-neg {
            color: #c0392b;
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 15px;
        }

        /* navegação */
        .nav {
            margin-bottom: 16px;
        }

        .nav a {
            display: inline-block;
            padding: 6px 14px;
            background: #2c5282;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            margin-right: 8px;
        }

        .nav a:hover {
            background: #1a3a5c;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        <div class="nav">
            <a href="../menu.html">&#8592; Menu</a>
            <a href="ListaEventos.php">Lista de Eventos</a>
        </div>

        <div class="extrato-header">
            <h1>Extrato de Movimentos</h1>
            <div class="sub">Conta: <?= htmlspecialchars($apelido) ?></div>
            <div class="total-info"><?= count($linhas) ?> lançamento(s)</div>
        </div>

        <div class="extrato-card">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Grupo</th>
                        <th class="r">Débito (R$)</th>
                        <th class="r">Crédito (R$)</th>
                        <th class="r">Saldo (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($linhas as $l):
                        $dc      = $l['DC'];
                        $valor   = (float)$l['ValorE'];
                        $saldoLn = (float)$l['saldo'];
                        $dataFmt = fmtData((string)$l['dataM']);
                        $saldoCls = $saldoLn >= 0 ? 'saldo-pos' : 'saldo-neg';
                    ?>
                        <tr>
                            <td class="data"><?= $dataFmt ?></td>
                            <td class="desc" title="<?= htmlspecialchars((string)$l['evento']) ?>">
                                <?= htmlspecialchars((string)$l['evento']) ?>
                            </td>
                            <td class="grupo"><?= htmlspecialchars((string)$l['grupo']) ?></td>
                            <?php if ($dc === 'D'): ?>
                                <td class="debito"><?= fmtVal($valor) ?></td>
                                <td class="vazio">&mdash;</td>
                            <?php else: ?>
                                <td class="vazio">&mdash;</td>
                                <td class="credito"><?= fmtVal($valor) ?></td>
                            <?php endif; ?>
                            <td class="<?= $saldoCls ?>"><?= fmtVal($saldoLn) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($linhas)):
                    $ultimo = end($linhas);
                    $saldoFinal = (float)$ultimo['saldo'];
                    $sfCls = $saldoFinal >= 0 ? 'saldo-final-pos' : 'saldo-final-neg';
                ?>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="label">Saldo Final</td>
                            <td class="<?= $sfCls ?>"><?= fmtVal($saldoFinal) ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

    </div>
</body>

</html>