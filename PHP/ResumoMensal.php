<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();

date_default_timezone_set('America/Sao_Paulo');

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

function resumoCsvValor($valor)
{
    return number_format((float)$valor, 2, ',', '');
}

function resumoCsvCampo($valor)
{
    $texto = (string)$valor;
    $texto = str_replace('"', '""', $texto);
    return '"' . $texto . '"';
}

$dataInicial = resumoNormalizarData($_GET['DataI'] ?? '');
$dataFinal = resumoNormalizarData($_GET['DataFim'] ?? '');
$eventoFiltro = trim((string)($_GET['Evento'] ?? ''));
$eventoFiltro = substr($eventoFiltro, 0, 100);
$exportarExcel = (($_GET['export'] ?? '') === 'excel');


if (!$dataInicial || !$dataFinal) {
    $hoje = new DateTime('today');
    $dataFinal = $hoje->format('Y-m-d');
    $dataInicial = (clone $hoje)->modify('-6 months')->format('Y-m-d');
}

if ($dataInicial > $dataFinal) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}
$cacheExtrato = isset($_SESSION['extrato_cache']) && is_array($_SESSION['extrato_cache'])
    ? $_SESSION['extrato_cache']
    : null;

$movimentosCache = array();
$mensagemCache = '';
$cacheValido = false;

if ($cacheExtrato) {
    $cacheApelido = (string)($cacheExtrato['apelido'] ?? '');
    $cacheSenha = (string)($cacheExtrato['senha'] ?? '');
    $cacheDataInicial = (string)($cacheExtrato['dataInicial'] ?? '');
    $cacheDataFinal = (string)($cacheExtrato['dataFinal'] ?? '');
    $cacheEventoFiltro = (string)($cacheExtrato['eventoFiltro'] ?? '');

    $cacheValido = $cacheApelido === eventosApelidoSessao()
        && $cacheSenha === eventosSenhaSessao()
        && $cacheDataInicial === $dataInicial
        && $cacheDataFinal === $dataFinal
        && $cacheEventoFiltro === $eventoFiltro;

    if ($cacheValido) {
        $movimentosCache = isset($cacheExtrato['movimentos']) && is_array($cacheExtrato['movimentos'])
            ? $cacheExtrato['movimentos']
            : array();
    } else {
        $mensagemCache = 'Para gerar o resumo sem SQL, abra o extrato novamente com o mesmo período e filtro.';
    }
} else {
    $mensagemCache = 'Resumo depende dos dados em memória da tela de extrato. Gere o extrato primeiro.';
}

$grupos = array();
$totalPeriodoDebito = 0.0;
$totalPeriodoCredito = 0.0;
$totalPeriodoSaldo = 0.0;

