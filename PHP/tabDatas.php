<?php
$conn = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
mysqli_set_charset($conn, 'utf8');

$sql = "SELECT * FROM datas WHERE CAST(IFNULL(NULLIF(TRIM(DiaUtil), ''), '0') AS UNSIGNED) <> 0";
$query = mysqli_query($conn, $sql);

$erroConsulta = false;
$campos = [];
$linhas = [];

function normalizarDataEntrada($valor)
{
	$valor = trim((string)$valor);
	if ($valor === '') {
		return null;
	}

	$formatoIso = DateTime::createFromFormat('Y-m-d', $valor);
	if ($formatoIso instanceof DateTime) {
		$formatoIso->setTime(0, 0, 0);
		return $formatoIso;
	}

	$formatoBr = DateTime::createFromFormat('d/m/Y', $valor);
	if ($formatoBr instanceof DateTime) {
		$formatoBr->setTime(0, 0, 0);
		return $formatoBr;
	}

	return null;
}

function extrairDataLinha(array $linha)
{
	$ano = isset($linha['A']) ? (int)$linha['A'] : 0;
	$mes = isset($linha['M']) ? (int)$linha['M'] : 0;
	$dia = isset($linha['DataMes']) ? (int)$linha['DataMes'] : 0;

	if ($ano > 0 && $mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31 && checkdate($mes, $dia, $ano)) {
		return DateTime::createFromFormat('Y-n-j', $ano . '-' . $mes . '-' . $dia);
	}

	$rawData = trim((string)($linha['DataMes'] ?? ''));
	if ($rawData !== '') {
		$iso = DateTime::createFromFormat('Y-m-d', $rawData);
		if ($iso instanceof DateTime) {
			return $iso;
		}

		$br = DateTime::createFromFormat('d/m/Y', $rawData);
		if ($br instanceof DateTime) {
			return $br;
		}
	}

	return null;
}

$filtroDataIniRaw = (string)($_GET['dataIni'] ?? '');
$filtroDataFimRaw = (string)($_GET['dataFim'] ?? '');
$filtroDataIni = normalizarDataEntrada($filtroDataIniRaw);
$filtroDataFim = normalizarDataEntrada($filtroDataFimRaw);

$perPage = (int)($_GET['perPage'] ?? 50);
$opcoesPorPagina = [25, 50, 100, 200];
if (!in_array($perPage, $opcoesPorPagina, true)) {
	$perPage = 50;
}

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) {
	$page = 1;
}

if ($query) {
	$fields = mysqli_fetch_fields($query);
	foreach ($fields as $field) {
		$campos[] = $field->name;
	}

	while ($row = mysqli_fetch_assoc($query)) {
		$linhas[] = $row;
	}
} else {
	$erroConsulta = true;
}

$temFiltroPeriodo = ($filtroDataIni instanceof DateTime) || ($filtroDataFim instanceof DateTime);
$linhasFiltradas = $linhas;

if ($temFiltroPeriodo) {
	$linhasFiltradas = [];
	foreach ($linhas as $linha) {
		$dataLinha = extrairDataLinha($linha);
		if (!$dataLinha instanceof DateTime) {
			continue;
		}

		$dataLinha->setTime(0, 0, 0);

		if ($filtroDataIni instanceof DateTime && $dataLinha < $filtroDataIni) {
			continue;
		}

		if ($filtroDataFim instanceof DateTime && $dataLinha > $filtroDataFim) {
			continue;
		}

		$linhasFiltradas[] = $linha;
	}
}

$totalRegistros = count($linhasFiltradas);
$totalPaginas = max(1, (int)ceil($totalRegistros / $perPage));
if ($page > $totalPaginas) {
	$page = $totalPaginas;
}

$offset = ($page - 1) * $perPage;
$linhasPaginadas = array_slice($linhasFiltradas, $offset, $perPage);

$baseParams = [
	'dataIni' => $filtroDataIniRaw,
	'dataFim' => $filtroDataFimRaw,
	'perPage' => (string)$perPage,
];

$primeiroRegistro = $totalRegistros > 0 ? $offset + 1 : 0;
$ultimoRegistro = $totalRegistros > 0 ? min($offset + count($linhasPaginadas), $totalRegistros) : 0;

