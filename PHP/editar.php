<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

include_once "Conectar_BaseH.php";

date_default_timezone_set("America/Sao_Paulo");

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

eventosGarantirSchema($dbcon);

function getPostValue($name, $type = 'string')
{
    if (!isset($_POST[$name])) {
        return $type === 'int' ? 0 : '';
    }

    $value = trim((string)$_POST[$name]);
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

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checkedAttr($condition)
{
    return $condition ? ' checked' : '';
}

function selectedAttr($actual, $expected)
{
    return (string)$actual === (string)$expected ? ' selected' : '';
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        mysqli_close($dbcon);
        header('Location: ListaEventos.php?erro=1&msg=id_invalido_edicao');
        exit();
    }

    $id = intval($id);
    $sqlSelect = "SELECT * FROM eventos WHERE id = ? AND apelido = ? AND senha = ? LIMIT 1";
    $stmtSelect = mysqli_prepare($dbcon, $sqlSelect);

    if (!$stmtSelect) {
        mysqli_close($dbcon);
        header('Location: ListaEventos.php?erro=1&msg=preparo_edicao');
        exit();
    }

    mysqli_stmt_bind_param($stmtSelect, "iss", $id, $apelido, $senha);

    if (!mysqli_stmt_execute($stmtSelect)) {
        mysqli_stmt_close($stmtSelect);
        mysqli_close($dbcon);
        header('Location: ListaEventos.php?erro=1&msg=execucao_edicao&id=' . $id);
        exit();
    }

    $resultado = mysqli_stmt_get_result($stmtSelect);

    if (!$resultado || mysqli_num_rows($resultado) === 0) {
        mysqli_stmt_close($stmtSelect);
        mysqli_close($dbcon);
        header('Location: ListaEventos.php?erro=1&msg=registro_nao_encontrado&id=' . $id);
        exit();
    }

    $evento = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmtSelect);
    mysqli_close($dbcon);

