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
    // Torna o prorroga padrão "sim" se não estiver definido
    if (!isset($evento['prorroga']) || $evento['prorroga'] === '' || $evento['prorroga'] === null) {
        $evento['prorroga'] = 'sim';
    }
    // Torna o ativo padrão "sim" se não estiver definido
    if (!isset($evento['ativo']) || $evento['ativo'] === '' || $evento['ativo'] === null) {
        $evento['ativo'] = 'sim';
    }
    // Torna o diario padrão "não" se não estiver definido
    if (!isset($evento['diario']) || $evento['diario'] === '' || $evento['diario'] === null) {
        $evento['diario'] = 'não';
    }
    mysqli_stmt_close($stmtSelect);
    mysqli_close($dbcon);

    $erroParam = filter_input(INPUT_GET, 'erro', FILTER_VALIDATE_INT);
    $msgParam = trim((string)($_GET['msg'] ?? ''));
    $camposParam = trim((string)($_GET['campos'] ?? ''));
    $mensagemErro = '';

    if ($erroParam === 1) {
        if ($msgParam === 'campos_obrigatorios') {
            $mensagemErro = 'Preencha os campos obrigatórios: ' . h($camposParam) . '.';
        } elseif ($msgParam === 'grupo_obrigatorio') {
            $mensagemErro = 'Campo Grupo é obrigatório.';
        } elseif ($msgParam === 'recorrencia_obrigatoria') {
            $mensagemErro = 'Ao menos uma recorrência deve ser selecionada.';
        } else {
            $mensagemErro = 'Não foi possível salvar as alterações.';
        }
    }

