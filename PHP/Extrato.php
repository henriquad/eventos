<?php
session_start();


// Exibe aviso de logout, se aplicável
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    echo '<div style="background:#fff0e0;border:1.5px solid #e0a040;color:#a05a00;padding:14px 18px;margin:18px 0;border-radius:10px;font-size:1.15em;font-weight:bold;text-align:center;">Sessão encerrada com sucesso.</div>';
}

$periodoPadraoExtratoTipo = 'mes-atual';

function extratoPeriodoPadraoDatas($tipo)
{
    $tipoNormalizado = strtolower(trim((string)$tipo));
    if ($tipoNormalizado === 'ano-atual') {
        return [
            'inicio' => date('Y-01-01'),
            'fim' => date('Y-12-31'),
        ];
    }

    if ($tipoNormalizado === 'proximos-30') {
        return [
            'inicio' => date('Y-m-d'),
            'fim' => date('Y-m-d', strtotime('+29 days')),
        ];
    }

    return [
        'inicio' => date('Y-m-01'),
        'fim' => date('Y-m-t'),
    ];
}

$periodoPadraoExtrato = extratoPeriodoPadraoDatas($periodoPadraoExtratoTipo);

// --- GARANTIR MOVTOS PARA O PERÍODO SELECIONADO ---
$apelidoMov = $_SESSION['apelido'] ?? '';
$senhaMov = $_SESSION['senha'] ?? '';
$periodoI = isset($_GET['DataI']) ? extratoNormalizarData($_GET['DataI']) : $periodoPadraoExtrato['inicio'];
$periodoF = isset($_GET['DataF']) ? extratoNormalizarData($_GET['DataF']) : $periodoPadraoExtrato['fim'];
if ($apelidoMov && $senhaMov) {
    $dbconMov = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
    if ($dbconMov && !$dbconMov->connect_error) {
        // Só executa se não houver nenhum movto para o período selecionado
        $sqlExiste = "SELECT COUNT(*) as total FROM movtos WHERE Apelido = ? AND senha = ? AND diaCorreto > 0 AND EXISTS (SELECT 1 FROM datas d WHERE d.DataMes BETWEEN ? AND ? AND d.DiaUtil = movtos.diaCorreto AND d.M = movtos.M AND d.A = movtos.A)";
        $stmtExiste = $dbconMov->prepare($sqlExiste);
        $stmtExiste->bind_param('ssss', $apelidoMov, $senhaMov, $periodoI, $periodoF);
        $stmtExiste->execute();
        $resExiste = $stmtExiste->get_result();
        $totalMovtos = $resExiste->fetch_assoc()['total'] ?? 0;
        $stmtExiste->close();
        if ($totalMovtos == 0) {
            // Busca datas já existentes para o usuário nesse período
            $sqlDatas = "SELECT d.DataMes, d.DiaUtil, d.M, d.A FROM datas d WHERE d.DataMes BETWEEN ? AND ? AND d.Util = 1 ORDER BY d.DataMes ASC";
            $stmtDatas = $dbconMov->prepare($sqlDatas);
            $stmtDatas->bind_param('ss', $periodoI, $periodoF);
            $stmtDatas->execute();
            $resultDatas = $stmtDatas->get_result();
            while ($row = $resultDatas->fetch_assoc()) {
                // Verifica se já existe movto para essa data
                $sqlCheck = "SELECT COUNT(*) as total FROM movtos WHERE Apelido = ? AND senha = ? AND diaCorreto = ? AND M = ? AND A = ?";
                $stmtCheck = $dbconMov->prepare($sqlCheck);
                $stmtCheck->bind_param('ssiii', $apelidoMov, $senhaMov, $row['DiaUtil'], $row['M'], $row['A']);
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                $existe = $resCheck->fetch_assoc()['total'] ?? 0;
                $stmtCheck->close();
                if ($existe == 0) {
                    // Insere movto "placeholder" para a data
                    $sqlIns = "INSERT INTO movtos (Apelido, senha, diaCorreto, M, A, evento, grupo, DC, ValorE) VALUES (?, ?, ?, ?, ?, 'PREVISTO', 'Automático', 'C', 0.00)";
                    $stmtIns = $dbconMov->prepare($sqlIns);
                    $stmtIns->bind_param('ssiii', $apelidoMov, $senhaMov, $row['DiaUtil'], $row['M'], $row['A']);
                    $stmtIns->execute();
                    $stmtIns->close();
                }
            }
            $stmtDatas->close();
        }
        $dbconMov->close();
    }
}