?>
    <!doctype html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="../css/incluir.css?v=20260418f" />
        <title>Eventos-Edição</title>
    </head>

    <body>
        <header class="incluir-hero">
            <h1>Fluxo de caixa - Edição de eventos</h1>
            <div class="button_linha">

                <button type="submit" form="formEditar" style="background: none; border: none; cursor: pointer; padding: 0;">
                    <img src="../images/salvar.png" alt="Salvar"></button>

                <a class="menu-shell__logout" href="../php/ListaEventos.php">Voltar ao menu</a>
            </div>
        </header>

        <main>


            <form id="formEditar" action="editar.php" method="post">
                <input type="hidden" name="id" value="<?php echo h($evento['id']); ?>" />

                <section>
                    <fieldset>
                        <legend>Informações do evento em edição</legend>

                        <div class="linha-evento-grupo-valor">
                            <input type="text" name="evento" style="margin: 12px;" id="evento" value="<?php echo h($evento['evento']); ?>" placeholder="nome do evento..." />
                            <input type="text" id="grupo" name="grupo" style="margin: 12px;" value="<?php echo h($evento['grupo']); ?>" placeholder="digite grupo ou conta..." />
                            <input type="text" name="valorE" id="valorE" value="<?php echo h($evento['valorE']); ?>" style="margin: 12px;" placeholder="apenas digitos..." />
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="ativo" name="ativo" checked="True" value="Sim" />
                            <label for="ativo">Ativo</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="inativo" name="ativo" value="Não" />
                            <label for="inativo">Não ativo, aguardando outras considerações</label>
                        </div>

                    </fieldset>

                    <div class="linha-dc-prorroga">
                        <fieldset>
                            <legend>(*) DC</legend>
                            <br>
                            <div class="radio-option">
                                <input type="radio" id="DD" name="DC" value="D" <?php echo checkedAttr(($evento['DC'] ?? '') === 'D'); ?> />
                                <label for="DD">Débito ou Despesa ou Saída ou Cheque ou Pagamento...</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="CC" name="DC" value="C" <?php echo checkedAttr(($evento['DC'] ?? '') === 'C'); ?> />
                                <label for="CC">Crédito ou Receita ou Entrada ou Depósito ou Recebimento...</label>
                            </div>
                        </fieldset>
                        <br>
                        <fieldset>
                            <legend>(*) Prorroga?</legend>
                            <br>
                            <div class="radio-option">
                                <input type="radio" id="prorrogaS" value="Sim" name="prorroga" <?php echo checkedAttr(($evento['prorroga'] ?? '') === 'Sim'); ?> />
                                <label for="prorrogaS">Sim, prorroga, pois paga ou recebe depois do feriado ou fim de semana</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="prorrogaN" value="Não" name="prorroga" <?php echo checkedAttr(($evento['prorroga'] ?? '') === 'Não'); ?> />
                                <label for="prorrogaN">Não, não prorroga, pois tem que pagar ou receber antes do feriado ou fim de semana</label>
                            </div>
                            <div class="radio-option">
                                <input id="prorrogaNulo" type="radio" value="Nulo" name="prorroga" <?php echo checkedAttr(($evento['prorroga'] ?? '') === 'Nulo'); ?> />
                                <label for="prorrogaNulo">Nulo, pois evento não ocorre se for feriado ou fim de semana</label>
                            </div>
                        </fieldset>
                    </div>
                </section>

                <h2>(*) Recorrências do evento</h2>

                <section>

                    <br>
                    <fieldset>
                        <legend>escolha apenas uma das 6 opções...</legend>
                        <div class="radio-option">
                            <input type="radio" id="diarioS" value="Sim" name="diario" />
                            <label for="diarioS">Sim, ocorre todos os dias...</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="diarioN" checked="True" value="Não" name="diario" />
                            <label for="diarioN">Não, não é diario...</label>
                        </div>

                        <br>

                        <select id="diaM" name="diaM">
                            <option value="0">escolha o dia do mês...</option>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <option value="<?php echo $i; ?>" <?php echo selectedAttr($evento['diaM'] ?? '0', (string)$i); ?>><?php echo $i; ?></option>
                            <?php } ?>
                        </select>
                        <br>

                        <select id="diaS" name="diaS">
                            <option value="">ou escolha o dia da semana...</option>
                            <option value="Seg" <?php echo selectedAttr($evento['diaS'] ?? '', 'Seg'); ?>>Segunda-feira</option>
                            <option value="Ter" <?php echo selectedAttr($evento['diaS'] ?? '', 'Ter'); ?>>Terça-feira</option>
                            <option value="Qua" <?php echo selectedAttr($evento['diaS'] ?? '', 'Qua'); ?>>Quarta-feira</option>
                            <option value="Qui" <?php echo selectedAttr($evento['diaS'] ?? '', 'Qui'); ?>>Quinta-feira</option>
                            <option value="Sex" <?php echo selectedAttr($evento['diaS'] ?? '', 'Sex'); ?>>Sexta-feira</option>
                            <option value="Sab" <?php echo selectedAttr($evento['diaS'] ?? '', 'Sab'); ?>>Sábado</option>
                            <option value="Dom" <?php echo selectedAttr($evento['diaS'] ?? '', 'Dom'); ?>>Domingo</option>
                        </select>
                        <br>


                        <select id="diaU" name="diaU">
                            <option value="0">ou escolha o dia útil no mês...</option>
                            <?php for ($i = 1; $i <= 23; $i++) { ?>
                                <option value="<?php echo $i; ?>" <?php echo selectedAttr($evento['diaU'] ?? '0', (string)$i); ?>><?php echo $i; ?>º</option>
                            <?php } ?>
                        </select>
                        <br>


                        <select id="UPA" name="UPA">
                            <option value="0">ou escolha o fim do mês...</option>
                            <option value="7" <?php echo selectedAttr($evento['UPA'] ?? '0', '7'); ?>>Antepenúltimo dia útil</option>
                            <option value="8" <?php echo selectedAttr($evento['UPA'] ?? '0', '8'); ?>>Penúltimo dia útil</option>
                            <option value="9" <?php echo selectedAttr($evento['UPA'] ?? '0', '9'); ?>>Último dia útil</option>
                        </select>
                        <br>


                        <br>
                        <br>
                        <label for="dataF">ou escolha uma data em que o evento ocorre...</label>
                        <input type="date" id="dataF" style="margin:12px;" name="dataF" value="<?php echo h($evento['dataF']); ?>" />
                    </fieldset>

                </section>

                <section>

                    <br />

                    <fieldset>
                        <legend>ou escolha as 2 opções juntas abaixo...</legend>
                        <select id="diaR" name="diaR">
                            <option value="0">escolha o dia do mês que repete...</option>
                            <?php for ($i = 1; $i <= 31; $i++) { ?>
                                <option value="<?php echo $i; ?>" <?php echo selectedAttr($evento['diaR'] ?? '0', (string)$i); ?>><?php echo $i; ?></option>
                            <?php } ?>
                        </select>
                        <br>


                        <select id="mesR" name="mesR">
                            <option value="0"> e escolha o mês da data que repete...</option>
                            <option value="Jan" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Jan'); ?>>Janeiro</option>
                            <option value="Fev" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Fev'); ?>>Fevereiro</option>
                            <option value="Mar" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Mar'); ?>>Março</option>
                            <option value="Abr" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Abr'); ?>>Abril</option>
                            <option value="Mai" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Mai'); ?>>Maio</option>
                            <option value="Jun" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Jun'); ?>>Junho</option>
                            <option value="Jul" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Jul'); ?>>Julho</option>
                            <option value="Ago" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Ago'); ?>>Agosto</option>
                            <option value="Set" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Set'); ?>>Setembro</option>
                            <option value="Out" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Out'); ?>>Outubro</option>
                            <option value="Nov" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Nov'); ?>>Novembro</option>
                            <option value="Dez" <?php echo selectedAttr($evento['mesR'] ?? '0', 'Dez'); ?>>Dezembro</option>
                        </select>
                    </fieldset>
                    <br><br>
                </section>
                <section>

                    <br>
                    <fieldset>
                        <legend>ou escolha as 3 opções juntas abaixo...</legend>
                        <select id="semN" name="semN">
                            <option value="">escolha a semana do mês...</option>
                            <option value="1" <?php echo selectedAttr($evento['semN'] ?? '', '1'); ?>>1ª semana do mês</option>
                            <option value="2" <?php echo selectedAttr($evento['semN'] ?? '', '2'); ?>>2ª semana do mês</option>
                            <option value="3" <?php echo selectedAttr($evento['semN'] ?? '', '3'); ?>>3ª semana do mês</option>
                            <option value="4" <?php echo selectedAttr($evento['semN'] ?? '', '4'); ?>>4ª semana do mês</option>
                            <option value="5" <?php echo selectedAttr($evento['semN'] ?? '', '5'); ?>>5ª semana do mês</option>
                            <option value="9" <?php echo selectedAttr($evento['semN'] ?? '', '9'); ?>>Última semana do mês</option>
                        </select>
                        <br>


                        <select id="semD" name="semD">
                            <option value="">e escolha o dia da semana...</option>
                            <option value="Seg" <?php echo selectedAttr($evento['semD'] ?? '', 'Seg'); ?>>Segunda-feira</option>
                            <option value="Ter" <?php echo selectedAttr($evento['semD'] ?? '', 'Ter'); ?>>Terça-feira</option>
                            <option value="Qua" <?php echo selectedAttr($evento['semD'] ?? '', 'Qua'); ?>>Quarta-feira</option>
                            <option value="Qui" <?php echo selectedAttr($evento['semD'] ?? '', 'Qui'); ?>>Quinta-feira</option>
                            <option value="Sex" <?php echo selectedAttr($evento['semD'] ?? '', 'Sex'); ?>>Sexta-feira</option>
                            <option value="Sáb" <?php echo selectedAttr($evento['semD'] ?? '', 'Sáb'); ?>>Sábado</option>
                            <option value="Sab" <?php echo selectedAttr($evento['semD'] ?? '', 'Sab'); ?>>Sábado</option>
                            <option value="Dom" <?php echo selectedAttr($evento['semD'] ?? '', 'Dom'); ?>>Domingo</option>
                        </select>

                        <br>

                        <select id="semM" name="semM">
                            <option value="">e escolha o mês que repete...</option>
                            <option value="Jan" <?php echo selectedAttr($evento['semM'] ?? '', 'Jan'); ?>>Janeiro</option>
                            <option value="Fev" <?php echo selectedAttr($evento['semM'] ?? '', 'Fev'); ?>>Fevereiro</option>
                            <option value="Mar" <?php echo selectedAttr($evento['semM'] ?? '', 'Mar'); ?>>Março</option>
                            <option value="Abr" <?php echo selectedAttr($evento['semM'] ?? '', 'Abr'); ?>>Abril</option>
                            <option value="Mai" <?php echo selectedAttr($evento['semM'] ?? '', 'Mai'); ?>>Maio</option>
                            <option value="Jun" <?php echo selectedAttr($evento['semM'] ?? '', 'Jun'); ?>>Junho</option>
                            <option value="Jul" <?php echo selectedAttr($evento['semM'] ?? '', 'Jul'); ?>>Julho</option>
                            <option value="Ago" <?php echo selectedAttr($evento['semM'] ?? '', 'Ago'); ?>>Agosto</option>
                            <option value="Set" <?php echo selectedAttr($evento['semM'] ?? '', 'Set'); ?>>Setembro</option>
                            <option value="Out" <?php echo selectedAttr($evento['semM'] ?? '', 'Out'); ?>>Outubro</option>
                            <option value="Nov" <?php echo selectedAttr($evento['semM'] ?? '', 'Nov'); ?>>Novembro</option>
                            <option value="Dez" <?php echo selectedAttr($evento['semM'] ?? '', 'Dez'); ?>>Dezembro</option>
                        </select>
                    </fieldset>
                </section>


            </form>
        </main>

        <footer>
            <a href="#">Voltar ao topo</a>
        </footer>

        <script src="../js/incluir.js"></script>
    </body>

    </html>