?>
    <!doctype html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="../css/incluir.css?v=20260418f" />
        <title>Eventos-Edição</title>
        <style>
            .is-invalid {
                color: #d9534f !important;
            }
        </style>
    </head>

    <body>
        <section class="menu-shell">
            
            <nav id="menu-h" aria-label="Menu principal">
                <ul>
                    <li><a href="../Incluir.html">(*) Incluir evento</a></li>
                    <li><a href="ListaEventos.php">Lista de eventos</a></li>
                    <li><a id="gerarExtratoBtn" href="./">Gerar extrato</a></li>
                    <li><a href="tabFeriados.php">Consulta Feriados</a></li>
                    <li><a href="tabDatas.php">Consulta Datas</a></li>
                    <li><a href="logout.php" style="color:dimgray;"
                            onclick="return confirm('Tem certeza que deseja trocar de usuário? Isso encerrará sua sessão atual.');">Trocar
                            usuário</a></li>
                </ul>
                </ul>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var btn = document.getElementById('gerarExtratoBtn');
                        if (btn) {
                            btn.addEventListener('click', function (e) {
                                e.preventDefault();
                                var hoje = new Date();
                                var umAnoDepois = new Date();
                                umAnoDepois.setFullYear(hoje.getFullYear() + 1);
                                function formatarData(dt) {
                                    var m = String(dt.getMonth() + 1).padStart(2, '0');
                                    var d = String(dt.getDate()).padStart(2, '0');
                                    return dt.getFullYear() + '-' + m + '-' + d;
                                }
                                var dataI = formatarData(hoje);
                                var dataF = formatarData(umAnoDepois);
                                var url = 'Extrato.php?DataI=' + encodeURIComponent(dataI) + '&DataF=' + encodeURIComponent(dataF);
                                window.location.href = url;
                            });
                        }
                    });
                </script>

            </nav>

            <br><br>


        </section>

        <main>
        
        <br><br><br><br><br>

        <?php if ($mensagemErro !== ''): ?>
            <p class="periodo-status is-invalid" id="area-mensagens"><?php echo $mensagemErro; ?></p>
        <?php endif; ?>

        <button type="submit" form="formEditar" title="Salvar evento" style="position:absolute; background: none; border: none; cursor: pointer; padding: 0;">
                    <img src="../images/salvar.png" alt="Salvar"></button>

            <form id="formEditar" action="editar.php" method="post" onsubmit="return validarFormulario(event)">
                <input type="hidden" name="id" value="<?php echo h($evento['id']); ?>" />
            <h2>Informações do evento em edição</h2>
                <section>
                    <fieldset>
                        <legend>Informações do evento em edição</legend>

                        <div class="linha-evento-grupo-valor">
                            <input type="text" name="evento" style="margin: 12px;font-weight:bold;font-size:20px;" id="evento" value="<?php echo h($evento['evento']); ?>" placeholder="nome do evento..." />
                            <input type="text" id="grupo" name="grupo" style="margin: 12px;font-weight:bold;font-size:20px;" value="<?php echo h($evento['grupo']); ?>" placeholder="digite grupo ou conta..." />
                            <input type="text" name="valorE" id="valorE" value="<?php echo h($evento['valorE']); ?>" style="margin: 12px;font-weight:bold;font-size:20px;" placeholder="apenas digitos..." />
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="ativo" name="ativo" checked="True" value="sim" />
                            <label for="ativo">Ativo</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="inativo" name="ativo" value="não" />
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
                                <input type="radio" id="prorrogaS" value="sim" name="prorroga" <?php echo checkedAttr(($evento['prorroga'] ?? '') === 'sim'); ?> />
                                <label for="prorrogaS">Sim, prorroga, pois paga ou recebe depois do feriado ou fim de semana</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="prorrogaN" value="não" name="prorroga" <?php echo checkedAttr(($evento['prorroga'] ?? '') === 'não'); ?> />
                                <label for="prorrogaN">Não, não prorroga, pois tem que pagar ou receber antes do feriado ou fim de semana</label>
                            </div>
                            <div class="radio-option">
                                <input id="prorrogaNulo" type="radio" value="nulo" name="prorroga" <?php echo checkedAttr(strtolower($evento['prorroga'] ?? '') === 'nulo'); ?> />
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
                            <input type="radio" id="diarioS" value="sim" name="diario" <?php echo checkedAttr(($evento['diario'] ?? '') === 'sim'); ?> />
                            <label for="diarioS">Sim, ocorre todos os dias...</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="diarioN" value="não" name="diario" <?php echo checkedAttr(($evento['diario'] ?? '') === 'não'); ?> />
                            <label for="diarioN">Não, não é diario...</label>
                        </div>

                        <br>

                        <select id="diaM" name="diaM">
                            <option value="0">escolha o dia do mês ou decêncio...</option>
                            <option value="1" <?php echo selectedAttr($evento['diaM'] ?? '0', '1'); ?>>1 ou 1º decêncio</option>
                            <option value="2" <?php echo selectedAttr($evento['diaM'] ?? '0', '2'); ?>>2</option>
                            <option value="3" <?php echo selectedAttr($evento['diaM'] ?? '0', '3'); ?>>3</option>
                            <option value="4" <?php echo selectedAttr($evento['diaM'] ?? '0', '4'); ?>>4</option>
                            <option value="5" <?php echo selectedAttr($evento['diaM'] ?? '0', '5'); ?>>5</option>
                            <option value="6" <?php echo selectedAttr($evento['diaM'] ?? '0', '6'); ?>>6</option>
                            <option value="7" <?php echo selectedAttr($evento['diaM'] ?? '0', '7'); ?>>7</option>
                            <option value="8" <?php echo selectedAttr($evento['diaM'] ?? '0', '8'); ?>>8</option>
                            <option value="9" <?php echo selectedAttr($evento['diaM'] ?? '0', '9'); ?>>9</option>
                            <option value="10" <?php echo selectedAttr($evento['diaM'] ?? '0', '10'); ?>>10</option>
                            <option value="11" <?php echo selectedAttr($evento['diaM'] ?? '0', '11'); ?>>11 ou 2º decêncio</option>
                            <option value="12" <?php echo selectedAttr($evento['diaM'] ?? '0', '12'); ?>>12</option>
                            <option value="13" <?php echo selectedAttr($evento['diaM'] ?? '0', '13'); ?>>13</option>
                            <option value="14" <?php echo selectedAttr($evento['diaM'] ?? '0', '14'); ?>>14</option>
                            <option value="15" <?php echo selectedAttr($evento['diaM'] ?? '0', '15'); ?>>15</option>
                            <option value="16" <?php echo selectedAttr($evento['diaM'] ?? '0', '16'); ?>>16</option>
                            <option value="17" <?php echo selectedAttr($evento['diaM'] ?? '0', '17'); ?>>17</option>
                            <option value="18" <?php echo selectedAttr($evento['diaM'] ?? '0', '18'); ?>>18</option>
                            <option value="19" <?php echo selectedAttr($evento['diaM'] ?? '0', '19'); ?>>19</option>
                            <option value="20" <?php echo selectedAttr($evento['diaM'] ?? '0', '20'); ?>>20</option>
                            <option value="21" <?php echo selectedAttr($evento['diaM'] ?? '0', '21'); ?>>21 ou 3º decêncio</option>
                            <option value="22" <?php echo selectedAttr($evento['diaM'] ?? '0', '22'); ?>>22</option>
                            <option value="23" <?php echo selectedAttr($evento['diaM'] ?? '0', '23'); ?>>23</option>
                            <option value="24" <?php echo selectedAttr($evento['diaM'] ?? '0', '24'); ?>>24</option>
                            <option value="25" <?php echo selectedAttr($evento['diaM'] ?? '0', '25'); ?>>25</option>
                            <option value="26" <?php echo selectedAttr($evento['diaM'] ?? '0', '26'); ?>>26</option>
                            <option value="27" <?php echo selectedAttr($evento['diaM'] ?? '0', '27'); ?>>27</option>
                            <option value="28" <?php echo selectedAttr($evento['diaM'] ?? '0', '28'); ?>>28</option>
                            <option value="29" <?php echo selectedAttr($evento['diaM'] ?? '0', '29'); ?>>29</option>
                            <option value="30" <?php echo selectedAttr($evento['diaM'] ?? '0', '30'); ?>>30</option>
                            <option value="31" <?php echo selectedAttr($evento['diaM'] ?? '0', '31'); ?>>31</option>
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
                        <label for="dataFixa">ou escolha uma data em que o evento ocorre...</label>
                        <input type="date" id="dataFixa" style="margin:12px;" name="dataFixa" value="<?php echo h($evento['dataFixa']); ?>" />
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
        <script>
            // Garante que ao abrir a tela de edição, se dataFixa vier preenchida, limpa os outros campos de recorrência
            document.addEventListener("DOMContentLoaded", function() {
                var dataFixa = document.getElementById("dataFixa");
                if (dataFixa && dataFixa.value) {
                    // Limpa todos os radios de diário
                    var radiosDiario = document.querySelectorAll('input[name="diario"]');
                    radiosDiario.forEach(function(radio) {
                        radio.checked = false;
                    });

                    // Limpa todos os selects de recorrência
                    var todosSelects = [
                        document.getElementById("diaM"),
                        document.getElementById("diaS"),
                        document.getElementById("diaU"),
                        document.getElementById("UPA"),
                        document.getElementById("diaR"),
                        document.getElementById("mesR"),
                        document.getElementById("semN"),
                        document.getElementById("semD"),
                        document.getElementById("semM")
                    ];
                    todosSelects.forEach(function(select) {
                        if (select) {
                            select.selectedIndex = 0;
                            var evt = document.createEvent("HTMLEvents");
                            evt.initEvent("change", true, false);
                            select.dispatchEvent(evt);

                            // Força atualização do visual customizado se existir wrapper
                            var wrapper = select.nextElementSibling;
                            if (wrapper && wrapper.classList && wrapper.classList.contains("custom-select")) {
                                wrapper.classList.remove("has-selection");
                                var label = wrapper.querySelector(".custom-select-label");
                                if (label && select.options.length > 0) {
                                    label.textContent = "\u00A0" + select.options[0].textContent.trim();
                                }
                                var optionsLi = wrapper.querySelectorAll(".custom-select-option");
                                optionsLi.forEach(function(li, idx) {
                                    li.classList.remove("is-selected");
                                    if (idx === 0) li.classList.add("is-selected");
                                });
                            }
                        }
                    });
                }
            });
        </script>
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
$dataFixa = nullIfDefault(getPostValue('dataFixa'));
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

