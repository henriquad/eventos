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

// Exibe aviso de logout, se aplicável
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    echo '<div style="background:#fff0e0;border:1.5px solid #e0a040;color:#a05a00;padding:14px 18px;margin:18px 0;border-radius:10px;font-size:1.15em;font-weight:bold;text-align:center;">Sessão encerrada com sucesso.</div>';
}
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
$diasUteisPorMes = array();

if (!$cacheValido || !$movimentosCache) {
    // Buscar do banco de dados
    $apelido = $_SESSION['apelido'] ?? '';
    $senha = $_SESSION['senha'] ?? '';
    $movimentosCache = array();
    if ($apelido && $senha && $dataInicial && $dataFinal) {
        $dbcon = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
        if (!$dbcon->connect_error) {
            // Consulta para contar dias úteis por mês no período
            $sqlDias = "SELECT SUBSTRING(DataMes, 1, 7) as mes, COUNT(*) as qtd 
                        FROM datas 
                        WHERE DataMes BETWEEN ? AND ? AND CAST(Util AS UNSIGNED) = 1 
                        GROUP BY SUBSTRING(DataMes, 1, 7)";
            $stmtDias = $dbcon->prepare($sqlDias);
            $stmtDias->bind_param('ss', $dataInicial, $dataFinal);
            $stmtDias->execute();
            $resDias = $stmtDias->get_result();
            while ($rowD = $resDias->fetch_assoc()) {
                $diasUteisPorMes[$rowD['mes']] = (int)$rowD['qtd'];
            }
            $stmtDias->close();

            $sql = "SELECT d.DataMes AS dataM, m.grupo, m.DC, m.ValorE 
                    FROM movtos m 
                    INNER JOIN datas d ON CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) = CAST(IFNULL(m.diaCorreto, 0) AS UNSIGNED) 
                        AND CAST(d.M AS UNSIGNED) = CAST(m.M AS UNSIGNED) 
                        AND CAST(d.A AS UNSIGNED) = CAST(m.A AS UNSIGNED) 
                    WHERE m.Apelido = ? AND m.senha = ? AND d.DataMes BETWEEN ? AND ? 
                        AND IFNULL(m.diaCorreto, 0) > 0 
                        AND CAST(IFNULL(d.Util, 0) AS UNSIGNED) = 1";
            
            if ($eventoFiltro !== '') {
                $sql .= " AND m.evento LIKE ?";
                $sql .= " ORDER BY d.DataMes ASC, m.id ASC";
                $stmt = $dbcon->prepare($sql);
                $eventoFiltroLike = '%' . $eventoFiltro . '%';
                $stmt->bind_param('sssss', $apelido, $senha, $dataInicial, $dataFinal, $eventoFiltroLike);
            } else {
                $sql .= " ORDER BY d.DataMes ASC, m.id ASC";
                $stmt = $dbcon->prepare($sql);
                $stmt->bind_param('ssss', $apelido, $senha, $dataInicial, $dataFinal);
            }

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
            'qtd_dias_uteis' => $diasUteisPorMes[$dataMes] ?? 0,
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
$limparEventoUrl = 'ResumoMensal.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior);

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
:root {
  --bg: #efe4d6;
  --bg-soft: #f9f4ed;
  --ink: #241b14;
  --muted: #6e5e4f;
  --brand: #1f4f7f;
  --brand-dark: #143551;
  --accent: #c87428;
  --panel: #fffdf9;
  --border: #d8cab8;
  --shadow: 0 20px 40px rgba(33, 23, 14, 0.17);
  --panel-soft: #f3e8d9;
}
.menu-shell {
  padding: 1.5rem 2.4rem;
  width: 100%;
  max-width: 980px;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

.menu-shell__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.2rem;  
}

.menu-shell__header p,
.periodo-card__intro p,
.ajuda-intro p {
  color: var(--muted);
  line-height: 1.65;
  font-size: 20px;
}

.menu-shell__logout {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.85rem 1.15rem;
  border-radius: 999px;
  background: var(--panel-soft);
  color: var(--brand-dark);
  text-decoration: none;
  font-size: 1rem;
  font-weight: 700;
  width: max-content;
}

.menu-shell__header-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.6rem;
}

.menu-shell__clear {
  border: 1px solid rgba(33, 76, 120, 0.28);
  border-radius: 999px;
  padding: 0.85rem 1.15rem;
  background: #fff8ef;
  color: var(--brand-dark);
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.menu-shell__clear:hover {
  background: #f7ebdb;
}

#menu-h {
  margin-bottom: 1.25rem;
}

#menu-h ul {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0;
  justify-content: center;
}

#menu-h li {
  display: block;
}

#menu-h ul li a {
  min-height: 38px;
  padding: 0.4rem 0.7rem 0.4rem 1.7rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 12px;
  background: linear-gradient(120deg, #fff 70%, #e0e7ff 100%);
  border: 1.2px solid var(--border);
  color: var(--brand-dark);
  text-decoration: none;
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.2;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.03);
  position: relative;
  transition:
    transform 0.18s cubic-bezier(0.4, 2, 0.3, 1),
    box-shadow 0.18s,
    border-color 0.18s;
  max-width: 180px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