// Funções utilitárias
function extratoNormalizarData($data)
{
    $data = trim((string)$data);
    if ($data === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return $data;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    $dt = DateTime::createFromFormat('d/m/Y', $data);
    if ($dt) return $dt->format('Y-m-d');
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if ($dt) return $dt->format('Y-m-d');
    return $data;
}
function extratoFormatoBr($data)
{
    $data = trim((string)$data);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) return $m[3] . '/' . $m[2] . '/' . $m[1];
    return $data;
}
function extratoFormatoDataComSemana($iso)
{
    $dt = DateTime::createFromFormat('Y-m-d', (string)$iso);
    if (!$dt) return (string)$iso;
    $semana = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab');
    $indice = (int)$dt->format('w');
    return $dt->format('d/m/Y') . ' - ' . $semana[$indice];
}
function extratoValorFormatado($valor)
{
    return number_format((float)$valor, 2, ',', '.');
}
function extratoCsvValor($valor)
{
    return number_format((float)$valor, 2, ',', '');
}
function extratoCsvCampo($valor)
{
    $texto = (string)$valor;
    $texto = str_replace('"', '""', $texto);
    return '"' . $texto . '"';
}
function extratoNormalizarValorMonetario($valor)
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
    if ($negativo) $texto = '-' . $texto;
    if (!preg_match('/^-?\d+(\.\d+)?$/', $texto)) return 0.0;
    return (float)$texto;
}

// Variáveis de sessão e conexão
$apelido = $_SESSION['apelido'] ?? '';
$senha = $_SESSION['senha'] ?? '';
$dbcon = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
if (!$dbcon) {
    die('Erro ao conectar ao banco de dados: ' . mysqli_connect_error());
}






// Define datas padrão com base no tipo configurado se não vierem por GET
if (isset($_GET['DataI']) && isset($_GET['DataF'])) {
    $dataInicial = extratoNormalizarData($_GET['DataI']);
    $dataFinal = extratoNormalizarData($_GET['DataF']);
} else {
    $dataInicial = $periodoPadraoExtrato['inicio'];
    $dataFinal = $periodoPadraoExtrato['fim'];
}
$saldoAnteriorInformado = array_key_exists('SaldoAnterior', $_GET);
$saldoAnterior = extratoNormalizarValorMonetario($_GET['SaldoAnterior'] ?? '');
$eventoFiltro = trim((string)($_GET['Evento'] ?? ''));
$eventoFiltro = substr($eventoFiltro, 0, 100);
$exportarExcel = (($_GET['export'] ?? '') === 'excel');
$usarCache = (($_GET['usarCache'] ?? '') === '1');


if (!$dataInicial || !$dataFinal) {
    die('<div style="color:red;font-weight:bold;margin:20px 0;">Por favor, informe a Data Inicial e a Data Final para calcular o extrato.</div>');
}

if ($dataInicial > $dataFinal) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}


// Monta a tabela movtos para o período de 1 ano à frente antes de carregar a página
if ($apelido && $senha) {
    require_once __DIR__ . '/extrato_rotina.php';
    extratoPrepararMovtos($dbcon, $apelido, $senha, $dataInicial, $dataFinal);
}

$movimentos = array();
$cacheExtrato = isset($_SESSION['extrato_cache']) && is_array($_SESSION['extrato_cache'])
    ? $_SESSION['extrato_cache']
    : null;
$cacheValido = false;

