<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

$conn = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
mysqli_set_charset($conn, 'utf8');

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

eventosGarantirSchema($conn);

// Primeiro, obter as colunas válidas
$sql_test = 'SELECT * FROM eventos WHERE apelido = ? AND senha = ? LIMIT 1';
$stmt_test = mysqli_prepare($conn, $sql_test);
$colunas_validas = array();

if ($stmt_test) {
    mysqli_stmt_bind_param($stmt_test, 'ss', $apelido, $senha);
    if (mysqli_stmt_execute($stmt_test)) {
        $result_test = mysqli_stmt_get_result($stmt_test);
        if ($result_test) {
            $fields_temp = $result_test->fetch_fields();
            foreach ($fields_temp as $field) {
                $colunas_validas[] = $field->name;
            }
        }
    }
    mysqli_stmt_close($stmt_test);
}

// Parâmetros de ordenação
$coluna_ordem = filter_input(INPUT_GET, 'ordem', FILTER_SANITIZE_STRING);
$direcao_ordem = filter_input(INPUT_GET, 'direcao', FILTER_SANITIZE_STRING);

// Validar parâmetros de ordenação
if (empty($coluna_ordem) || !in_array($coluna_ordem, $colunas_validas, true)) {
    $coluna_ordem = 'id';
}

if ($direcao_ordem !== 'ASC') {
    $direcao_ordem = 'DESC';
}

$sql = 'SELECT * FROM eventos WHERE apelido = ? AND senha = ? ORDER BY `' . $coluna_ordem . '` ' . $direcao_ordem;
$stmt = mysqli_prepare($conn, $sql);
$query = false;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $apelido, $senha);
    if (mysqli_stmt_execute($stmt)) {
        $query = mysqli_stmt_get_result($stmt);
    }
}

$idMsg = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$incluido = filter_input(INPUT_GET, 'incluido', FILTER_VALIDATE_INT);
$excluido = filter_input(INPUT_GET, 'excluido', FILTER_VALIDATE_INT);
$editado = filter_input(INPUT_GET, 'editado', FILTER_VALIDATE_INT);
$erro = filter_input(INPUT_GET, 'erro', FILTER_VALIDATE_INT);
$msg = trim((string)($_GET['msg'] ?? ''));
$camposObrigatorios = trim((string)($_GET['campos'] ?? ''));

$erroConsulta = false;
$fields = [];
$linhas = [];
$hiddenColumns = array('apelido', 'senha', 'diahora', 'aviso');

// Corrige o cálculo do lastVisibleIndex para não ocultar a última coluna visível
$visibleFields = array_filter($fields, function ($field) use ($hiddenColumns) {
    return !in_array(strtolower($field->name), $hiddenColumns, true);
});
$lastVisibleIndex = count($fields) - 1;
if (count($visibleFields) > 0) {
    // Garante que todas as colunas não ocultas sejam exibidas
    $lastVisibleIndex = count($fields) - 1;
}

if ($query) {
    $fields = $query->fetch_fields();
    while ($row = mysqli_fetch_row($query)) {
        $linhas[] = $row;
    }
} else {
    $erroConsulta = true;
}

$hiddenFromEnd = 0;
$numberOfColumns = count($fields);
$lastVisibleIndex = $numberOfColumns - $hiddenFromEnd - 1;

if ($lastVisibleIndex < 0) {
    $lastVisibleIndex = -1;
}

$mensagemSucesso = '';
if ($incluido === 1) {
    $idTexto = $idMsg ? ' (id ' . $idMsg . ')' : '';
    $mensagemSucesso = 'Evento incluido com sucesso' . $idTexto . '.';
}

if ($excluido === 1) {
    $idTexto = $idMsg ? ' (id ' . $idMsg . ')' : '';
    $mensagemSucesso = 'Evento excluido com sucesso' . $idTexto . '.';
}

if ($editado === 1) {
    $idTexto = $idMsg ? ' (id ' . $idMsg . ')' : '';
    $mensagemSucesso = 'Evento editado com sucesso' . $idTexto . '.';
}