<?php
    exit();
}

$id = getPostValue('id', 'int');

if (!$id || $id <= 0) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=id_invalido_edicao');
    exit();
}

$evento = getPostValue('evento');
$grupo = getPostValue('grupo');
$valorE = normalizeDecimal(getPostValue('valorE'));
$DC = getPostValue('DC');
$diario = getPostValue('diario');
$diaM = nullIfDefault(getPostValue('diaM'));
$diaS = nullIfDefault(getPostValue('diaS'));
$diaU = nullIfDefault(getPostValue('diaU'));
$dataF = nullIfDefault(getPostValue('dataF'));
$prorroga = getPostValue('prorroga');
$UPA = nullIfDefault(getPostValue('UPA'));
$semN = nullIfDefault(getPostValue('semN'));
$semM = nullIfDefault(getPostValue('semM'));
$semD = nullIfDefault(getPostValue('semD'));
$diaR = nullIfDefault(getPostValue('diaR'));
$mesR = nullIfDefault(getPostValue('mesR'));


if ($DC !== 'D' && $DC !== 'C') {
    $DC = null;
}

if (isBlankValue($grupo)) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=grupo_obrigatorio&id=' . $id);
    exit();
}

$diaSPermitidos = array('', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom');
if (!in_array($diaS, $diaSPermitidos, true)) {
    $diaS = null;
}

$ativo = getPostValue('ativo');

if ($diario !== 'Sim' && $diario !== 'Não') {
    $diario = null;
}

if ($ativo !== 'Sim' && $ativo !== 'Não') {
    $ativo = null;
}

if ($prorroga !== 'Sim' && $prorroga !== 'Não' && $prorroga !== 'Nulo') {
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
    header('Location: ListaEventos.php?erro=1&msg=campos_obrigatorios&campos=' . $campos . '&id=' . $id);
    exit();
}

$diaHora = date('Y-m-d H:i:s');

if (!empty($dataF) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataF, $matches)) {
    $dataF = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
} elseif (empty($dataF)) {
    $dataF = null;
}