if ($usarCache && $cacheExtrato) {
    $cacheValido = (string)($cacheExtrato['apelido'] ?? '') === $apelido
        && (string)($cacheExtrato['senha'] ?? '') === $senha
        && (string)($cacheExtrato['dataInicial'] ?? '') === $dataInicial
        && (string)($cacheExtrato['dataFinal'] ?? '') === $dataFinal
        && (string)($cacheExtrato['eventoFiltro'] ?? '') === $eventoFiltro
        && isset($cacheExtrato['movimentos'])
        && is_array($cacheExtrato['movimentos']);

    if ($cacheValido) {
        $movimentos = $cacheExtrato['movimentos'];
        if (!$saldoAnteriorInformado) {
            $saldoAnterior = (float)($cacheExtrato['saldoAnterior'] ?? 0.0);
        }
    }
}


if (!$cacheValido) {
    // extratoPrepararMovtos($dbcon, $apelido, $senha, $dataInicial, $dataFinal);

    $sqlLista = "SELECT m.id, d.DataMes AS dataM, CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) AS diaUtil, m.evento, m.grupo, m.DC, m.ValorE
        FROM movtos m
        INNER JOIN datas d ON CAST(IFNULL(d.DiaUtil, 0) AS UNSIGNED) = CAST(IFNULL(m.diaCorreto, 0) AS UNSIGNED)
        AND CAST(d.M AS UNSIGNED) = CAST(m.M AS UNSIGNED)
        AND CAST(d.A AS UNSIGNED) = CAST(m.A AS UNSIGNED)
        WHERE m.Apelido = ?
        AND m.senha = ?
        AND d.DataMes BETWEEN ? AND ?
        AND IFNULL(m.diaCorreto, 0) > 0
        AND CAST(IFNULL(d.Util, 0) AS UNSIGNED) = 1";

    $eventoFiltroLike = '';
    if ($eventoFiltro !== '') {
        $sqlLista .= " AND m.evento LIKE ?";
        $eventoFiltroLike = '%' . $eventoFiltro . '%';
    }

    $sqlLista .= " ORDER BY d.DataMes ASC, m.id ASC";
    $stmtLista = mysqli_prepare($dbcon, $sqlLista);
    if (!$stmtLista) {
        extratoFalha($dbcon, 'Falha ao preparar SQL da lista: ' . mysqli_error($dbcon));
    }

    if ($eventoFiltro !== '') {
        mysqli_stmt_bind_param($stmtLista, 'sssss', $apelido, $senha, $dataInicial, $dataFinal, $eventoFiltroLike);
    } else {
        mysqli_stmt_bind_param($stmtLista, 'ssss', $apelido, $senha, $dataInicial, $dataFinal);
    }
    if (!mysqli_stmt_execute($stmtLista)) {
        $erroLista = mysqli_stmt_error($stmtLista);
        extratoFalha($dbcon, 'Falha ao executar SQL da lista: ' . $erroLista, $stmtLista);
    }

    $resultadoLista = mysqli_stmt_get_result($stmtLista);
    while ($resultadoLista && ($linha = mysqli_fetch_assoc($resultadoLista))) {
        $movimentos[] = $linha;
    }
    mysqli_stmt_close($stmtLista);

    $_SESSION['extrato_cache'] = array(
        'apelido' => $apelido,
        'senha' => $senha,
        'dataInicial' => $dataInicial,
        'dataFinal' => $dataFinal,
        'saldoAnterior' => $saldoAnterior,
        'eventoFiltro' => $eventoFiltro,
        'geradoEm' => date('c'),
        'movimentos' => $movimentos,
    );
}

