<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();

include_once 'Conectar_BaseH.php';
require_once __DIR__ . '/extrato_rotina.php';

date_default_timezone_set('America/Sao_Paulo');

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

function resumoFalha($dbcon, $mensagem, $stmt = null)
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

function resumoNormalizarData($valor)
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

function resumoDataBr($iso)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$iso);
    return $dt ? $dt->format('d/m/Y') : (string)$iso;
}

function resumoMesRotulo($chave)
{
    $dt = DateTime::createFromFormat('Y-m', (string)$chave);
    if (!$dt) {
        return (string)$chave;
    }

    $meses = array(
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Marco',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    );

    return $meses[(int)$dt->format('n')] . ' / ' . $dt->format('Y');
}

function resumoValor($valor)
{
    return number_format((float)$valor, 2, ',', '.');
}

function resumoNormalizarValorMonetario($valor)
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

$dataInicial = resumoNormalizarData($_GET['DataI'] ?? '');
$dataFinal = resumoNormalizarData($_GET['DataF'] ?? '');
$saldoAnterior = resumoNormalizarValorMonetario($_GET['SaldoAnterior'] ?? '');
$exportarExcel = (($_GET['export'] ?? '') === 'excel');

if (!$dataInicial || !$dataFinal) {
    $hoje = new DateTime('today');
    $dataInicial = (new DateTime($hoje->format('Y-m-01')))->format('Y-m-d');
    $dataFinal = (new DateTime($hoje->format('Y-m-t')))->format('Y-m-d');
}

if ($dataInicial > $dataFinal) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

extratoPrepararMovtos($dbcon, $apelido, $senha, $dataInicial, $dataFinal);

$sql = "SELECT
                        DATE_FORMAT(dataM, '%Y-%m') AS mesRef,
                        IFNULL(grupo, '') AS grupo,
                        SUM(CASE WHEN DC = 'D' THEN ValorE ELSE 0 END) AS debitoMes,
                        SUM(CASE WHEN DC = 'D' THEN 0 ELSE ValorE END) AS creditoMes,
                        SUM(CASE WHEN DC = 'D' THEN -ValorE ELSE ValorE END) AS saldoMes
                FROM movtos
                WHERE Apelido = ?
                    AND senha = ?
                    AND dataM BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(dataM, '%Y-%m'), IFNULL(grupo, '')
                ORDER BY DATE_FORMAT(dataM, '%Y-%m') ASC, IFNULL(grupo, '') ASC";

$stmt = mysqli_prepare($dbcon, $sql);
if (!$stmt) {
    resumoFalha($dbcon, 'Falha ao preparar resumo mensal: ' . mysqli_error($dbcon));
}

mysqli_stmt_bind_param($stmt, 'ssss', $apelido, $senha, $dataInicial, $dataFinal);
if (!mysqli_stmt_execute($stmt)) {
    $erro = mysqli_stmt_error($stmt);
    resumoFalha($dbcon, 'Falha ao executar resumo mensal: ' . $erro, $stmt);
}

$resultado = mysqli_stmt_get_result($stmt);
$grupos = array();
$totalPeriodoDebito = 0.0;
$totalPeriodoCredito = 0.0;
$totalPeriodoSaldo = $saldoAnterior;

while ($resultado && ($linha = mysqli_fetch_assoc($resultado))) {
    $mes = (string)$linha['mesRef'];
    $grupo = trim((string)$linha['grupo']);
    $debito = (float)$linha['debitoMes'];
    $credito = (float)$linha['creditoMes'];
    $saldoMes = (float)$linha['saldoMes'];

    if (!isset($grupos[$mes])) {
        $grupos[$mes] = array(
            'itens' => array(),
            'debito' => 0.0,
            'credito' => 0.0,
            'saldo' => 0.0,
        );
    }

    $grupos[$mes]['itens'][] = array(
        'grupo' => $grupo === '' ? 'Sem grupo' : $grupo,
        'debito' => $debito,
        'credito' => $credito,
        'saldo' => $saldoMes,
    );
    $grupos[$mes]['debito'] += $debito;
    $grupos[$mes]['credito'] += $credito;
    $grupos[$mes]['saldo'] += $saldoMes;

    $totalPeriodoDebito += $debito;
    $totalPeriodoCredito += $credito;
    $totalPeriodoSaldo += $saldoMes;
}

mysqli_stmt_close($stmt);
mysqli_close($dbcon);

if ($exportarExcel) {
    $nomeArquivo = 'resumo_mensal_' . str_replace('-', '', $dataInicial) . '_a_' . str_replace('-', '', $dataFinal) . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    echo '<thead><tr><th>Mês</th><th>Grupo</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo do grupo (R$)</th></tr></thead><tbody>';

    if (count($grupos) === 0) {
        echo '<tr><td colspan="5">Nenhum valor encontrado no período selecionado.</td></tr>';
    } else {
        foreach ($grupos as $mes => $grupoMes) {
            foreach ($grupoMes['itens'] as $item) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars(resumoMesRotulo($mes), ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($item['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . resumoValor($item['debito']) . '</td>';
                echo '<td>' . resumoValor($item['credito']) . '</td>';
                echo '<td>' . resumoValor($item['saldo']) . '</td>';
                echo '</tr>';
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars(resumoMesRotulo($mes), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><strong>Total do mês</strong></td>';
            echo '<td><strong>' . resumoValor($grupoMes['debito']) . '</strong></td>';
            echo '<td><strong>' . resumoValor($grupoMes['credito']) . '</strong></td>';
            echo '<td><strong>' . resumoValor($grupoMes['saldo']) . '</strong></td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<td><strong>Saldo anterior</strong></td>';
        echo '<td><strong>-</strong></td>';
        echo '<td><strong>-</strong></td>';
        echo '<td><strong>' . resumoValor($saldoAnterior) . '</strong></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td><strong>Total do período</strong></td>';
        echo '<td><strong>Geral</strong></td>';
        echo '<td><strong>' . resumoValor($totalPeriodoDebito) . '</strong></td>';
        echo '<td><strong>' . resumoValor($totalPeriodoCredito) . '</strong></td>';
        echo '<td><strong>' . resumoValor($totalPeriodoSaldo) . '</strong></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    exit;
}

echo '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Resumo mensal</title>';
echo '<style>
body{font-family:Arial,sans-serif;background:#f2f4f7;margin:0;padding:20px;color:#222}
.container{max-width:980px;margin:0 auto}
.topo{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.topo a{background:#1f4f82;color:#fff;padding:7px 12px;text-decoration:none;border-radius:4px}
h2{margin:0 0 12px 0}
.filtro{background:#fff;border:1px solid #dbe2ea;border-radius:6px;padding:10px 12px;margin-bottom:14px}
.bloco-mes{background:#fff;border:1px solid #dbe2ea;border-radius:8px;overflow:hidden;margin-bottom:16px}
.bloco-mes h3{margin:0;padding:12px 14px;background:#1f4f82;color:#fff;font-size:18px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dbe2ea;border-radius:8px;overflow:hidden}
th,td{border:1px solid #e5e9ef;padding:8px 10px;font-size:14px}
th{background:#1f4f82;text-align:center;color:#fff}
td.num{text-align:right;font-family:monospace}
td.deb{color:#b42318}
td.cre{color:#067647}
td.saldo-pos{color:#067647;font-weight:bold}
td.saldo-neg{color:#b42318;font-weight:bold}
tr:nth-child(even){background:#f8fafc}
tfoot td{font-weight:bold;background:#eef4fa}
</style></head><body>';
echo '<div class="container">';
echo '<h2>Resumo mensal dos valores diários</h2>';
echo '<div class="topo">';
echo '<a href="../menu.html">Menu</a>';
echo '<a href="Extrato.php?DataI=' . urlencode(resumoDataBr($dataInicial)) . '&DataF=' . urlencode(resumoDataBr($dataFinal)) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior) . '">Extrato</a>';
echo '<a href="ResumoMensal.php?DataI=' . urlencode(resumoDataBr($dataInicial)) . '&DataF=' . urlencode(resumoDataBr($dataFinal)) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior) . '&export=excel">Exportar Excel</a>';
echo '</div>';
echo '<div class="filtro">Período: <strong>' . htmlspecialchars(resumoDataBr($dataInicial), ENT_QUOTES, 'UTF-8') . '</strong> até <strong>' . htmlspecialchars(resumoDataBr($dataFinal), ENT_QUOTES, 'UTF-8') . '</strong></div>';
echo '<div class="filtro">Saldo anterior ao período: <strong>R$ ' . resumoValor($saldoAnterior) . '</strong></div>';

if (count($grupos) === 0) {
    echo '<table>';
    echo '<thead><tr><th>Mês</th><th>Grupo</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo do grupo (R$)</th></tr></thead>';
    echo '<tbody><tr><td colspan="5">Nenhum valor encontrado no período selecionado.</td></tr></tbody>';
    echo '</table>';
} else {
    foreach ($grupos as $mes => $grupoMes) {
        echo '<div class="bloco-mes">';
        echo '<h3>' . htmlspecialchars(resumoMesRotulo($mes), ENT_QUOTES, 'UTF-8') . '</h3>';
        echo '<table>';
        echo '<thead><tr><th>Grupo</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo do grupo (R$)</th></tr></thead>';
        echo '<tbody>';

        foreach ($grupoMes['itens'] as $item) {
            $classeSaldo = $item['saldo'] >= 0 ? 'saldo-pos' : 'saldo-neg';

            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="num deb">' . resumoValor($item['debito']) . '</td>';
            echo '<td class="num cre">' . resumoValor($item['credito']) . '</td>';
            echo '<td class="num ' . $classeSaldo . '">' . resumoValor($item['saldo']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '<tfoot><tr><td>Total do mês</td><td class="num deb">' . resumoValor($grupoMes['debito']) . '</td><td class="num cre">' . resumoValor($grupoMes['credito']) . '</td><td class="num ' . ($grupoMes['saldo'] >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($grupoMes['saldo']) . '</td></tr></tfoot>';
        echo '</table>';
        echo '</div>';
    }

    echo '<table>';
    echo '<thead><tr><th>Total do período</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo total (R$)</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>Saldo anterior</td><td class="num">-</td><td class="num">-</td><td class="num ' . ($saldoAnterior >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($saldoAnterior) . '</td></tr>';
    echo '<tr><td>Geral</td><td class="num deb">' . resumoValor($totalPeriodoDebito) . '</td><td class="num cre">' . resumoValor($totalPeriodoCredito) . '</td><td class="num ' . ($totalPeriodoSaldo >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($totalPeriodoSaldo) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
}

echo '</div></body></html>';