if ($diario !== 'sim' && $diario !== 'não') {
    $diario = null;
}

if ($ativo !== 'sim' && $ativo !== 'não') {
    $ativo = null;
}

if ($prorroga !== 'sim' && $prorroga !== 'não' && strtolower($prorroga) !== 'nulo') {
    $prorroga = null;
}


$camposObrigatorios = array();

// Validação: pelo menos um campo de recorrência deve ser preenchido
$recorrenciaPreenchida = false;
if (
    ($diario === 'sim' || $diario === 'não') ||
    (!empty($diaM) && $diaM !== '0') ||
    (!empty($diaS)) ||
    (!empty($diaU) && $diaU !== '0') ||
    (!empty($UPA) && $UPA !== '0') ||
    (!empty($dataFixa)) ||
    (!empty($diaR) && $diaR !== '0') ||
    (!empty($mesR) && $mesR !== '0') ||
    (!empty($semN)) ||
    (!empty($semD)) ||
    (!empty($semM))
) {
    $recorrenciaPreenchida = true;
}

if (!$recorrenciaPreenchida) {
    mysqli_close($dbcon);
    header('Location: ListaEventos.php?erro=1&msg=recorrencia_obrigatoria&id=' . $id);
    exit();
}

if (isBlankValue($evento)) {
    $camposObrigatorios[] = 'Evento';
}

if (isBlankValue($grupo)) {
    $camposObrigatorios[] = 'Grupo';
}

if (isBlankValue(getPostValue('valorE'))) {
    $camposObrigatorios[] = 'Valor R$';
}

if (isBlankValue(getPostValue('valorE'))) {
    $camposObrigatorios[] = 'Valor R$';
}

if ($DC === null) {
    $camposObrigatorios[] = 'DC';
}

// Remove qualquer menção a 'Prorroga' como campo obrigatório
if (!empty($camposObrigatorios)) {
    $campos = urlencode(implode(', ', array_filter($camposObrigatorios, function ($campo) {
        return strtolower($campo) !== 'prorroga';
    })));
    mysqli_close($dbcon);
    header('Location: editar.php?id=' . $id . '&erro=1&msg=campos_obrigatorios&campos=' . $campos);
    exit();
}

$diaHora = date('Y-m-d H:i:s');

if (!empty($dataFixa) && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataFixa, $matches)) {
    $dataFixa = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
} elseif (empty($dataFixa)) {
    $dataFixa = null;
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
    dataFixa = ?,
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
    $dataFixa,
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
