<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();

include_once "Conectar_BaseH.php";

$apelido = eventosApelidoSessao();
$senha   = eventosSenhaSessao();

function normalizarValorMonetarioResumoMovtos($valor)
{
    $texto = trim((string)$valor);
    if ($texto === '') {
        return 0.0;
    }

    $texto = preg_replace('/\s+/', '', $texto);
    if ($texto === null || $texto === '') {
        return 0.0;
    }

    $texto = preg_replace('/^R\$/i', '', $texto);
    $texto = preg_replace('/[^0-9,\.\-]/', '', $texto);
    if ($texto === null || $texto === '') {
        return 0.0;
    }

    $negativo = false;
    if (strpos($texto, '-') === 0) {
        $negativo = true;
        $texto = substr($texto, 1);
    }

    if ($texto === '') {
        return 0.0;
    }

    $temVirgula = strpos($texto, ',') !== false;
    $temPonto = strpos($texto, '.') !== false;

    if ($temVirgula && $temPonto) {
        if (strrpos($texto, ',') > strrpos($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }
    } elseif ($temVirgula) {
        $partesVirgula = explode(',', $texto);
        if (count($partesVirgula) > 2) {
            $texto = implode('', $partesVirgula);
        } elseif (isset($partesVirgula[1]) && strlen($partesVirgula[1]) === 3) {
            $texto = $partesVirgula[0] . $partesVirgula[1];
        } else {
            $texto = $partesVirgula[0] . '.' . ($partesVirgula[1] ?? '');
        }
    } elseif ($temPonto) {
        $partesPonto = explode('.', $texto);
        if (count($partesPonto) > 2) {
            $texto = implode('', $partesPonto);
        } elseif (isset($partesPonto[1]) && strlen($partesPonto[1]) === 3) {
            $texto = $partesPonto[0] . $partesPonto[1];
        }
    }

    if ($negativo) {
        $texto = '-' . $texto;
    }

    if (!preg_match('/^-?\d+(\.\d+)?$/', $texto)) {
        return 0.0;
    }

    return (float)$texto;
}

$saldoAnteriorTexto = trim((string)($_GET['SaldoAnterior'] ?? ''));
$saldoAnterior = normalizarValorMonetarioResumoMovtos($saldoAnteriorTexto);
$saldoAnteriorAtivo = ($saldoAnteriorTexto !== '');
$eventoFiltro = trim((string)($_GET['EventoExtrato'] ?? ($_GET['Evento'] ?? '')));
$eventoFiltroSql = '%' . $eventoFiltro . '%';

$sql = "
    SELECT
        m.id,
        d.DataMes AS dataM,
        m.evento,
        m.grupo,
        m.DC,
        m.ValorE
    FROM movtos m
    INNER JOIN datas d
        ON CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) = CAST(IFNULL(m.diaCorreto, 0) AS UNSIGNED)
       AND CAST(d.M AS UNSIGNED) = CAST(m.M AS UNSIGNED)
       AND CAST(d.A AS UNSIGNED) = CAST(m.A AS UNSIGNED)
    WHERE m.Apelido = ?
      AND m.senha = ?
      AND IFNULL(m.diaCorreto, 0) > 0
      AND CAST(IFNULL(d.Util, 0) AS UNSIGNED) = 1
            AND (? = '' OR m.evento LIKE ?)
    ORDER BY d.DataMes ASC, m.id ASC
";

$stmt = mysqli_prepare($dbcon, $sql);
if (!$stmt) {
    mysqli_close($dbcon);
    exit('Falha ao preparar consulta: ' . mysqli_error($dbcon));
}