#menu-h ul li a:hover {
  transform: translateY(-4px) scale(1.04);
  box-shadow: 0 8px 32px rgba(37, 99, 235, 0.13);
  border-color: var(--brand);
  background: linear-gradient(120deg, #e0e7ff 60%, #fff 100%);
  color: var(--brand);
}
body {
  font-family: Arial, sans-serif;
  background:
    radial-gradient(circle at 12% 14%, rgba(31, 79, 127, 0.2), transparent 40%),
    radial-gradient(
      circle at 90% 84%,
      rgba(200, 116, 40, 0.18),
      transparent 38%
    ),
    linear-gradient(160deg, var(--bg-soft) 0%, var(--bg) 48%, #e4d5c3 100%);
  color: var(--ink);
  min-height: 100vh;
  margin: 0;
  padding: 20px;
}
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
.filtro-form{background:#fff;border:1px solid #dbe2ea;border-radius:6px;padding:10px 12px;margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.filtro-form label{font-size:13px;color:#344054;display:block;margin-bottom:4px}
.filtro-form input{height:36px;padding:6px 10px;border:1px solid #cfd8e3;border-radius:6px;min-width:260px}
.filtro-form button,.filtro-form .btn-limpar{height:36px;padding:0 14px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:none;cursor:pointer}
.filtro-form button{background:#1f4f82;color:#fff}
.filtro-form .btn-limpar{background:#eef2f7;color:#1f4f82}
</style>';
echo '<div class="container">';

echo '<section class="menu-shell">
            
            <nav id="menu-h" aria-label="Menu principal">
                <ul>
                    <li><a href="../Incluir.html">(*) Incluir evento</a></li>
                    
                    <li><a href="ListaEventosResumo.php">Lista dos eventos</a></li>
                    <li><a href="tabFeriados.php">Consulta Feriados</a></li>
                    <li><a href="tabDatas.php">Consulta Datas</a></li>
                    <li><a href="logout.php" style="color:dimgray;" onclick="return confirm(\'Tem certeza que deseja trocar de usuário? Isso encerrará sua sessão atual.\');">Trocar usuário</a></li>
                </ul>
                <script src="../js/extrato-periodo-config.js?v=20260507"></script>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        var btn = document.getElementById("gerarExtratoBtn");
                        if (btn) {
                            btn.addEventListener("click", function (e) {
                                e.preventDefault();
                                var periodo = typeof window.eventosObterPeriodoPadraoExtrato === "function"
                                    ? window.eventosObterPeriodoPadraoExtrato(new Date())
                                    : (function () {
                                        var hoje = new Date();
                                        return {
                                            inicio: new Date(hoje.getFullYear(), hoje.getMonth(), 1),
                                            fim: new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0)
                                        };
                                    })();
                                function formatarData(dt) {
                                    var m = String(dt.getMonth() + 1).padStart(2, "0");
                                    var d = String(dt.getDate()).padStart(2, "0");
                                    return dt.getFullYear() + "-" + m + "-" + d;
                                }
                                var dataI = formatarData(periodo.inicio);
                                var dataF = formatarData(periodo.fim);
                                var url = "Extrato.php?DataI=" + encodeURIComponent(dataI) + "&DataF=" + encodeURIComponent(dataF);
                                window.location.href = url;
                            });
                        }
                    });
                </script>

            </nav>

            <br><br>


        </section>';


echo '<div class="topo">';
echo '<a href="' . htmlspecialchars($extratoUrl, ENT_QUOTES, "UTF-8") . '">Voltar p/ Extrato</a>';

echo '<a href="' . htmlspecialchars($resumoBaseUrl . '&export=excel', ENT_QUOTES, 'UTF-8') . '">Exportar...<img src="../images/excel.png" alt="Excel" style="width:20px;height:20px;vertical-align:middle;"> Excel</a>';
echo '</div>';
echo '<div class="filtro">Período: <strong>' . htmlspecialchars(resumoDataBr($dataInicial), ENT_QUOTES, 'UTF-8') . '</strong> até <strong>' . htmlspecialchars(resumoDataBr($dataFinal), ENT_QUOTES, 'UTF-8') . '</strong></div>';

echo '<form class="filtro-form" method="get" action="ResumoMensal.php">';
echo '<input type="hidden" name="DataI" value="' . htmlspecialchars($dataInicial, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="DataF" value="' . htmlspecialchars($dataFinal, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="SaldoAnterior" value="' . htmlspecialchars((string)$saldoAnterior, ENT_QUOTES, 'UTF-8') . '">';
echo '<div><label for="Evento">Filtrar por evento</label><input id="Evento" name="Evento" type="text" maxlength="100" placeholder="Digite parte do nome do evento" value="' . htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<button type="submit">Aplicar filtro</button>';
echo '<a class="btn-limpar" href="' . htmlspecialchars($limparEventoUrl, ENT_QUOTES, 'UTF-8') . '">Limpar filtro</a>';
echo '</form>';

if ($eventoFiltro !== '') {
    echo '<div class="filtro">Evento filtrado: <strong>' . htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') . '</strong></div>';
}

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
        $txtDias = ($grupoMes['qtd_dias_uteis'] ?? 0) > 0 ? ' (' . $grupoMes['qtd_dias_uteis'] . ' dias úteis)' : '';
        echo '<h3>' . htmlspecialchars(resumoMesRotulo($mes), ENT_QUOTES, 'UTF-8') . $txtDias . '</h3>';
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
