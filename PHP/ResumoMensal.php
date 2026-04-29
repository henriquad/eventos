<?php
// Inicialização para evitar avisos de variável indefinida
$mensagemCache = $mensagemCache ?? '';
// Função utilitária para exibir datas no formato brasileiro (dd/mm/aaaa)
function resumoDataBr($data) {
    $data = trim((string)$data);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) return $m[3] . '/' . $m[2] . '/' . $m[1];
    return $data;
}

function resumoMesRotulo($mesAno) {
    $partes = explode('-', $mesAno);
    if (count($partes) !== 2) return $mesAno;
    $meses = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];
    return ($meses[$partes[1]] ?? $partes[1]) . '/' . $partes[0];
}

function resumoCsvCampo($valor) {
    $texto = (string)$valor;
    $texto = str_replace('"', '""', $texto);
    return '"' . $texto . '"';
}

function resumoCsvValor($valor) {
    return number_format((float)$valor, 2, ',', '');
}

function resumoValor($valor) {
    return number_format((float)$valor, 2, ',', '.');
}


// Função para normalizar valores monetários (copiada de ResumoMovtos.php)
if (!function_exists('normalizarValorMonetarioResumoMensal')) {
    function normalizarValorMonetarioResumoMensal($valor)
    {
        $texto = trim((string)$valor);
        if ($texto === '') return 0.0;
        $texto = preg_replace('/\s+/', '', $texto);
        if ($texto === null || $texto === '') return 0.0;
        $texto = preg_replace('/^R\$/i', '', $texto);
        $texto = preg_replace('/[^0-9,\.\-]/', '', $texto);
        if ($texto === null || $texto === '') return 0.0;
        $negativo = false;
        if (strpos($texto, '-') === 0) {
            $negativo = true;
            $texto = substr($texto, 1);
        }
        if ($texto === '') return 0.0;
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
            $texto = str_replace(',', '.', $texto);
        }
        if ($negativo) $texto = '-' . $texto;
        if (!preg_match('/^-?\d+(\.\d+)?$/', $texto)) return 0.0;
        return (float)$texto;
    }
}


// Inicialização de variáveis para evitar avisos
session_start();
$cacheValido = $cacheValido ?? false;
$dataInicial = $dataInicial ?? ($_GET['DataI'] ?? '');
$dataFinal = $dataFinal ?? ($_GET['DataF'] ?? '');
$eventoFiltro = $eventoFiltro ?? ($_GET['Evento'] ?? '');
$exportarExcel = $exportarExcel ?? (isset($_GET['export']) && $_GET['export'] === 'excel');
$saldoAnterior = normalizarValorMonetarioResumoMensal($_GET['SaldoAnterior'] ?? 0.0);

// Inicialização das variáveis principais do resumo
$grupos = array();
$totalPeriodoDebito = 0.0;
$totalPeriodoCredito = 0.0;
$totalPeriodoSaldoNet = 0.0; // Net saldo for the entire period
$saldoAcumuladoGeral = $saldoAnterior; // Overall accumulated balance
$movimentosCache = $movimentosCache ?? null;
if (!$cacheValido || !$movimentosCache) {
    // Buscar do banco de dados
    $apelido = $_SESSION['apelido'] ?? '';
    $senha = $_SESSION['senha'] ?? '';
    $movimentosCache = array();
    if ($apelido && $senha && $dataInicial && $dataFinal) {
        $dbcon = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
        if (!$dbcon->connect_error) {
            $sql = "SELECT d.DataMes AS dataM, m.grupo, m.DC, m.ValorE FROM movtos m INNER JOIN datas d ON CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) = CAST(IFNULL(m.diaCorreto, 0) AS UNSIGNED) AND CAST(d.M AS UNSIGNED) = CAST(m.M AS UNSIGNED) AND CAST(d.A AS UNSIGNED) = CAST(m.A AS UNSIGNED) WHERE m.Apelido = ? AND m.senha = ? AND d.DataMes BETWEEN ? AND ? AND IFNULL(m.diaCorreto, 0) > 0 AND CAST(IFNULL(d.Util, 0) AS UNSIGNED) = 1 ORDER BY d.DataMes ASC, m.id ASC";
            $stmt = $dbcon->prepare($sql);
            $stmt->bind_param('ssss', $apelido, $senha, $dataInicial, $dataFinal);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $movimentosCache[] = $row;
            }
            $stmt->close();
            $dbcon->close();
        }
    }
}