$sqlExiste = "SELECT id FROM eventos WHERE id = ? AND apelido = ? AND senha = ? LIMIT 1";
$stmtExiste = mysqli_prepare($dbcon, $sqlExiste);

if (!$stmtExiste) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=preparo_edicao');
    exit();
}

mysqli_stmt_bind_param($stmtExiste, "iss", $id, $apelido, $senha);

if (!mysqli_stmt_execute($stmtExiste)) {
    mysqli_stmt_close($stmtExiste);
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=execucao_edicao&id=' . $id);
    exit();
}

$resultadoExiste = mysqli_stmt_get_result($stmtExiste);

if (!$resultadoExiste || mysqli_num_rows($resultadoExiste) === 0) {
    mysqli_stmt_close($stmtExiste);
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=registro_nao_encontrado&id=' . $id);
    exit();
}

mysqli_stmt_close($stmtExiste);

$sql = "UPDATE eventos SET
    evento = ?,
    valorE = ?,
    grupo = ?,
    DC = ?,
    prorroga = ?,
    diario = ?,
    diaM = ?,
    diaS = ?,
    diaU = ?,
    UPA = ?,
    dataF = ?,
    semN = ?,
    semM = ?,
    semD = ?,
    diaR = ?,
    mesR = ?,
    ativo = ?,    
    diaHora = ?
WHERE id = ? AND apelido = ? AND senha = ?";

$stmt = mysqli_prepare($dbcon, $sql);

if (!$stmt) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=preparo_edicao');
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "sdssssssssssssssssiss",
    $evento,
    $valorE,
    $grupo,
    $DC,
    $prorroga,
    $diario,
    $diaM,
    $diaS,
    $diaU,
    $UPA,
    $dataF,
    $semN,
    $semM,
    $semD,
    $diaR,
    $mesR,
    $ativo,
    $diaHora,
    $id,
    $apelido,
    $senha
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=execucao_edicao&id=' . $id);
    exit();
}

$registrosAfetados = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);
mysqli_close($dbcon);

if ($registrosAfetados >= 0) {
    header('Location: ListaEventos.php?editado=1&id=' . $id);
    exit();
}

header('Location: ListaEventos.php?erro=1&msg=registro_nao_alterado&id=' . $id);
exit();
