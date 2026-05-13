<?php

require_once __DIR__ . '/auth_session.php';
eventosExigirLogin();
require_once __DIR__ . '/eventos_schema.php';

include_once 'Conectar_BaseH.php';

$apelido = eventosApelidoSessao();
$senha = eventosSenhaSessao();

eventosGarantirSchema($dbcon);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function campoSelecionado($campo, $valor)
{
    $texto = trim((string)$valor);

    if ($texto === '' || $texto === '0' || $texto === '0000-00-00') {
        return false;
    }

    if ($campo === 'diario') {
        return strtolower($texto) === 'sim';
    }

    if ($campo === 'mesR') {
        return $texto !== '' && $texto !== '0';
    }

    if ($campo === 'semM') {
        return $texto !== '' && $texto !== '0';
    }

    if (in_array($campo, array('diaM', 'diaU', 'UPA', 'semN', 'diaR'), true)) {
        return (int)$texto > 0;
    }

    if (in_array($campo, array('diaS', 'semD', 'ativo', 'dataFixa'), true)) {
        return true;
    }

    return false;
}

function formatarCampoSelecionado($campo, $valor)
{
    $labels = array(
        'diario' => 'diário?',
        'dataFixa' => '',
        'diaM' => 'dia do mês',
        'diaS' => 'dia da semana',
        'diaU' => 'dia útil',
        'UPA' => 'UPA',
        'semN' => 'ordem da semana',
        'semD' => 'dia da semana',
        'semM' => 'mês da semana',
        'diaR' => 'dia que repete',
        'mesR' => 'mês que repete',
    );

    $texto = trim((string)$valor);

    if ($campo === 'UPA') {
        $mapaUpa = array(
            '7' => 'antepenúltimo dia útil',
            '8' => 'penúltimo dia útil',
            '9' => 'último dia útil',
        );
        $texto = $mapaUpa[$texto] ?? $texto;
        return $texto;
    }

    if ($campo === 'dataFixa' && $texto !== '' && $texto !== '0000-00-00') {
        $dt = DateTime::createFromFormat('Y-m-d', $texto);
        if ($dt) {
            $texto = $dt->format('d/m/Y');
        }
    }

    return ($labels[$campo] ?? $campo) . ' ' . $texto;
}

function montarResumoSelecionados($row)
{
    $campos = array('diario', 'dataFixa', 'diaM', 'diaS', 'diaU', 'UPA');
    $selecionados = array();

    foreach ($campos as $campo) {
        $valor = $row[$campo] ?? '';
        if (campoSelecionado($campo, $valor)) {
            $selecionados[] = formatarCampoSelecionado($campo, $valor);
        }
    }

    $semN = trim((string)($row['semN'] ?? ''));
    $semD = trim((string)($row['semD'] ?? ''));
    $semM = trim((string)($row['semM'] ?? ''));
    $semNSelecionado = campoSelecionado('semN', $semN);
    $semDSelecionado = campoSelecionado('semD', $semD);
    $semMSelecionado = campoSelecionado('semM', $semM);

    if ($semNSelecionado || $semDSelecionado || $semMSelecionado) {
        $textoSem = 
             ($semNSelecionado ? $semN."º" : '-') 
            . ' /  '
            . ($semDSelecionado ? $semD : '-')
            . ' /  '
            . ($semMSelecionado ? $semM : '-');
        $selecionados[] = $textoSem;
    }

    $diaR = trim((string)($row['diaR'] ?? ''));
    $mesR = trim((string)($row['mesR'] ?? ''));
    $diaRSelecionado = campoSelecionado('diaR', $diaR);
    $mesRSelecionado = campoSelecionado('mesR', $mesR);

    if ($diaRSelecionado && $mesRSelecionado) {
        $selecionados[] =   $diaR . ' / ' . $mesR;
    } elseif ($diaRSelecionado) {
        $selecionados[] = formatarCampoSelecionado('diaR', $diaR);
    } elseif ($mesRSelecionado) {
        $selecionados[] = formatarCampoSelecionado('mesR', $mesR);
    }

    if (empty($selecionados)) {
        return '-';
    }

    return implode(' | ', $selecionados);
}

$sql = 'SELECT id, evento, grupo, valorE, DC, prorroga, ativo, diario, dataFixa, diaM, diaS, diaU, UPA, semN, semD, semM, diaR, mesR FROM eventos WHERE apelido = ? AND senha = ? ORDER BY grupo ASC, evento ASC';
$stmt = mysqli_prepare($dbcon, $sql);

$erroConsulta = false;
$linhas = array();

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $apelido, $senha);
    if (mysqli_stmt_execute($stmt)) {
        $query = mysqli_stmt_get_result($stmt);
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $linhas[] = $row;
            }
        } else {
            $erroConsulta = true;
        }
    } else {
        $erroConsulta = true;
    }
    mysqli_stmt_close($stmt);
} else {
    $erroConsulta = true;
}