// Processar movimentos para construir o resumo
$saldoAcumuladoMensal = $saldoAnterior; // This will be the running accumulated balance for monthly totals
$mesesOrdenados = []; // To store months in order for accumulated saldo calculation

foreach ($movimentosCache as $mov) {
    $dataMes = substr((string)$mov['dataM'], 0, 7); // YYYY-MM
    $grupo = (string)$mov['grupo'];
    $dc = (string)$mov['DC'];
    $valor = (float)$mov['ValorE'];

    if (!isset($grupos[$dataMes])) {
        $grupos[$dataMes] = array(
            'debito' => 0.0,
            'credito' => 0.0,
            'saldo_net_mes' => 0.0, // Net saldo for the current month
            'saldo_acumulado_mes' => 0.0, // Accumulated saldo up to the end of this month
            'itens' => array()
        );
        $mesesOrdenados[] = $dataMes; // Keep track of month order
    }

    if (!isset($grupos[$dataMes]['itens'][$grupo])) {
        $grupos[$dataMes]['itens'][$grupo] = array(
            'grupo' => $grupo,
            'debito' => 0.0,
            'credito' => 0.0,
            'saldo_net_grupo' => 0.0 // Net saldo for the current group within the month
        );
    }

    if ($dc === 'D') {
        $grupos[$dataMes]['itens'][$grupo]['debito'] += $valor;
        $grupos[$dataMes]['itens'][$grupo]['saldo_net_grupo'] -= $valor;
        $grupos[$dataMes]['debito'] += $valor;
        $grupos[$dataMes]['saldo_net_mes'] -= $valor;
    } else {
        $grupos[$dataMes]['itens'][$grupo]['credito'] += $valor;
        $grupos[$dataMes]['itens'][$grupo]['saldo_net_grupo'] += $valor;
        $grupos[$dataMes]['credito'] += $valor;
        $grupos[$dataMes]['saldo_net_mes'] += $valor;
    }
}

// Calculate accumulated monthly saldo after all movements are processed
foreach ($mesesOrdenados as $mes) {
    $saldoAcumuladoMensal += $grupos[$mes]['saldo_net_mes'];
    $grupos[$mes]['saldo_acumulado_mes'] = $saldoAcumuladoMensal;
}

// Calculate total period debit, credit, and net saldo (not accumulated from saldoAnterior)
foreach ($grupos as $mes => $grupoMes) {
    $totalPeriodoDebito += $grupoMes['debito'];
    $totalPeriodoCredito += $grupoMes['credito'];
    $totalPeriodoSaldoNet += $grupoMes['saldo_net_mes'];
}

// The final accumulated balance for the period
$saldoAcumuladoGeral = $saldoAcumuladoMensal;


$extratoUrl = 'Extrato.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&usarCache=1';
$resumoBaseUrl = 'ResumoMensal.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior);
if ($eventoFiltro !== '') {
    $extratoUrl .= '&Evento=' . urlencode($eventoFiltro);
    $resumoBaseUrl .= '&Evento=' . urlencode($eventoFiltro);
}