$mensagemErro = '';
if ($erro === 1) {
    $mensagemErro = 'Nao foi possivel concluir a operacao.';
    if ($msg === 'id_invalido') {
        $mensagemErro = 'ID invalido para exclusao.';
    } elseif ($msg === 'preparo_delete') {
        $mensagemErro = 'Falha ao preparar a exclusao.';
    } elseif ($msg === 'execucao_delete') {
        $mensagemErro = 'Falha ao executar a exclusao.';
    } elseif ($msg === 'registro_nao_encontrado') {
        $mensagemErro = 'Evento nao encontrado para exclusao.';
    } elseif ($msg === 'id_invalido_edicao') {
        $mensagemErro = 'ID invalido para edicao.';
    } elseif ($msg === 'preparo_edicao') {
        $mensagemErro = 'Falha ao preparar a edicao.';
    } elseif ($msg === 'execucao_edicao') {
        $mensagemErro = 'Falha ao executar a edicao.';
    } elseif ($msg === 'registro_nao_alterado') {
        $mensagemErro = 'Nenhuma alteracao foi aplicada ao evento.';
    } elseif ($msg === 'grupo_obrigatorio') {
        $mensagemErro = 'Campo Grupo e obrigatorio.';
    } elseif ($msg === 'campos_obrigatorios') {
        $mensagemErro = 'Preencha os campos obrigatorios: ' . $camposObrigatorios . '.';
    }
}