mysqli_close($conn);
?>
<!doctype html>
<html lang="pt-br">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Datas</title>
	<link rel="stylesheet" href="../css/menu.css" />
	<style>
		   .tabela-scroll table th,
		   .tabela-scroll table td {
			   text-align: center;
			   vertical-align: middle;
			   max-width: 40px;
			   min-width: 40px;
			   width: 40px;
			   overflow-x: auto;
			   white-space: nowrap;
			   font-size: 18px;
		   }

		/* Linhas alternadas para todas as tabelas */
		table tbody tr:nth-child(even):not(.no-alt),
		.tabela-scroll tbody tr:nth-child(even) {
			background: #f8f0e4 !important;
		}

		table tbody tr:nth-child(odd):not(.no-alt),
		.tabela-scroll tbody tr:nth-child(odd) {
			background: #fffdf9 !important;
		}

		/* Melhoria visual para tabela de datas */
		.tabela-scroll {
			overflow-x: auto;
			max-width: 80vw;
		}

		.tabela-scroll table {
			min-width: 460px;
			width: 100%;
			border-collapse: collapse;
			background: #fffdf9;
		}

		.tabela-scroll th {
			background: #f4e9d9;
			color: #2f2419;
			position: sticky;
			top: 0;
			z-index: 2;
			box-shadow: 0 2px 4px rgba(44, 34, 19, 0.04);
			padding-top: 1.4rem;
			padding-bottom: 1.4rem;
			min-height: 0;
			height: 96px;
		}


		.tabela-scroll tbody tr:nth-child(even) {
			background: #f8f0e4;
		}

		.tabela-scroll tbody tr:nth-child(odd) {
			background: #fffdf9;
		}

		.tabela-scroll tbody tr:hover {
			background: #e9e0d1;
			transition: background 0.2s;
		}

		.tabela-scroll td {
			font-size: 1.01em;
		}

		@media (max-width: 500px) {
			.tabela-scroll table {
				min-width: 500px;
			}

			.tabela-scroll th,
			.tabela-scroll td {
				padding: 0.45rem 0.5rem;
				font-size: 0.98em;
			}
		}

		table,
		th,
		td {
			border: 1.5px solid #bcae98 !important;
			border-collapse: collapse !important;
		}

		.tabela-scroll {
			overflow-x: auto;
		}

		.tabela-scroll table {
			min-width: 440px;
		}

		.tabela-scroll td,
		.tabela-scroll th {
			white-space: nowrap;
		}

		.erro-card {
			margin-top: 1rem;
			padding: 1rem;
			border-radius: 14px;
			border: 1px solid #d9a49e;
			background: #fff0ec;
			color: #8a2b1f;
		}

		.filtros-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 0.9rem;
			margin: 1rem 0;
		}

		.filtro-campo {
			display: flex;
			flex-direction: column;
			gap: 0.35rem;
		}

		.filtro-campo input,
		.filtro-campo select {
			padding: 0.75rem 0.85rem;
			border: 1px solid #cfbfae;
			border-radius: 12px;
			font-size: 22px;
			background: #fffefa;
			color: #2f2419;
		}

		.filtros-acoes {
			display: flex;
			gap: 0.65rem;
			flex-wrap: wrap;
			margin-bottom: 1rem;
		}

		.filtro-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 140px;
			padding: 0.7rem 1rem;
			border-radius: 999px;
			border: 0;
			background: #214c78;
			color: #fff;
			font-weight: 700;
			cursor: pointer;
			text-decoration: none;
		}

		.filtro-btn--ghost {
			background: #efe4d5;
			color: #16324f;
		}

		.resultado-resumo {
			margin-bottom: 0.75rem;
			color: #6b5c4d;
		}

		.paginacao {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			margin-top: 1rem;
			flex-wrap: wrap;
		}

		.paginacao-links {
			display: flex;
			gap: 0.5rem;
			align-items: center;
			flex-wrap: wrap;
		}

		.paginacao-link {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.5rem 0.8rem;
			border: 1px solid #d9ccbb;
			border-radius: 999px;
			text-decoration: none;
			color: #16324f;
			background: #fffdf9;
		}

		.paginacao-link.is-atual {
			background: #214c78;
			color: #fff;
			border-color: #214c78;
		}
	
		.coluna-com-tooltip {
			position: relative;
			display: inline-block;
			cursor: help;
		}
		.coluna-com-tooltip .tooltip-text {
			visibility: hidden;
			width: max-content;
			max-width: 200px;
			background: #333;
			color: #fff;
			text-align: left;
			border-radius: 6px;
			padding: 7px 12px;
			position: absolute;
			z-index: 9999;
			bottom: 120%;
			left: 50%;
			transform: translateX(-50%);
			opacity: 0;
			transition: opacity 0.2s;
			font-size: 0.82em;
			pointer-events: none;
			box-shadow: 0 2px 8px rgba(0,0,0,0.18);
			white-space: pre-line !important;
			word-break: break-word !important;
		}

		.tabela-scroll th, .tabela-scroll tr, .tabela-scroll thead {
			overflow: visible !important;
		}

		/* Garante contexto de empilhamento para tooltips */
		.tabela-scroll {
			position: relative;
			z-index: 1;
		}

		.coluna-com-tooltip {
			position: relative;
			z-index: 10000;
		}
		.coluna-com-tooltip:hover .tooltip-text,
		.coluna-com-tooltip:focus .tooltip-text {
			visibility: visible;
			opacity: 1;
			pointer-events: auto;
		}
	</style>
