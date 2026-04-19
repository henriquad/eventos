<?php
$conn = new mysqli('MYSQL8002.site4now.net', 'a90b7e_baseh', 'Amanti_#9', 'db_a90b7e_baseh');
mysqli_set_charset($conn, 'utf8');

$sql = 'SELECT * FROM feriados';
$query = mysqli_query($conn, $sql);

$erroConsulta = false;
$linhas = [];

if ($query) {
	while ($row = mysqli_fetch_assoc($query)) {
		$linhas[] = $row;
	}
} else {
	$erroConsulta = true;
}

mysqli_close($conn);
?>
<!doctype html>
<html lang="pt-br">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Feriados</title>
	<link rel="stylesheet" href="../css/menu.css" />
	<style>
		.tabela-scroll {
			overflow-x: auto;
		}

		.tabela-scroll table {
			min-width: 520px;
		}

		.erro-card {
			margin-top: 1rem;
			padding: 1rem;
			border-radius: 14px;
			border: 1px solid #d9a49e;
			background: #fff0ec;
			color: #8a2b1f;
		}
	</style>
</head>

<body>
	<header class="hero">
		<div class="hero__content">
			<p class="hero__eyebrow">Agenda financeira</p>
			<h1>Tabela de feriados</h1>
			<p class="hero__text">Consulte os feriados cadastrados para planejar prorrogacoes e antecipacoes de eventos.</p>
		</div>
	</header>

	<main class="menu-page">
		<section class="menu-shell">
			<div class="menu-shell__header">
				<div>
					<h2>Feriados cadastrados</h2>
					<p>Referencia para calculo de eventos em dias uteis e excecoes do calendario.</p>
				</div>
				<a class="menu-shell__logout" href="../menu.html">Voltar ao menu</a>
			</div>

			<?php if ($erroConsulta): ?>
				<div class="erro-card">Nao foi possivel carregar a tabela de feriados neste momento.</div>
			<?php elseif (empty($linhas)): ?>
				<p class="periodo-status">Nenhum feriado cadastrado no momento.</p>
			<?php else: ?>
				<div class="tabela-scroll">
					<table>
						<thead>
							<tr>
								<th>Feriado</th>
								<th>Motivo</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($linhas as $linha): ?>
								<tr>
									<td><?php echo htmlspecialchars((string)($linha['feriado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string)($linha['motivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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