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
			max-width: 100vw;
		}

		.tabela-scroll table {
			min-width: 1200px;
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
			padding-top: 0.25rem;
			padding-bottom: 0.25rem;
			min-height: 0;
			height: 32px;
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

		@media (max-width: 900px) {
			.tabela-scroll table {
				min-width: 700px;
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
			min-width: 1400px;
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
			font-size: 0.98rem;
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
	</style>
</head>

<body>
	<header class="hero">
		<div class="hero__content">
			
			<h1>Calendario para eventos</h1>
			<p class="hero__text">Visualize a base de datas de apoio para recorrencias, prazos e organizacao do planejamento.</p>
			<a class="menu-shell__logout" href="../menu.html">Voltar ao menu</a>
		</div>
	</header>

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
								<?php foreach ($campos as $campo): ?>
									<?php if ($campo === 'DataMes'): ?>
										<th style="max-width: 80px; min-width: 70px; width: 80px;">DataMes</th>
									<?php else: ?>
										<th><?php echo htmlspecialchars((string)$campo, ENT_QUOTES, 'UTF-8'); ?></th>
									<?php endif; ?>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($linhasPaginadas as $linha): ?>
								<tr>
									<?php foreach ($campos as $campo): ?>
										<?php if ($campo === 'DataMes'): ?>
											<td style="max-width: 80px; min-width: 70px; width: 80px; overflow-x: auto; white-space: nowrap;">
												<?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
											</td>
										<?php else: ?>
											<td><?php echo htmlspecialchars((string)($linha[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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