</head>

<body>
	<section class="menu-shell">
            
            <nav id="menu-h" aria-label="Menu principal">
                <ul>
                    <li><a href="../Incluir.html">(*) Incluir evento</a></li>
                    <li><a href="ListaEventos.php">Lista de eventos</a></li>
                    <li><a id="gerarExtratoBtn" href="./">Gerar planilha</a></li>
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
			

			<form method="get" action="tabDatas.php">
				<div class="filtros-grid">
					<div class="filtro-campo">
						<label for="dataIni">Data inicial</label>
						<input type="date" id="dataIni" name="dataIni" value="<?php echo htmlspecialchars((string)$filtroDataIniRaw, ENT_QUOTES, 'UTF-8'); ?>" />
					</div>
					<div class="filtro-campo">
						<label for="dataFim">Data final</label>
						<input type="date" id="dataFim" name="dataFim" value="<?php echo htmlspecialchars((string)$filtroDataFimRaw, ENT_QUOTES, 'UTF-8'); ?>" />
					</div>
					<div class="filtro-campo">
						<label for="perPage">Registros por pagina</label>
						<select id="perPage" name="perPage">
							<?php foreach ($opcoesPorPagina as $opcao): ?>
								<option value="<?php echo $opcao; ?>" <?php echo $opcao === $perPage ? 'selected' : ''; ?>><?php echo $opcao; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="filtros-acoes">
					<button type="submit" class="filtro-btn">Aplicar filtros</button>
					<a class="filtro-btn filtro-btn--ghost" href="tabDatas.php">Limpar filtros</a>
				</div>
			</form>

			<?php if ($erroConsulta): ?>
				<div class="erro-card">Nao foi possivel carregar a tabela de datas neste momento.</div>
			<?php elseif (empty($campos)): ?>
				<p class="periodo-status">Nao existem colunas disponiveis para exibicao.</p>
			<?php elseif (empty($linhasPaginadas)): ?>
				<p class="periodo-status">Nao existem registros com DiaUtil diferente de zero para os filtros informados.</p>
			<?php else: ?>
				<p class="resultado-resumo">
					Exibindo <?php echo $primeiroRegistro; ?> a <?php echo $ultimoRegistro; ?> de <?php echo $totalRegistros; ?> registro(s).
				</p>

				<div class="tabela-scroll">
					<table>
						<thead>
							<tr>
								<?php
								$descricoesDatas = [
									'DataMes' => ['título' => 'Data',   'tooltip' => "dd-mm-aaaa"],
									'N'       => ['título' => 'Dia',    'tooltip' => "dia\n(1-31)"],
									'Sem'     => ['título' => 'Sem',    'tooltip' => "dia da\nsemana"],
									'M'       => ['título' => 'Mês',    'tooltip' => "mês\n(1-12)"],
									'A'       => ['título' => 'Ano',    'tooltip' => "ano com\n4 dígitos"],
									'Util'    => ['título' => 'Útil',   'tooltip' => "1=útil\n0=não útil"],
									'Feriado'    => ['título' => 'Feriado',   'tooltip' => "Feriados\nnacionais"],
									'DiaUtil' => ['título' => 'Dia útil', 'tooltip' => "dia útil\nno mês"],
									'SemN'    => ['título' => 'semN',  'tooltip' => "ordem da\nsemana"],
									'UPAm'    => ['título' => 'UPA',   'tooltip' => "último, penúltimo\nou antepenúltimo"],
									'U'       => ['título' => 'Úteis',    'tooltip' => "dias úteis\nno mês"],									
								];
								?>
								<?php foreach ($campos as $campo): ?>
									<?php
									$titulo = $campo;
									$tooltip = '';
									if (isset($descricoesDatas[$campo])) {
										$titulo = $descricoesDatas[$campo]['título'];
										$tooltip = $descricoesDatas[$campo]['tooltip'];
									}
									   $style = 'style="max-width: 40px; min-width: 50px; width: 40px;"';
									?>
									<th <?php echo $style; ?>>
										<?php if ($tooltip !== ''): ?>
											<div class="coluna-com-tooltip">
												<span><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></span>
												<span class="tooltip-text"><?php echo htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?></span>
											</div>
										<?php else: ?>
											<?php echo htmlspecialchars((string)$campo, ENT_QUOTES, 'UTF-8'); ?>
										<?php endif; ?>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php
							$anoAnterior = null;
							foreach ($linhasPaginadas as $linha):
								// Descobre o ano da linha
								$anoLinha = '';
								$valorData = $linha['DataMes'] ?? '';
								if (!empty($valorData)) {
									$dt = DateTime::createFromFormat('Y-m-d', $valorData);
									if ($dt instanceof DateTime) {
										$anoLinha = $dt->format('Y');
									} else {
										$dt2 = DateTime::createFromFormat('d/m/Y', $valorData);
										if ($dt2 instanceof DateTime) {
											$anoLinha = $dt2->format('Y');
										}
									}
								}
								if ($anoLinha !== $anoAnterior) {
									echo '<tr style="background:#e0e7ef;"><td colspan="' . count($campos) . '" style="font-weight:bold;text-align:left;padding-left:18px;">Ano ' . htmlspecialchars($anoLinha, ENT_QUOTES, 'UTF-8') . '</td></tr>';
									$anoAnterior = $anoLinha;
								}
							?>
								<tr>
									<?php foreach ($campos as $campo): ?>
										<?php if ($campo === 'DataMes'): ?>
											   <td style="max-width: 40px; min-width: 40px; width: 40px; overflow-x: auto; white-space: nowrap;">
												   <?php
													   $valorData = $linha[$campo] ?? '';
													   $dataBr = $valorData;
													   if (!empty($valorData)) {
														   $dt = DateTime::createFromFormat('Y-m-d', $valorData);
														   if ($dt instanceof DateTime) {
															   $dataBr = $dt->format('j/n/y');
														   } else {
															   // Tenta converter de outros formatos
															   $dt2 = DateTime::createFromFormat('d/m/Y', $valorData);
															   if ($dt2 instanceof DateTime) {
																   $dataBr = $dt2->format('j/n/y');
															   }
														   }
													   }
													   echo htmlspecialchars($dataBr, ENT_QUOTES, 'UTF-8');
												   ?>
											   </td>
										<?php elseif ($campo === 'DiaUtil'): ?>
											   <td style="max-width: 40px; min-width: 40px; width: 40px; overflow-x: auto; white-space: nowrap;">
												   <?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8') . 'º'; ?>
											   </td>
										<?php elseif (strcasecmp($campo, 'Feriado') === 0): ?>
											   <td style="max-width: 40px; min-width: 40px; width: 40px; overflow-x: auto; white-space: nowrap;">
												   <?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
											   </td>
										<?php elseif ($campo === 'UPAm'): ?>
											   <td style="max-width: 40px; min-width: 40px; width: 40px; overflow-x: auto; white-space: nowrap;">
												   <?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
											   </td>
										<?php else: ?>
											<td>
												<?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
											</td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="paginacao">
					<div>Pagina <?php echo $page; ?> de <?php echo $totalPaginas; ?></div>
					<div class="paginacao-links">
						<?php
						$prevPage = max(1, $page - 1);
						$nextPage = min($totalPaginas, $page + 1);

						$prevParams = $baseParams;
						$prevParams['page'] = (string)$prevPage;

						$nextParams = $baseParams;
						$nextParams['page'] = (string)$nextPage;
						?>
						<a class="paginacao-link" href="tabDatas.php?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
						<a class="paginacao-link is-atual" href="#"><?php echo $page; ?></a>
						<a class="paginacao-link" href="tabDatas.php?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES, 'UTF-8'); ?>">Proxima</a>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</main>
</body>

</html>