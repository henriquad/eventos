<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();

include_once "Conectar_BaseH.php";
require_once __DIR__ . '/extrato_rotina.php';

date_default_timezone_set("America/Sao_Paulo");

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

function extratoFormatoBr($iso)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$iso);
    return $dt ? $dt->format('d/m/Y') : (string)$iso;
}

function extratoFormatoDataComSemana($iso)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$iso);
    if (!$dt) {
        return (string)$iso;
    }

    $semana = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab');
    $indice = (int)$dt->format('w');

    return $dt->format('d/m/Y') . ' - ' . $semana[$indice];
}

function extratoValorFormatado($valor)
{
    return number_format((float)$valor, 2, ',', '.');
}

function extratoNormalizarValorMonetario($valor)
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

$dataInicial = extratoNormalizarData($_GET['DataI'] ?? '');
$dataFinal = extratoNormalizarData($_GET['DataF'] ?? '');
$saldoAnterior = extratoNormalizarValorMonetario($_GET['SaldoAnterior'] ?? '');
$exportarExcel = (($_GET['export'] ?? '') === 'excel');

if (!$dataInicial || !$dataFinal) {
    $hoje = new DateTime('today');
    $primeiroDia = new DateTime($hoje->format('Y-m-01'));
    $ultimoDia = new DateTime($hoje->format('Y-m-t'));
    $dataInicial = $primeiroDia->format('Y-m-d');
    $dataFinal = $ultimoDia->format('Y-m-d');
}

if ($dataInicial > $dataFinal) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

extratoPrepararMovtos($dbcon, $apelido, $senha, $dataInicial, $dataFinal);

$sqlLista = "SELECT m.id, d.DataMes AS dataM, m.evento, m.grupo, m.DC, m.ValorE
                         FROM movtos m
                         INNER JOIN datas d
                             ON CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) = CAST(IFNULL(m.diaCorreto, 0) AS UNSIGNED)
                            AND CAST(d.M AS UNSIGNED) = CAST(m.M AS UNSIGNED)
                            AND CAST(d.A AS UNSIGNED) = CAST(m.A AS UNSIGNED)
                         WHERE m.Apelido = ?
                             AND m.senha = ?
                             AND d.DataMes BETWEEN ? AND ?
                             AND IFNULL(m.diaCorreto, 0) > 0
                             AND CAST(IFNULL(d.Util, 0) AS UNSIGNED) = 1
                         ORDER BY d.DataMes ASC, m.id ASC";
$stmtLista = mysqli_prepare($dbcon, $sqlLista);
if (!$stmtLista) {
    extratoFalha($dbcon, 'Falha ao preparar SQL da lista: ' . mysqli_error($dbcon));
}

mysqli_stmt_bind_param($stmtLista, 'ssss', $apelido, $senha, $dataInicial, $dataFinal);
if (!mysqli_stmt_execute($stmtLista)) {
    $erroLista = mysqli_stmt_error($stmtLista);
    extratoFalha($dbcon, 'Falha ao executar SQL da lista: ' . $erroLista, $stmtLista);
}

$resultadoLista = mysqli_stmt_get_result($stmtLista);
$movimentos = array();
while ($resultadoLista && ($linha = mysqli_fetch_assoc($resultadoLista))) {
    $movimentos[] = $linha;
}
mysqli_stmt_close($stmtLista);