mysqli_close($dbcon);

$idMsg = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$incluido = filter_input(INPUT_GET, 'incluido', FILTER_VALIDATE_INT);
$excluido = filter_input(INPUT_GET, 'excluido', FILTER_VALIDATE_INT);
$editado = filter_input(INPUT_GET, 'editado', FILTER_VALIDATE_INT);
$erro = filter_input(INPUT_GET, 'erro', FILTER_VALIDATE_INT);

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
}
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista dos eventos</title>
    <link rel="stylesheet" href="../css/menu.css" />
    <style>
        .menu-page,
        .hero__content {
            max-width: min(100vw, 1500px);
        }

        .resumo-lista {
            color: #6b5c4d;
            margin-bottom: 0.9rem;
            font-size: 15px;
        }

        .vazio-card,
        .erro-card {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 14px;
            border: 1px solid #d9ccbb;
            background: #f8f0e4;
            color: #6b5c4d;
        }

        .erro-card {
            border-color: #d9a49e;
            background: #fff0ec;
            color: #8a2b1f;
        }

        .tabela-scroll {
            overflow-x: auto;
        }

        .tabela-scroll table {
            width: 100%;
            margin-top: 0;
            table-layout: auto;
        }

        th,
        td {
            text-align: left;
            vertical-align: middle;
            font-size: 18px;
        }

        .valor-coluna {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .centro {
            text-align: center;
            white-space: nowrap;
        }

        .col-acoes {
            width: 130px;
            white-space: nowrap;
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

        .periodo-status.is-valid {
            color: #2d7a3e;
        }

        .periodo-status.is-invalid {
            color: #8a2b1f;
        }

        .col-selecionados {
            min-width: 360px;
            white-space: normal;
            line-height: 1.35;
        }

        th.col-selecionados {
            text-align: center;
        }

        td.col-selecionados {
            text-align: center;
        }

        h2 {
            font-size: 28px;
        }

        body {
            font-size: 18px;
        }
    </style>
</head>

<body>
    <section class="menu-shell">
        <nav id="menu-h" aria-label="Menu principal">
            <ul>
                <li><a href="../Incluir.html">(*) Incluir evento</a></li>
              
                
                <li><a id="gerarExtratoBtn" href="#">Gerar planilha</a></li>
                <li><a href="tabFeriados.php">Consulta Feriados</a></li>
                <li><a href="tabDatas.php">Consulta Datas</a></li>
                <li><a href="logout.php" style="color:dimgray;" onclick="return confirm('Tem certeza que deseja trocar de usuario? Isso encerrara sua sessao atual.');">Trocar usuario</a></li>
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
            <h2>Lista dos eventos</h2>

            <?php if ($mensagemSucesso !== ''): ?>
                <p class="periodo-status is-valid"><?php echo h($mensagemSucesso); ?></p>
            <?php endif; ?>

            <?php if ($mensagemErro !== ''): ?>
                <p class="periodo-status is-invalid"><?php echo h($mensagemErro); ?></p>
            <?php endif; ?>

            <?php if ($erroConsulta): ?>
                <div class="erro-card">Nao foi possivel carregar a lista de eventos neste momento.</div>
            <?php elseif (empty($linhas)): ?>
                <div class="vazio-card">Nenhum evento cadastrado no momento.</div>
            <?php else: ?>
                <p class="resumo-lista">Total de registros: <?php echo count($linhas); ?>.</p>

                <div class="tabela-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-acoes">Acoes</th>
                                <th>Evento</th>
                                <th>Grupo</th>
                                <th class="valor-coluna">Valor</th>
                                <th class="centro">DC</th>
                                <th class="centro">Prorroga</th>
                                <th class="centro">Ativo</th>
                                <th class="col-selecionados">Recorrências selecionadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linhas as $row): ?>
                                <tr>
                                    <td class="col-acoes">
                                        <a class="acao-link" href="editar.php?id=<?php echo urlencode((string)$row['id']); ?>" title="Editar evento">
                                            <img src="../images/editar.png" alt="Editar" />
                                        </a>
                                        <a class="acao-link" href="deletar.php?id=<?php echo urlencode((string)$row['id']); ?>" title="Excluir evento" onclick="return confirm('Confirma excluir este evento?')">
                                            <img src="../images/delete.png" alt="Excluir" />
                                        </a>
                                    </td>
                                    <td><?php echo h($row['evento']); ?></td>
                                    <td><?php echo h($row['grupo']); ?></td>
                                    <td class="valor-coluna"><?php echo number_format((float)$row['valorE'], 2, ',', '.'); ?></td>
                                    <td class="centro"><?php echo h($row['DC']); ?></td>
                                    <td class="centro"><?php echo h($row['prorroga']); ?></td>
                                    <td class="centro"><?php echo h($row['ativo']); ?></td>
                                    <td class="col-selecionados"><?php echo h(montarResumoSelecionados($row)); ?></td>
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