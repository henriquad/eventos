<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

$conn = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
mysqli_set_charset($conn, 'utf8');

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

eventosGarantirSchema($conn);

$sql = 'SELECT * FROM eventos WHERE apelido = ? AND senha = ? ORDER BY id DESC';
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
$hiddenColumns = array('apelido', 'senha');

if ($query) {
    $fields = $query->fetch_fields();
    while ($row = mysqli_fetch_row($query)) {
        $linhas[] = $row;
    }
} else {
    $erroConsulta = true;
}

$hiddenFromEnd = 4;
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
        .hero__content,
        .menu-page {
            max-width: min(96vw, 1680px);
        }

        .hero__content {
            text-align: center;
        }

        .menu-shell__header h2,
        .menu-shell__header p,
        .resumo-lista {
            text-align: center;
        }

        .menu-shell__header {
            align-items: center;
        }

        .menu-shell__header>div {
            width: 100%;
        }

        .tabela-scroll {
            overflow-x: auto;
        }

        .tabela-scroll table {
            min-width: 1480px;
            width: 100%;
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
        }

        @media (max-width: 900px) {

            .hero__content,
            .menu-page {
                max-width: 100%;
            }

            .tabela-scroll table {
                min-width: 980px;
            }
        }
    </style>
</head>

<body>
    <header class="hero">
        <div class="hero__content">
            <p class="hero__eyebrow">Agenda financeira</p>
            <h1>Lista de eventos</h1>
            <p class="hero__text">Consulte, edite ou exclua eventos cadastrados para manter o planejamento atualizado.</p>
        </div>
    </header>

    <main class="menu-page">
        <section class="menu-shell">
            <div class="menu-shell__header">
                <div>
                    <h2>Eventos cadastrados</h2>
                    <p>Use as açoes na primeira coluna para editar ou excluir um registro.</p>
                </div>
                <a class="menu-shell__logout" style="width: 170px;" href="../menu.html">Voltar ao menu</a>
            </div>

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
                                <th class="col-acoes">Açoes</th>
                                <?php foreach ($fields as $index => $field): ?>
                                    <?php if ($index > $lastVisibleIndex) {
                                        continue;
                                    } ?>
                                    <?php if (in_array($field->name, $hiddenColumns, true)) {
                                        continue;
                                    } ?>
                                    <?php
                                    $titulo = $field->name;
                                    if ($index === 0) {
                                        $titulo = 'id';
                                    } elseif ($index === 1) {
                                        $titulo = 'Evento';
                                    } elseif ($index === 3) {
                                        $titulo = 'Valor';
                                    }
                                    ?>
                                    <th><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linhas as $row): ?>
                                <tr>
                                    <td class="col-acoes">
                                        <a class="acao-link" href="editar.php?id=<?php echo urlencode((string)$row[0]); ?>" title="Editar evento">
                                            <img src="../images/edit_icon-36.png" alt="Editar" />
                                        </a>
                                        <a class="acao-link" href="deletar.php?id=<?php echo urlencode((string)$row[0]); ?>" title="Excluir evento" onclick="return confirm('Confirma excluir este evento?')">
                                            <img src="../images/excluir.gif" alt="Excluir" />
                                        </a>
                                    </td>

                                    <?php for ($j = 0; $j <= $lastVisibleIndex; $j++): ?>
                                        <?php if (in_array($fields[$j]->name, $hiddenColumns, true)) {
                                            continue;
                                        } ?>
                                        <?php if ($j === 3): ?>
                                            <td class="valor-coluna"><?php echo number_format((float)$row[$j], 2, ',', '.'); ?></td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars((string)$row[$j], ENT_QUOTES, 'UTF-8'); ?></td>
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