if ($cacheValido) {
    foreach ($movimentosCache as $mov) {
        $dataMovimento = (string)($mov['dataM'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d', $dataMovimento);
        if (!$dt) {
            continue;
        }

        $mes = $dt->format('Y-m');
        $grupoNome = trim((string)($mov['grupo'] ?? ''));
        $dc = (string)($mov['DC'] ?? 'C');
        $valor = (float)($mov['ValorE'] ?? 0.0);

        $debito = ($dc === 'D') ? $valor : 0.0;
        $credito = ($dc === 'D') ? 0.0 : $valor;
        $saldoMes = ($dc === 'D') ? ($valor * -1) : $valor;
        $grupoChave = $grupoNome === '' ? 'Sem grupo' : $grupoNome;

        if (!isset($grupos[$mes])) {
            $grupos[$mes] = array(
                'itens' => array(),
                'debito' => 0.0,
                'credito' => 0.0,
                'saldo' => 0.0,
            );
        }

        if (!isset($grupos[$mes]['itens'][$grupoChave])) {
            $grupos[$mes]['itens'][$grupoChave] = array(
                'grupo' => $grupoChave,
                'debito' => 0.0,
                'credito' => 0.0,
                'saldo' => 0.0,
            );
        }

        $grupos[$mes]['itens'][$grupoChave]['debito'] += $debito;
        $grupos[$mes]['itens'][$grupoChave]['credito'] += $credito;
        $grupos[$mes]['itens'][$grupoChave]['saldo'] += $saldoMes;

        $grupos[$mes]['debito'] += $debito;
        $grupos[$mes]['credito'] += $credito;
        $grupos[$mes]['saldo'] += $saldoMes;

        $totalPeriodoDebito += $debito;
        $totalPeriodoCredito += $credito;
        $totalPeriodoSaldo += $saldoMes;
    }

    foreach ($grupos as $mesChave => $grupoMes) {
        ksort($grupos[$mesChave]['itens'], SORT_NATURAL | SORT_FLAG_CASE);
        $grupos[$mesChave]['itens'] = array_values($grupos[$mesChave]['itens']);
    }
}

$extratoUrl = 'Extrato.php?DataI=' . urlencode($dataInicial) . '&DataFim=' . urlencode($dataFinal) . '&usarCache=1';
$resumoBaseUrl = 'ResumoMensal.php?DataI=' . urlencode($dataInicial) . '&DataFim=' . urlencode($dataFinal);
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
                    . resumoCsvCampo(resumoCsvValor($item['saldo'])) . "\r\n";
            }

            echo resumoCsvCampo(resumoMesRotulo($mes)) . ';'
                . resumoCsvCampo('Total do mes') . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['debito'])) . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['credito'])) . ';'
                . resumoCsvCampo(resumoCsvValor($grupoMes['saldo'])) . "\r\n";
        }

        echo resumoCsvCampo('Total do periodo') . ';'
            . resumoCsvCampo('Geral') . ';'
            . resumoCsvCampo(resumoCsvValor($totalPeriodoDebito)) . ';'
            . resumoCsvCampo(resumoCsvValor($totalPeriodoCredito)) . ';'
            . resumoCsvCampo(resumoCsvValor($totalPeriodoSaldo)) . "\r\n";
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
.grafico-resumo-wrap{
    width:100vw;
    left:50%;
    right:50%;
    margin-left:-50vw;
    margin-right:-50vw;
    margin-bottom:24px;
    background:#fff;
    border-radius:8px;
    padding:12px 0 8px 0;
    box-shadow:0 1px 4px rgba(0,0,0,.08)
}
#graficoResumo{
    width:100vw!important;
    min-width:320px;
    max-width:100vw;
    height:340px!important;
    display:block;
    margin:0 auto;
}
</style>';
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
// Preparar dados do gráfico
$graficoLabels = array();
$graficoSaldos = array();
foreach ($grupos as $mes => $grupoMes) {
    $graficoLabels[] = resumoMesRotulo($mes);
    $graficoSaldos[] = round($grupoMes['saldo'], 2);
}
$graficoLabelsJson = json_encode($graficoLabels, JSON_UNESCAPED_UNICODE);
$graficoSaldosJson = json_encode($graficoSaldos);
echo '<div class="container">';
// Gráfico de barras do saldo mensal
if (count($graficoLabels) > 0) {
    echo '<div class="grafico-resumo-wrap">';
    echo '<canvas id="graficoResumo"></canvas>';
    echo '</div>';
    echo '<script>
    const ctxResumo = document.getElementById("graficoResumo").getContext("2d");
    new Chart(ctxResumo, {
        type: "bar",
        data: {
            labels: ' . $graficoLabelsJson . ',
            datasets: [{
                label: "Saldo do mês (R$)",
                data: ' . $graficoSaldosJson . ',
                backgroundColor: "#1f4f82",
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: true, text: "Saldo total por mês" }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => v.toLocaleString("pt-BR", {minimumFractionDigits:2}) }
                }
            }
        }
    });
    </script>';
}
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
    echo '<tr><td>Geral</td><td class="num deb">' . resumoValor($totalPeriodoDebito) . '</td><td class="num cre">' . resumoValor($totalPeriodoCredito) . '</td><td class="num ' . ($totalPeriodoSaldo >= 0 ? 'saldo-pos' : 'saldo-neg') . '">' . resumoValor($totalPeriodoSaldo) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
}

echo '</div></body></html>';