if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de eventos</title>
    <link rel="stylesheet" href="../css/menu.css" />
    <style>
        .coluna-centralizada {
            text-align: center !important;
        }

        .is-invalid {
            color: #d9534f !important;
        }

        .hero__content,
        .menu-page {
            max-width: min(110vw, 2100px);
        }

        .hero__content {
            text-align: center;
        }

        .menu-shell__header h2,
        .menu-shell__header p,
        .resumo-lista {
            text-align: left;
        }

        .menu-shell__header {
            align-items: center;
        }

        .menu-shell__header>div {
            width: 100%;
        }

        .tabela-scroll {
            overflow-x: auto;
            position: relative;
            z-index: 0;
        }

        .tabela-scroll table {
            width: 100%;
            margin-top: 0;
            table-layout: fixed;
        }


        .col-acoes {
            white-space: nowrap;
            width: 130px;
        }



        .acao-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f6ecdf;
            border: 1px solid #e2d6c8;
            text-decoration: none;
            margin-right: 0.45rem;
        }

        .acao-link img {
            max-width: 20px;
            max-height: 20px;
        }

        .valor-coluna {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .coluna-checkbox {
            text-align: center;
        }

        .coluna-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: default;
        }

        .vazio-card {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 14px;
            border: 1px solid #d9ccbb;
            background: #f8f0e4;
            color: #6b5c4d;
        }

        .resumo-lista {
            color: #6b5c4d;
            margin-bottom: 0.9rem;
        }

        .tabela-scroll th {
            text-align: center;
            position: relative;
            overflow: hidden;
            word-break: break-word;
            z-index: 1;
            padding-top: 5.5rem;
            vertical-align: bottom;
        }

        .coluna-dc {
            min-width: 90px;
            white-space: normal;
            word-break: break-word;
            text-align: center;
            overflow: hidden;
        }

        .tabela-scroll td {
            overflow: hidden;
            word-break: break-word;
        }

        .coluna-ativo {
            width: 60px;
            min-width: 60px;
            text-align: center;
        }

        .coluna-ordenavel {
            cursor: pointer;
            user-select: none;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 0.65rem;
            margin: 0 auto;
            vertical-align: bottom;
            text-align: center;
            width: auto;
        }

        .coluna-ordenavel:hover {
            text-decoration: underline;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            z-index: 2000;
        }

        .coluna-ordenavel .seta {
            font-size: 0.8em;
            margin-left: 0.2rem;
        }

        .coluna-ordenavel.ativo-asc .seta::after {
            content: ' ▲';
            color: #2d5016;
            font-weight: bold;
        }

        .coluna-ordenavel.ativo-desc .seta::after {
            content: ' ▼';
            color: #2d5016;
            font-weight: bold;
        }

        .coluna-ordenavel .tooltip-text {
            position: absolute;
            bottom: calc(100% + 0.4rem);
            left: 50%;
            transform: translateX(-50%);
            background: #2d5016;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 1.35rem;
            min-height: 50px;
            border-radius: 6px;
            font-size: 0.86em;
            font-weight: normal;
            white-space: normal;
            width: 150px;
            max-width: 150px;
            text-align: center !important;
            text-align-last: center !important;
            line-height: 1.15;
            overflow-wrap: anywhere;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
            z-index: 3000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .coluna-ordenavel .tooltip-text.tooltip-text--dc {
            width: 150px;
            max-width: 150px;
        }

        .coluna-ordenavel .tooltip-text::before {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #2d5016;
            pointer-events: none;
        }

        .coluna-ordenavel:hover .tooltip-text,
        .coluna-ordenavel:focus-visible .tooltip-text {
            opacity: 1;
            visibility: visible;
        }

        /* Remover bordas entre as colunas de recorrência semanal (semN, semD, semM) */
        .col-semN { border-right: none !important; }
        .col-semD { border-left: none !important; border-right: none !important; }
        .col-semM { border-left: none !important; }

        /* Remover bordas entre diaR e mesR */
        .col-diaR { border-right: none !important; }
        .col-mesR { border-left: none !important; }

        @media (max-width: 900px) {

            .hero__content,
            .menu-page {
                max-width: 100%;
            }

            .tabela-scroll table {
                min-width: 0;
            }
        }
    </style>
</head>

<body>

   <section class="menu-shell">
            
            <nav id="menu-h" aria-label="Menu principal">
                <ul>
                    <li><a href="../Incluir.html">(*) Incluir evento</a></li>
                    <li><a href="ListaEventosResumo.php">Lista dos eventos</a></li>
                    <li><a id="gerarExtratoBtn" href="#">Gerar planilha</a></li>
                    <li><a href="tabFeriados.php">Consulta Feriados</a></li>
                    <li><a href="tabDatas.php">Consulta Datas</a></li>
                        <li><a href="logout.php" style="color:dimgray;"
                            onclick="return confirm('Tem certeza que deseja trocar de usuário? Isso encerrará sua sessão atual.');">Trocar
                            usuário</a></li>
                </ul>
                </ul>
                <script src="../js/extrato-periodo-config.js?v=20260507"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var btn = document.getElementById('gerarExtratoBtn');
                        if (btn) {
                            btn.addEventListener('click', function (e) {
                                e.preventDefault();
                                var periodo = typeof window.eventosObterPeriodoPadraoExtrato === 'function'
                                    ? window.eventosObterPeriodoPadraoExtrato(new Date())
                                    : (function () {
                                        var hoje = new Date();
                                        return {
                                            inicio: new Date(hoje.getFullYear(), hoje.getMonth(), 1),
                                            fim: new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0)
                                        };
                                    })();
                                function formatarData(dt) {
                                    var m = String(dt.getMonth() + 1).padStart(2, '0');
                                    var d = String(dt.getDate()).padStart(2, '0');
                                    return dt.getFullYear() + '-' + m + '-' + d;
                                }
                                var dataI = formatarData(periodo.inicio);
                                var dataF = formatarData(periodo.fim);
                                var url = 'Extrato.php?DataI=' + encodeURIComponent(dataI) + '&DataF=' + encodeURIComponent(dataF);
                                window.location.href = url;
                            });
                        }
                    });
                </script>

            </nav>

            <br><br>


        </section>

    <main class="menu-page">
        <section class="menu-shell">


            <?php if ($mensagemSucesso !== ''): ?>
                <p class="periodo-status is-valid"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($mensagemErro !== ''): ?>
                <p class="periodo-status is-invalid"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($erroConsulta): ?>
                <p class="periodo-status is-invalid">Nao foi possivel carregar a lista de eventos neste momento.</p>
            <?php elseif ($lastVisibleIndex < 0): ?>
                <div class="vazio-card">Nao ha colunas disponiveis para exibicao.</div>
            <?php elseif (empty($linhas)): ?>
                <div class="vazio-card">Nenhum evento cadastrado no momento.</div>
            <?php else: ?>
                <p class="resumo-lista">Total de registros: <?php echo count($linhas); ?>.</p>
                <div class="tabela-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-acoes">Ações</th>
                                <?php foreach ($fields as $index => $field): ?>
                                    <?php if ($index > $lastVisibleIndex) {
                                        continue;
                                    } ?>
                                    <?php if (in_array(strtolower($field->name), $hiddenColumns, true) || strtolower($field->name) === 'id') {
                                        continue;
                                    } ?>
                                    <?php
                                    $titulo = $field->name;
                                    $tooltip = '';

                                    // Mapa de títulos e tooltips
                                    $descricoes = array(
                                        'evento' => array('título' => 'Evento', 'tooltip' => 'Nome do evento'),
                                        'grupo' => array('título' => 'Grupo', 'tooltip' => 'Grupo do evento'),
                                        'valorE' => array('título' => 'Valor R$', 'tooltip' => 'Valor financeiro'),
                                        'DC' => array('título' => 'DC', 'tooltip' => 'Débito ou crédito'),
                                        'diario' => array('título' => 'Diario?', 'tooltip' => 'ocorre todos os dias?'),
                                        'dataFixa' => array('título' => 'Data fixa', 'tooltip' => 'Data fixa'),
                                        'diaM' => array('título' => 'Dia do mês', 'tooltip' => 'Dia do mês'),
                                        'diaS' => array('título' => 'Dia da semana', 'tooltip' => 'Dia da semana'),
                                        'diaU' => array('título' => 'Dia útil', 'tooltip' => 'Dia útil'),
                                        
                                        'prorroga' => array('título' => 'prorroga?', 'tooltip' => 'ocorre após dia não útil'),
                                        'UPA' => array('título' => 'UPA', 'tooltip' => 'último, penúltimo ou antepenúltimo dia do mês'),

                                        'semN' => array('título' => 'Ordem da semana no mês', 'tooltip' => 'ordem da semana no mês '),
                                        'semD' => array('título' => 'Dia da semana', 'tooltip' => 'dia da semana '),
                                        'semM' => array('título' => 'Número do mês', 'tooltip' => 'número do mês '),

                                        'diaR' => array('título' => 'Dia que repete', 'tooltip' => 'dia que repete '),
                                        'mesR' => array('título' => 'Mês que repete', 'tooltip' => 'mês que repete '),
                                        'ativo' => array('título' => 'ativo?', 'tooltip' => 'ou inativo?'),

                                    );

                                    if (isset($descricoes[$field->name])) {
                                        $titulo = $descricoes[$field->name]['título'];
                                        $tooltip = $descricoes[$field->name]['tooltip'];
                                    }

                                    // Determinar a próxima direção de ordenação
                                    $proxima_direcao = 'ASC';
                                    $classe_ativa = '';
                                    if ($coluna_ordem === $field->name) {
                                        $proxima_direcao = ($direcao_ordem === 'ASC') ? 'DESC' : 'ASC';
                                        $classe_ativa = ($direcao_ordem === 'ASC') ? 'ativo-asc' : 'ativo-desc';
                                    }

                                    $classe_coluna =
                                        ($field->name === 'DC' ? 'coluna-dc' : ($field->name === 'ativo' ? 'coluna-ativo' : (in_array($field->name, ['diario', 'diaM', 'diaS', 'diaU', 'dataF', 'dataFixa', 'prorroga', 'UPA', 'semN', 'semD', 'semM', 'diaR', 'mesR']) ? 'coluna-centralizada' : ''))) . ' col-' . $field->name;
                                    $classe_tooltip = $field->name === 'DC' ? 'tooltip-text tooltip-text--dc' : 'tooltip-text';

                                    $url_ordenacao = '?ordem=' . urlencode($field->name) . '&direcao=' . urlencode($proxima_direcao);
                                    ?>
                                    <?php
                                    // Não define largura mínima, deixa o navegador ajustar automaticamente
                                    $largura_min = null;
                                    ?>
                                    <?php
                                    // Força largura de 50px para colunas específicas
                                    // Remover larguras fixas para todas as colunas
                                    if (strtolower($field->name) === 'ativo') {
                                        $style = 'style="width:100px;min-width:30px;max-width:60px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'diaR') {
                                        $style = 'style="width:80px;min-width:30px;max-width:30px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'diario') {
                                        $style = 'style="width:100px;min-width:30px;max-width:70px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'prorroga') {
                                        $style = 'style="width:110px;min-width:30px;max-width:70px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'mesR') {
                                        $style = 'style="width:60px;min-width:30px;max-width:30px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'diaS') {
                                        $style = 'style="width:80px;min-width:30px;max-width:30px;text-align:center;"';
                                    } elseif (strtolower($field->name) === 'semM') {
                                        $style = 'style="width:80px;min-width:30px;max-width:30px;text-align:center;"';
                                    } else {
                                        $style = '';
                                    }
                                    ?>
                                    <th class="<?php echo $classe_coluna; ?>" <?php echo $style; ?>>
                                        <a href="<?php echo htmlspecialchars($url_ordenacao, ENT_QUOTES, 'UTF-8'); ?>" class="coluna-ordenavel <?php echo $classe_ativa; ?>">
                                            <span><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="seta"></span>
                                            <span class="<?php echo $classe_tooltip; ?>"><?php echo htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Lógica para colorir blocos de valores iguais na coluna ordenada e aplicar a cor para toda a linha
                            $agrupadorIndex = null;
                            $cores = [
                                '#e3f2fd', // azul claro
                                '#fff3e0', // laranja claro
                                '#e8f5e9', // verde claro
                                '#fce4ec', // rosa claro
                                '#f3e5f5', // lilás claro
                                '#f9fbe7', // amarelo claro
                                '#ede7f6', // roxo claro
                                '#fbe9e7', // salmão claro
                                '#e0f2f1', // turquesa claro
                                '#f1f8e9'  // verde amarelado
                            ];
                            // Descobre o índice da coluna atualmente ordenada
                            foreach ($fields as $idx => $field) {
                                if ($field->name === $coluna_ordem) {
                                    $agrupadorIndex = $idx;
                                    break;
                                }
                            }
                            // fallback: se não encontrar, usa grupo
                            if ($agrupadorIndex === null) {
                                foreach ($fields as $idx => $field) {
                                    if (strtolower($field->name) === 'grupo') {
                                        $agrupadorIndex = $idx;
                                        break;
                                    }
                                }
                            }
                            // Garante que as linhas estejam ordenadas pelo agrupador
                            if ($agrupadorIndex !== null && $coluna_ordem !== $fields[$agrupadorIndex]->name) {
                                usort($linhas, function($a, $b) use ($agrupadorIndex) {
                                    $valA = is_string($a[$agrupadorIndex]) ? strtolower(trim($a[$agrupadorIndex])) : $a[$agrupadorIndex];
                                    $valB = is_string($b[$agrupadorIndex]) ? strtolower(trim($b[$agrupadorIndex])) : $b[$agrupadorIndex];
                                    return $valA <=> $valB;
                                });
                            }
                            $valorAnteriorNorm = null;
                            $corAtual = 0;
                            $corFundoAtual = $cores[0];
                            foreach ($linhas as $i => $row):
                                $valorAgrupador = $agrupadorIndex !== null ? $row[$agrupadorIndex] : '';
                                // Normaliza para comparação: remove espaços e ignora maiúsculas/minúsculas
                                $valorAgrupadorNorm = is_string($valorAgrupador) ? strtolower(trim($valorAgrupador)) : $valorAgrupador;
                                if ($i === 0 || $valorAgrupadorNorm !== $valorAnteriorNorm) {
                                    $corFundoAtual = $cores[$corAtual % count($cores)];
                                    $corAtual++;
                                }
                                $valorAnteriorNorm = $valorAgrupadorNorm;
                                $corFundo = $corFundoAtual;
                            ?>
                                <tr style="background: <?php echo $corFundo; ?> !important;">
                                    <td class="col-acoes">
                                        <a class="acao-link" href="editar.php?id=<?php echo urlencode((string)$row[0]); ?>" title="Editar evento">
                                            <img src="../images/editar.png" alt="Editar" />
                                        </a>
                                        <a class="acao-link" href="deletar.php?id=<?php echo urlencode((string)$row[0]); ?>" title="Excluir evento" onclick="return confirm('Confirma excluir este evento?')">
                                            <img src="../images/delete.png" alt="Excluir" />
                                        </a>
                                    </td>

                                    <?php for ($j = 0; $j <= $lastVisibleIndex; $j++): ?>
                                        <?php if (in_array(strtolower($fields[$j]->name), $hiddenColumns, true) || strtolower($fields[$j]->name) === 'id') {
                                            continue;
                                        } ?>
                                        <?php
                                        $classeColunaDado =
                                            ($fields[$j]->name === 'DC' ? 'coluna-dc' : ($fields[$j]->name === 'ativo' ? 'coluna-ativo' : (in_array($fields[$j]->name, ['diario', 'diaM', 'diaS', 'diaU', 'dataF', 'dataFixa', 'prorroga', 'UPA', 'semN', 'semD', 'semM', 'diaR', 'mesR']) ? 'coluna-centralizada' : ''))) . ' col-' . $fields[$j]->name;
                                        // Aplica a cor de fundo também na célula da coluna agrupadora
                                        $styleTd = '';
                                        if ($agrupadorIndex !== null && $j === $agrupadorIndex) {
                                            $styleTd = ' style="background: ' . $corFundo . ';"';
                                        }
                                        ?>
                                        <?php if ($fields[$j]->name === 'valorE'): ?>
                                            <td class="valor-coluna <?php echo $classeColunaDado; ?>"<?php echo $styleTd; ?>><?php echo number_format((float)$row[$j], 2, ',', '.'); ?></td>
                                        <?php elseif ($fields[$j]->name === 'dataFixa'): ?>
                                            <td class="<?php echo $classeColunaDado; ?>"<?php echo $styleTd; ?>>
                                                <?php
                                                $data = $row[$j];
                                                if (!empty($data)) {
                                                    $dt = DateTime::createFromFormat('Y-m-d', $data);
                                                    if ($dt) {
                                                        echo $dt->format('d/m/Y');
                                                    } else {
                                                        echo htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
                                                    }
                                                }
                                                ?>
                                            </td>
                                        <?php else: ?>
                                            <td class="<?php echo $classeColunaDado; ?>"<?php echo $styleTd; ?>><?php echo htmlspecialchars((string)$row[$j], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>