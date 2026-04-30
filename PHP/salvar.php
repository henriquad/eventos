<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

include_once "Conectar_BaseH.php";

date_default_timezone_set("America/Sao_Paulo");

// Função auxiliar para pegar valores POST
function getPostValue($name, $type = 'string')
{
    if (!isset($_POST[$name])) {
        return $type === 'int' ? 0 : '';
    }
    $value = trim($_POST[$name]);
    if ($type === 'int') {
        return $value !== '' ? intval($value) : 0;
    }
    return $value;
}

function normalizeDecimal($value)
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }

    $raw = str_replace(' ', '', $raw);
    $hasComma = strpos($raw, ',') !== false;
    $hasDot = strpos($raw, '.') !== false;

    if ($hasComma && $hasDot) {
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        if ($lastComma > $lastDot) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '', $raw);
        }
    } elseif ($hasComma) {
        $raw = str_replace(',', '.', $raw);
    }

    return is_numeric($raw) ? (float)$raw : 0.0;
}

function nullIfDefault($value, array $defaults = array('', '0'))
{
    $normalized = trim((string)$value);
    return in_array($normalized, $defaults, true) ? null : $normalized;
}

function isBlankValue($value)
{
    return preg_match('/^\s*$/u', (string)$value) === 1;
}



// Recebe os dados do formulário
$evento = getPostValue('evento');
$grupo = getPostValue('grupo');
$valorE = normalizeDecimal(getPostValue('valorE'));
$DC = getPostValue('DC');
$diario = getPostValue('diario');
$diaM = nullIfDefault(getPostValue('diaM'));
$diaS = nullIfDefault(getPostValue('diaS'));
$diaU = nullIfDefault(getPostValue('diaU'));
$dataFixa = nullIfDefault(getPostValue('dataFixa'));
$prorroga = getPostValue('prorroga');
$UPA = nullIfDefault(getPostValue('UPA'));
$semN = nullIfDefault(getPostValue('semN'));
$semM = nullIfDefault(getPostValue('semM'));
$semD = nullIfDefault(getPostValue('semD'));
$diaR = nullIfDefault(getPostValue('diaR'));
$mesR = nullIfDefault(getPostValue('mesR'));
$ativo = getPostValue('ativo');
$diaHora = date('Y-m-d H:i:s');
$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

if ($DC !== 'D' && $DC !== 'C') {
    $DC = null;
}

if ($diario !== 'sim' && $diario !== 'não') {
    $diario = null;
}

if ($ativo !== 'sim' && $ativo !== 'não') {
    $ativo = null;
}

if ($prorroga !== 'sim' && $prorroga !== 'não' && $prorroga !== 'nul') {
    $prorroga = null;
}

$camposObrigatorios = array();

if (isBlankValue($evento)) {
    $camposObrigatorios[] = 'Evento';
}

if (isBlankValue($grupo)) {
    $camposObrigatorios[] = 'Grupo';
}

if ($DC === null) {
    $camposObrigatorios[] = 'DC';
}

if ($prorroga === null) {
    $camposObrigatorios[] = 'Prorroga';
}

if (!empty($camposObrigatorios)) {
    $campos = urlencode(implode(', ', $camposObrigatorios));
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=campos_obrigatorios&campos=' . $campos);
    exit();
}

if (isBlankValue($grupo)) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=grupo_obrigatorio');
    exit();
}


// Aceita tanto yyyy-mm-dd (padrão do input type=date) quanto dd/mm/aaaa
if (!empty($dataFixa)) {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataFixa, $matches)) {
        $dataFixa = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dataFixa, $matches)) {
        $dataFixa = $dataFixa;
    } else {
        $dataFixa = null;
    }
} else {
    $dataFixa = null;
}



eventosGarantirSchema($dbcon);

// Insere os dados com prepared statement (proteção contra SQL Injection)
$sql = "INSERT INTO eventos(
   evento, valorE, grupo, apelido, senha, DC, prorroga, diario, 
    diaM, diaS, diaU, UPA, dataFixa, semN, semM, semD, diaR, mesR, ativo,  diaHora
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($dbcon, $sql);
if (!$stmt) {
    die('Erro na preparação da query: ' . mysqli_error($dbcon));
}

// s = string, d = double, i = int
mysqli_stmt_bind_param(
    $stmt,
    "sdssssssssssssssssss",
    $evento,
    $valorE,
    $grupo,
    $apelido,
    $senha,
    $DC,
    $prorroga,
    $diario,
    $diaM,
    $diaS,
    $diaU,
    $UPA,
    $dataFixa,
    $semN,
    $semM,
    $semD,
    $diaR,
    $mesR,
    $ativo,
    $diaHora
);

if (!mysqli_stmt_execute($stmt)) {
    die('Erro ao salvar: ' . mysqli_stmt_error($stmt));
}

$lastId = mysqli_insert_id($dbcon);

mysqli_stmt_close($stmt);
mysqli_close($dbcon);

// Redireciona para a lista apos incluir
$destino = '/eventosHTML/PHP/ListaEventos.php?incluido=1&id=' . $lastId;

if (!headers_sent()) {
    header('Location: ' . $destino, true, 303);
    exit();
}

echo '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8">';
echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '">';
echo '<title>Redirecionando...</title></head><body>';
echo '<script>window.location.href=' . json_encode($destino) . ';</script>';
echo '<p>Redirecionando para a lista de eventos... ';
echo '<a href="' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '">clique aqui</a>.</p>';
echo '</body></html>';
exit();