if ($exportarExcel) {
    $nomeArquivo = 'resumo_mensal_' . str_replace('-', '', $dataInicial) . '_a_' . str_replace('-', '', $dataFinal) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo "Mes;Grupo;Debito total (R$);Credito total (R$);Saldo do grupo (R$)\r\n";

    if (count($grupos) === 0) {
        $mensagemCsv = $mensagemCache !== ''
            ? $mensagemCache
            : 'Nenhum valor encontrado no periodo selecionado.';
        echo resumoCsvCampo($mensagemCsv) . ";;;;\r\n";
    } else {
        foreach ($grupos as $mes => $grupoMes) {
            foreach ($grupoMes['itens'] as $item) {
                echo resumoCsvCampo(resumoMesRotulo($mes)) . ';'
                    . resumoCsvCampo($item['grupo']) . ';'
                    . resumoCsvCampo(resumoCsvValor($item['debito'])) . ';'
                    . resumoCsvCampo(resumoCsvValor($item['credito'])) . ';'
                    . resumoCsvCampo(resumoCsvValor($item['saldo_net_grupo'])) . "\r\n";
            }

            echo resumoCsvCampo(resumoMesRotulo($mes)) . ';'
                . resumoCsvCampo('Total do mes') . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['debito'])) . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['credito'])) . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['saldo_acumulado_mes'])) . "\r\n";
        }

        echo resumoCsvCampo('Total do periodo') . ';'
            . resumoCsvCampo('Geral') . ';'
            . resumoCsvCampo(resumoCsvValor($totalPeriodoDebito)) . ';'
            . resumoCsvCampo(resumoCsvValor($totalPeriodoCredito)) . ';'
            . resumoCsvCampo(resumoCsvValor($saldoAcumuladoGeral)) . "\r\n";
    }

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
</style>';
echo '<div class="container">';
echo '<h2>Resumo mensal dos valores diários</h2>';
echo '<div class="topo">';
echo '<a href="../menu.html">Menu</a>';
echo '<a href="' . htmlspecialchars($extratoUrl, ENT_QUOTES, 'UTF-8') . '">Extrato</a>';
echo '<a href="' . htmlspecialchars($resumoBaseUrl . '&export=excel', ENT_QUOTES, 'UTF-8') . '">Exportar Excel</a>';
echo '</div>';
echo '<div class="filtro">Período: <strong>' . htmlspecialchars(resumoDataBr($dataInicial), ENT_QUOTES, 'UTF-8') . '</strong> até <strong>' . htmlspecialchars(resumoDataBr($dataFinal), ENT_QUOTES, 'UTF-8') . '</strong></div>';
if ($mensagemCache !== '') {
    echo '<div class="filtro">' . htmlspecialchars($mensagemCache, ENT_QUOTES, 'UTF-8') . '</div>';
}

if (count($grupos) === 0) {
    echo '<table>';
    echo '<thead><tr><th>Mês</th><th>Grupo</th><th>Evento</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo (R$)</th></tr></thead>';
    echo '<tbody><tr><td colspan="6">Nenhum valor encontrado no período selecionado.</td></tr></tbody>';
    echo '</table>';
} else {
    foreach ($grupos as $mes => $grupoMes) {
        echo '<div class="bloco-mes">';
        echo '<h3>' . htmlspecialchars(resumoMesRotulo($mes), ENT_QUOTES, 'UTF-8') . '</h3>';
        echo '<table>';
        echo '<thead><tr><th>Grupo</th><th>Débito total (R$)</th><th>Crédito total (R$)</th><th>Saldo (R$)</th></tr></thead>';
        echo '<tbody>';
        foreach ($grupoMes['itens'] as $item) {
            $classeSaldo = $item['saldo_net_grupo'] >= 0 ? 'saldo-pos' : 'saldo-neg';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="num deb">' . resumoValor($item['debito']) . '</td>';
            echo '<td class="num cre">' . resumoValor($item['credito']) . '</td>';
            echo '<td class="num ' . $classeSaldo . '">' . resumoValor($item['saldo_net_grupo']) . '</td>';
            echo '</tr>';
        }
        // Linha de total do mês
        echo '<tr style="font-weight:bold;background:#eef4fa">';
        echo '<td>Total do mês</td>';
        echo '<td class="num deb">' . resumoValor($grupoMes['debito']) . '</td>';
        echo '<td class="num cre">' . resumoValor($grupoMes['credito']) . '</td>';
        echo '<td class="num ' . ($grupoMes['saldo_acumulado_mes'] >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($grupoMes['saldo_acumulado_mes']) . '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    // Linha de total do período
    echo '<div class="bloco-mes" style="margin-top:24px">';
    echo '<table>';
    echo '<thead><tr><th colspan="4">Total do período selecionado</th></tr></thead>';
    echo '<tbody>';
    echo '<tr style="font-weight:bold;background:#eef4fa">';
    echo '<td>Saldo acumulado final</td>';
    echo '<td class="num deb">' . resumoValor($totalPeriodoDebito) . '</td>'; // Net debit for period
    echo '<td class="num cre">' . resumoValor($totalPeriodoCredito) . '</td>'; // Net credit for period
    echo '<td class="num ' . ($saldoAcumuladoGeral >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($saldoAcumuladoGeral) . '</td>'; // Final accumulated saldo
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