mysqli_stmt_bind_param($stmt, 'ssss', $apelido, $senha, $eventoFiltro, $eventoFiltroSql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$linhas = [];
$saldo  = $saldoAnterior;

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

$mensagemStatus = 'Sem filtro de evento. Saldo acumulado iniciado em R$ 0,00.';
if ($saldoAnteriorAtivo && $eventoFiltro !== '') {
    $mensagemStatus = 'Filtro de evento ativo e saldo acumulado iniciado em R$ ' . fmtVal($saldoAnterior) . '.';
} elseif ($saldoAnteriorAtivo) {
    $mensagemStatus = 'Saldo acumulado iniciado em R$ ' . fmtVal($saldoAnterior) . '.';
} elseif ($eventoFiltro !== '') {
    $mensagemStatus = 'Filtro de evento ativo. Saldo acumulado iniciado em R$ 0,00.';
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

        .filtros {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            padding: 14px;
            margin-bottom: 14px;
            display: flex;
            gap: 14px;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .filtros-bloco {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filtros-bloco--esquerda {
            justify-content: flex-start;
            flex: 1 1 320px;
        }

        .filtros-bloco--direita {
            justify-content: flex-end;
            flex: 1 1 320px;
        }

        .campo-grupo {
            min-width: 220px;
        }

        .filtros label {
            display: block;
            font-size: 12px;
            color: #2c5282;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .filtros input {
            width: 100%;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 13px;
        }

        .filtros button,
        .filtros a {
            height: 36px;
            border: none;
            border-radius: 6px;
            background: #2c5282;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .filtros a {
            background: #6c7f95;
        }

        .periodo-status-row {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .periodo-status {
            margin: 0;
            color: #2c5282;
            font-size: 12px;
        }

        .is-invalid {
            color: #d9534f !important;
        }

        .menu-shell__clear {
            border: none;
            border-radius: 6px;
            background: #6c7f95;
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
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

        <form class="filtros" method="get" action="ResumoMovtos.php">
            <div class="filtros-bloco filtros-bloco--esquerda">
                <div class="campo-grupo">
                    <label for="EventoExtrato">Evento (filtro do extrato)</label>
                    <input type="text" id="EventoExtrato" name="EventoExtrato" placeholder="Digite parte do nome do evento" autocomplete="off" value="<?= htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') ?>" />
                </div>
                <button type="submit" name="acao" value="filtrar">Aplicar filtro</button>
            </div>

            <div class="filtros-bloco filtros-bloco--direita">
                <div class="campo-grupo">
                    <label for="SaldoAnterior">AAAAAAAAAASaldo anterior do período (R$)</label>
                    <input type="text" id="SaldoAnterior" name="SaldoAnterior" placeholder="0,00" inputmode="decimal" autocomplete="off" value="<?= htmlspecialchars($saldoAnteriorTexto === '' ? '' : fmtVal($saldoAnterior), ENT_QUOTES, 'UTF-8') ?>" />
                </div>
                <button type="submit" name="acao" value="recalcular">Recalcular</button>
                <a href="ResumoMovtos.php">Limpar</a>
            </div>
        </form>

        <div class="periodo-status-row">
            <p id="periodoStatus" class="periodo-status" aria-live="polite"><?= htmlspecialchars($mensagemStatus, ENT_QUOTES, 'UTF-8') ?></p>
            <button type="button" id="clearLocalData" class="menu-shell__clear">Limpar texto e saldo digitados</button>
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
                    <?php if ($saldoAnteriorAtivo): ?>
                        <tr>
                            <td class="data">-</td>
                            <td class="desc">Saldo anterior aplicado</td>
                            <td class="grupo">-</td>
                            <td class="vazio">&mdash;</td>
                            <td class="vazio">&mdash;</td>
                            <td class="<?= $saldoAnterior >= 0 ? 'saldo-pos' : 'saldo-neg' ?>"><?= fmtVal($saldoAnterior) ?></td>
                        </tr>
                    <?php endif; ?>
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

    <script>
        (function() {
            var botaoLimpar = document.getElementById('clearLocalData');
            if (!botaoLimpar) {
                return;
            }

            botaoLimpar.addEventListener('click', function() {
                var saldoEl = document.getElementById('SaldoAnterior');
                var eventoEl = document.getElementById('EventoExtrato');
                var statusEl = document.getElementById('periodoStatus');

                if (saldoEl) {
                    saldoEl.value = '';
                }
                if (eventoEl) {
                    eventoEl.value = '';
                }
                if (statusEl) {
                    statusEl.textContent = 'Campos limpos nesta tela.';
                }
            });
        })();
    </script>
</body>

</html>