if ($exportarExcel) {
    $nomeArquivo = 'extrato_' . str_replace('-', '', $dataInicial) . '_a_' . str_replace('-', '', $dataFinal) . '.csv';

    if ($dbcon instanceof mysqli) {
        $dbcon->close();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo "Data;DiaUtil;Evento;Grupo;Debito (R$);Credito (R$);Saldo Acumulado (R$)\r\n";

    if (count($movimentos) === 0) {
        echo extratoCsvCampo('Nenhum movimento encontrado para o periodo selecionado.') . ";;;;;;\r\n";
    } else {
        $saldoAcumulado = $saldoAnterior;
        foreach ($movimentos as $mov) {
            $dc = (string)$mov['DC'];
            $valor = (float)$mov['ValorE'];
            $valorComSinal = ($dc === 'D') ? ($valor * -1) : $valor;
            $saldoAcumulado += $valorComSinal;

            $debito = ($dc === 'D') ? extratoCsvValor($valor) : '-';
            $credito = ($dc === 'D') ? '-' : extratoCsvValor($valor);

            echo extratoCsvCampo(extratoFormatoDataComSemana((string)$mov['dataM'])) . ';'
                . extratoCsvCampo((int)$mov['diaUtil']) . ';'
                . extratoCsvCampo((string)$mov['evento']) . ';'
                . extratoCsvCampo((string)$mov['grupo']) . ';'
                . extratoCsvCampo($debito) . ';'
                . extratoCsvCampo($credito) . ';'
                . extratoCsvCampo(extratoCsvValor($saldoAcumulado)) . "\r\n";
        }
    }

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

$baseExtratoUrl = 'Extrato.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior);
$resumoMensalUrl = 'ResumoMensal.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior);

if ($eventoFiltro !== '') {
    $baseExtratoUrl .= '&Evento=' . urlencode($eventoFiltro);
    $resumoMensalUrl .= '&Evento=' . urlencode($eventoFiltro);
}

$exportUrl = $baseExtratoUrl . '&export=excel';
$limparEventoUrl = 'Extrato.php?DataI=' . urlencode($dataInicial) . '&DataF=' . urlencode($dataFinal) . '&SaldoAnterior=' . urlencode((string)$saldoAnterior);

mysqli_close($dbcon);

echo '
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Extrato</title>';
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
        .container {
            max-width: 1100px;
            margin: 0 auto
        }

        h2 {
            margin: 0 0 12px 0
        }

        .topo {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 12px
        }

        .topo a {
            background: #1f4f82;
            color: #fff;
            padding: 7px 12px;
            text-decoration: none;
            border-radius: 4px
        }

        .filtro {
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px
        }

        .filtro-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: end
        }

        .filtro-form label {
            font-size: 13px;
            color: #344054;
            display: block;
            margin-bottom: 4px
        }

        .filtro-form input {
            height: 36px;
            padding: 6px 10px;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            min-width: 260px
        }

        .filtro-form .filtro-acao {
            height: 36px;
            min-width: 140px;
            padding: 0 14px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box
        }

        .filtro-form button {
            border: none;
            background: #1f4f82;
            color: #fff;
            cursor: pointer
        }

        .filtro-form a {
            background: #eef2f7;
            color: #1f4f82;
            text-decoration: none
        }

        .acoes-grafico {
            margin: 0 0 12px 0
        }

        .btn-grafico {
            background: #067647;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 9px 14px;
            font-size: 14px;
            cursor: pointer
        }

        .btn-grafico:hover {
            background: #055d38
        }
    
        #grafico {
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            margin-bottom: 12px;
            }

        .grafico.is-hidden {
            display: none;
        }

        .grafico h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }

        .grafico-wrap {
        minBarLength: 2,
            position: relative;
            width: 1100px;
            left: 5px;
            right: 5px;
            margin-left: 5px;
            margin-right: 5px;
            height: 480px;
            min-height: 320px;
            max-height: 600px;
            max-width: 1200vw;
            background: transparent;
            border-radius: 8px;
            padding: 2px 0 8px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08)
        }

        th,
        td {
        
            border: 1px solid #e5e9ef;
            padding: 8px 10px;
            font-size: 14px
        }

        th {
            background: #1f4f82;
            color: #fff;
            text-align: center
        }

        td.num {
            text-align: right;
            font-family: monospace
        }

        td.deb {
            color: #b42318
        }

        td.cre {
            color: #067647
        }

        tr:nth-child(even) {
            background: #f8fafc
        }
            .menu-shell {
  padding: 1.5rem 2.4rem;
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
  width: 100%;
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
.periodo-rapido-btn {
    background: linear-gradient(90deg, #1f4f82 60%, #3a7bd5 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 0 18px;
    height: 36px;
    min-width: 140px;
    margin-left: 5px;
    font-size: 15px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(31, 79, 130, 0.08);
    transition: background 0.2s, transform 0.1s;
    cursor: pointer;
}
.periodo-rapido-btn:hover {
    background: linear-gradient(90deg, #3a7bd5 60%, #1f4f82 100%);
    transform: translateY(-2px) scale(1.04);
}
</style>';

echo '</head>
<body>';

echo '<div class="container">';
echo '<section class="menu-shell">
            
            <nav id="menu-h" aria-label="Menu principal">
                <ul>
                    <li><a href="../Incluir.html">(*) Incluir evento</a></li>
                    <li><a href="ListaEventos.php">Lista de eventos</a></li>                    
                    <li><a href="tabFeriados.php">Consulta Feriados</a></li>
                    <li><a href="tabDatas.php">Consulta Datas</a></li>                    
                        <li><a href="logout.php" style="color:dimgray;"
                            onclick="return confirm(\'Tem certeza que deseja trocar de usuário? Isso encerrará sua sessão atual.\');">Trocar usuário</a></li>
                    
                    
                    
                    
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



echo '<div class="topo">
<a href="' . htmlspecialchars($resumoMensalUrl, ENT_QUOTES, 'UTF-8') . '">Gerar planilha de resumo mensal por grupo</a>

<a href="' . htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') . '">Exportar...<img src="../images/excel.png" alt="Excel" style="width:20px;height:20px;vertical-align:middle;"> Excel</a>';

// Botões de período rápido
$hoje = date('Y-m-d');
$mesAtualInicio = date('Y-m-01');
$mesAtualFim = date('Y-m-t');
$anoAtualInicio = date('Y-01-01');
$anoAtualFim = date('Y-12-31');
$proximos30Fim = date('Y-m-d', strtotime('+30 days'));



// Campos de data manual + botões de período rápido

// Corrige datas padrão com base no tipo configurado ao abrir o formulário
if (isset($_GET['DataI']) && isset($_GET['DataF'])) {
    $dataInicialPadrao = extratoNormalizarData($_GET['DataI']);
    $dataFinalPadrao = extratoNormalizarData($_GET['DataF']);
} else {
    $dataInicialPadrao = $periodoPadraoExtrato['inicio'];
    $dataFinalPadrao = $periodoPadraoExtrato['fim'];
}
echo '<form class="filtro filtro-form" method="get" action="Extrato.php" style="margin-bottom:12px;" onsubmit="return prepararDatasFiltro(this)">';
echo '<div style="display:flex;gap:8px;align-items:end;">';
echo '<div><label for="DataI">Data inicial</label><input type="text" id="DataI" name="DataI" placeholder="dd/mm/aaaa" inputmode="numeric" autocomplete="off" value="' . htmlspecialchars(extratoFormatoBr($dataInicialPadrao), ENT_QUOTES, 'UTF-8') . '" style="width:80px;min-width:80px;"></div>';
echo '<div><label for="DataFim">Data final</label><input type="text" id="DataFim" name="DataF" placeholder="dd/mm/aaaa" inputmode="numeric" autocomplete="off" value="' . htmlspecialchars(extratoFormatoBr($dataFinalPadrao), ENT_QUOTES, 'UTF-8') . '" style="width:80px;min-width:80px;"></div>';

// Mês atual
$mesAtualInicioJs = htmlspecialchars($mesAtualInicio, ENT_QUOTES, 'UTF-8');
$mesAtualFimJs = htmlspecialchars($mesAtualFim, ENT_QUOTES, 'UTF-8');
$anoAtualInicioJs = htmlspecialchars($anoAtualInicio, ENT_QUOTES, 'UTF-8');
$anoAtualFimJs = htmlspecialchars($anoAtualFim, ENT_QUOTES, 'UTF-8');
$hojeJs = htmlspecialchars($hoje, ENT_QUOTES, 'UTF-8');
$proximos30FimJs = htmlspecialchars($proximos30Fim, ENT_QUOTES, 'UTF-8');
echo '<button type="button" class="periodo-rapido-btn" onclick="setPeriodoRapido(\'' . $mesAtualInicioJs . '\', \'' . $mesAtualFimJs . '\')">Gerar p/ mês atual</button>';
echo '<button type="button" class="periodo-rapido-btn" onclick="setPeriodoRapido(\'' . $anoAtualInicioJs . '\', \'' . $anoAtualFimJs . '\')">Gerar p/ ano atual</button>';
echo '<button type="button" class="periodo-rapido-btn" onclick="setPeriodoRapido(\'' . $hojeJs . '\', \'' . $proximos30FimJs . '\')">Gerar p/ os próximos 30 dias</button>';

// Botões de período rápido ao lado da data final
echo '<div style="display:flex;gap:5px;align-items:end;margin-left:10px;">';
echo '<button type="submit" class="periodo-rapido-btn" style="font-style: italic; min-width:100px;">Gerar p/ período digitado</button>';

echo '</div>';

echo '</div>';
echo '</form>';

// Script para preencher datas rapidamente e preparar datas para envio
echo "<script>
                function setPeriodoRapido(di, df) {
                    document.getElementById('DataI').value = di.split('-').reverse().join('/');
                    document.getElementById('DataFim').value = df.split('-').reverse().join('/');
                    var form = document.getElementById('DataI').form;
                    if (prepararDatasFiltro(form)) {
                        form.submit();
                    }
                }

                function prepararDatasFiltro(form) {
                    // Converte datas para formato yyyy-mm-dd antes de enviar
                    var di = form.DataI;
                    var df = form.DataF;
                    if (di && df) {
                        var vdi = di.value.split('/');
                        var vdf = df.value.split('/');
                        if (vdi.length === 3) di.value = vdi[2] + '-' + vdi[1].padStart(2, '0') + '-' + vdi[0].padStart(2, '0');
                        if (vdf.length === 3) df.value = vdf[2] + '-' + vdf[1].padStart(2, '0') + '-' + vdf[0].padStart(2, '0');
                    }
                    return true;
                }
            </script>";


echo '<form class="filtro filtro-form" method="get" action="Extrato.php">';
echo '<input type="hidden" name="DataI" value="' . htmlspecialchars($dataInicial, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="DataF" value="' . htmlspecialchars($dataFinal, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="Evento" value="' . htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') . '">';
echo '<div><label for="SaldoAnterior">Saldo anterior ao período (R$)</label><input id="SaldoAnterior" name="SaldoAnterior" type="text" inputmode="decimal" maxlength="20" placeholder="0,00" value="' . htmlspecialchars(extratoValorFormatado($saldoAnterior), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<button type="submit" class="filtro-acao">Incluir saldo anterior</button>';
echo '</form>';

echo '<form class="filtro filtro-form" method="get" action="Extrato.php">';
echo '<input type="hidden" name="DataI" value="' . htmlspecialchars($dataInicial, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="DataF" value="' . htmlspecialchars($dataFinal, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="SaldoAnterior" value="' . htmlspecialchars((string)$saldoAnterior, ENT_QUOTES, 'UTF-8') . '">';
echo '<div><label for="Evento">Filtrar por evento</label><input id="Evento" name="Evento" type="text" maxlength="100" placeholder="Digite parte do nome do evento" value="' . htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<button type="submit" class="filtro-acao">Aplicar filtro</button>';
echo '<a class="filtro-acao" href="' . htmlspecialchars($limparEventoUrl, ENT_QUOTES, 'UTF-8') . '">Limpar filtro</a>';
echo '</form>';
if ($eventoFiltro !== '') {
    echo '<div class="filtro">Evento filtrado: <strong>' . htmlspecialchars($eventoFiltro, ENT_QUOTES, 'UTF-8') . '</strong></div>';
}


echo '<div class="acoes-grafico"><button type="button" id="toggleGraficoBtn" class="btn-grafico">Mostrar gráfico</button></div>';

echo '<div class="grafico is-hidden" id="grafico">';
echo '<h3>Saldo Final Acumulado por Dia</h3>';
if (count($movimentos) === 0) {
    echo '<p>Sem dados para gerar gráfico no período selecionado.</p>';
} else {
    echo '<div class="grafico-wrap"><canvas id="graficoExtrato" style="width:1100px;height:400px;"></canvas></div>';
}
echo '</div>';

echo '<table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>DiaUtil</th>
                        <th>Evento</th>
                        <th>Grupo</th>
                        <th>Débito (R$)</th>
                        <th>Crédito (R$)</th>
                        <th>Saldo Acumulado (R$)</th>
                    </tr>
                </thead>
                <tbody>';

if (count($movimentos) === 0) {
    echo '<tr>
                        <td colspan="7">Nenhum evento cadastrado...!</td>
                    </tr>';
} else {
    $saldoAcumulado = $saldoAnterior;

    // Cores para alternar entre datas
    $extratoCores = [
        '#f8fafc', // cor1
        '#e6f7ff', // cor2
        '#fffbe6', // cor3
        '#f6ffed', // cor4
        '#fff0f6', // cor5
    ];
    $ultimaData = null;
    $corIndex = 0;
    $ultimoMes = null;

    foreach ($movimentos as $mov) {
        $dc = (string)$mov['DC'];
        $classe = ($dc === 'D') ? 'deb' : 'cre';
        $valor = (float)$mov['ValorE'];
        $valorComSinal = ($dc === 'D') ? ($valor * -1) : $valor;
        $saldoAcumulado += $valorComSinal;

        // Troca de cor ao mudar a data
        if ($ultimaData !== $mov['dataM']) {
            $corIndex = ($corIndex + 1) % count($extratoCores);
            $ultimaData = $mov['dataM'];
        }
        $corFundo = $extratoCores[$corIndex];

        // Borda superior ao iniciar um novo mês (exceto o primeiro mês exibido)
        $mesMov = substr((string)$mov['dataM'], 0, 7);
        $estiloTr = 'background:' . $corFundo;
        if ($ultimoMes !== null && $mesMov !== $ultimoMes) {
            $estiloTr .= ';border-top:3px solid #1f4f82';
        }
        $ultimoMes = $mesMov;

        echo '<tr style="' . $estiloTr . '">';
        echo '<td>' . htmlspecialchars(extratoFormatoDataComSemana((string)$mov['dataM']), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((int)$mov['diaUtil'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$mov['evento'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)$mov['grupo'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td class="num deb">' . ($dc === 'D' ? extratoValorFormatado($valor) : '-') . '</td>';
        echo '<td class="num cre">' . ($dc === 'C' ? extratoValorFormatado($valor) : '-') . '</td>';
        echo '<td class="num">' . extratoValorFormatado($saldoAcumulado) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
            </table>';
    echo '<script>
                (function() {
                    var btn = document.getElementById("toggleGraficoBtn");
                    var grafico = document.getElementById("grafico");
                    if (!btn || !grafico) {
                        return;
                    }
                    btn.addEventListener("click", function() {
                        var oculto = grafico.classList.toggle("is-hidden");
                        btn.textContent = oculto ? "Mostrar gráfico" : "Ocultar gráfico";
                    });
                })();
            </script>';
    if (count($movimentos) > 0) {
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script>
                const labels = ' . $graficoLabelsJson . ';
                const dadosSaldo = ' . $graficoSaldosJson . ';
                const ctx = document.getElementById("graficoExtrato");
                if (ctx) {
                    new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Saldo acumulado",
                                data: dadosSaldo,
                                backgroundColor: "rgba(31,79,130,0.75)",
                                borderColor: "rgba(31,79,130,1)",
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: "top"
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            </script>';
    }

    echo '</div>';
    echo '</body>';
    echo '

</html>';
}