if ($exportarExcel) {
    $nomeArquivo = 'extrato_' . str_replace('-', '', $dataInicial) . '_a_' . str_replace('-', '', $dataFinal) . '.xls';

    mysqli_close($dbcon);

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    echo '<thead><tr><th>Data</th><th>Evento</th><th>Grupo</th><th>Débito (R$)</th><th>Crédito (R$)</th><th>Saldo Acumulado (R$)</th></tr></thead><tbody>';

    if (count($movimentos) === 0) {
        echo '<tr><td colspan="6">Nenhum movimento encontrado para o período selecionado.</td></tr>';
    } else {
        $saldoAcumulado = $saldoAnterior;
        foreach ($movimentos as $mov) {
            $dc = (string)$mov['DC'];
            $valor = (float)$mov['ValorE'];
            $valorComSinal = ($dc === 'D') ? ($valor * -1) : $valor;
            $saldoAcumulado += $valorComSinal;

            echo '<tr>';
            echo '<td>' . htmlspecialchars(extratoFormatoDataComSemana((string)$mov['dataM']), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)$mov['evento'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)$mov['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';

            if ($dc === 'D') {
                echo '<td>' . extratoValorFormatado($valor) . '</td>';
                echo '<td>-</td>';
            } else {
                echo '<td>-</td>';
                echo '<td>' . extratoValorFormatado($valor) . '</td>';
            }

            echo '<td>' . extratoValorFormatado($saldoAcumulado) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    exit;
}

$resumoGrafico = array();
foreach ($movimentos as $mov) {
    $dataChave = (string)$mov['dataM'];
    $dc = (string)$mov['DC'];
    $valor = (float)$mov['ValorE'];
    $valorComSinal = ($dc === 'D') ? ($valor * -1) : $valor;

    if (!isset($resumoGrafico[$dataChave])) {
        $resumoGrafico[$dataChave] = 0.0;
    }

    $resumoGrafico[$dataChave] += $valorComSinal;
}

$graficoLabels = array();
$graficoSaldos = array();
$saldoGraficoAcumulado = $saldoAnterior;
foreach ($resumoGrafico as $dataChave => $saldoDia) {
    $saldoGraficoAcumulado += (float)$saldoDia;
    $graficoLabels[] = extratoFormatoBr($dataChave);
    $graficoSaldos[] = round($saldoGraficoAcumulado, 2);
}

$graficoLabelsJson = json_encode($graficoLabels, JSON_UNESCAPED_UNICODE);
$graficoSaldosJson = json_encode($graficoSaldos);

$exportUrl = 'Extrato.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior) . '&export=excel';

mysqli_close($dbcon);

echo '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Extrato</title>';
echo '<style>
body{font-family:Arial,sans-serif;background:#f2f4f7;margin:0;padding:20px;color:#222}
.container{max-width:1100px;margin:0 auto}
h2{margin:0 0 12px 0}
.topo{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.topo a{background:#1f4f82;color:#fff;padding:7px 12px;text-decoration:none;border-radius:4px}
.filtro{background:#fff;border:1px solid #dbe2ea;border-radius:6px;padding:10px 12px;margin-bottom:12px}
.acoes-grafico{margin:0 0 12px 0}
.btn-grafico{background:#067647;color:#fff;border:none;border-radius:6px;padding:9px 14px;font-size:14px;cursor:pointer}
.btn-grafico:hover{background:#055d38}
.grafico{background:#fff;border:1px solid #dbe2ea;border-radius:6px;padding:12px;margin-bottom:12px}
.grafico.is-hidden{display:none}
.grafico h3{margin:0 0 10px 0;font-size:16px}
.grafico-wrap{position:relative;height:280px}
table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.08)}
th,td{border:1px solid #e5e9ef;padding:8px 10px;font-size:14px}
th{background:#1f4f82;color:#fff;text-align:center}
td.num{text-align:right;font-family:monospace}
td.deb{color:#b42318}
td.cre{color:#067647}
tr:nth-child(even){background:#f8fafc}
</style></head><body>';
echo '<div class="container">';
echo '<h2>Extrato - Lista de Movimentos</h2>';
echo '<div class="topo">';
echo '<a href="../menu.html">Menu</a>';
echo '<a href="ListaEventos.php">Lista de eventos</a>';
echo '<a href="' . htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') . '">Exportar Excel</a>';
echo '</div>';
echo '<div class="filtro">Período: <strong>' . htmlspecialchars(extratoFormatoBr($dataInicial), ENT_QUOTES, 'UTF-8') . '</strong> até <strong>' . htmlspecialchars(extratoFormatoBr($dataFinal), ENT_QUOTES, 'UTF-8') . '</strong></div>';
echo '<div class="filtro">Saldo anterior ao período: <strong>R$ ' . extratoValorFormatado($saldoAnterior) . '</strong></div>';
echo '<div class="acoes-grafico"><button type="button" id="toggleGraficoBtn" class="btn-grafico">Ocultar gráfico</button></div>';

echo '<div class="grafico" id="grafico">';
echo '<h3>Saldo Final Acumulado por Dia</h3>';
if (count($movimentos) === 0) {
    echo '<p>Sem dados para gerar gráfico no período selecionado.</p>';
} else {
    echo '<div class="grafico-wrap"><canvas id="graficoExtrato"></canvas></div>';
}
echo '</div>';

echo '<table>';
echo '<thead><tr><th>Data</th><th>Evento</th><th>Grupo</th><th>Débito (R$)</th><th>Crédito (R$)</th><th>Saldo Acumulado (R$)</th></tr></thead><tbody>';

if (count($movimentos) === 0) {
    echo '<tr><td colspan="6">Nenhum movimento encontrado para o período selecionado.</td></tr>';
} else {
    $saldoAcumulado = $saldoAnterior;
    foreach ($movimentos as $mov) {
        $dc = (string)$mov['DC'];
        $classe = ($dc === 'D') ? 'deb' : 'cre';
        $valor = (float)$mov['ValorE'];
        $valorComSinal = ($dc === 'D') ? ($valor * -1) : $valor;
        $saldoAcumulado += $valorComSinal;

        echo '<tr>';
        echo '<td>' . htmlspecialchars(extratoFormatoDataComSemana((string)$mov['dataM']), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$mov['evento'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$mov['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';

        if ($dc === 'D') {
            echo '<td class="num deb">' . extratoValorFormatado($valor) . '</td>';
            echo '<td class="num">-</td>';
        } else {
            echo '<td class="num">-</td>';
            echo '<td class="num cre">' . extratoValorFormatado($valor) . '</td>';
        }

        echo '<td class="num">' . extratoValorFormatado($saldoAcumulado) . '</td>';
        echo '</tr>';
    }
}

echo '</tbody></table>';
echo '<script>';
echo '(function(){';
echo 'var btn=document.getElementById("toggleGraficoBtn");';
echo 'var grafico=document.getElementById("grafico");';
echo 'if(!btn||!grafico){return;}';
echo 'btn.addEventListener("click",function(){';
echo 'var oculto=grafico.classList.toggle("is-hidden");';
echo 'btn.textContent=oculto?"Mostrar gráfico":"Ocultar gráfico";';
echo '});';
echo '})();';
echo '</script>';
if (count($movimentos) > 0) {
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>';
    echo 'const labels = ' . $graficoLabelsJson . ';';
    echo 'const dadosSaldo = ' . $graficoSaldosJson . ';';
    echo 'const ctx = document.getElementById("graficoExtrato");';
    echo 'if (ctx) {';
    echo 'new Chart(ctx, {';
    echo 'type: "bar",';
    echo 'data: {';
    echo 'labels: labels,';
    echo 'datasets: [';
    echo '{ label: "Saldo acumulado", data: dadosSaldo, backgroundColor: "rgba(31,79,130,0.75)", borderColor: "rgba(31,79,130,1)", borderWidth: 1 }';
    echo ']';
    echo '},';
    echo 'options: {';
    echo 'responsive: true,';
    echo 'maintainAspectRatio: false,';
    echo 'plugins: { legend: { position: "top" } },';
    echo 'scales: { y: { beginAtZero: true } }';
    echo '}';
    echo '});';
    echo '}';
    echo '</script>';
}
echo '</div></body></html>';
