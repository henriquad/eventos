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
		   .tabela-scroll th,
		   .tabela-scroll td {
			   max-width: 140px;
			   min-width: 140px;
			   width: 140px;
			   text-align: center;
			   white-space: nowrap;
			   overflow-x: auto;
			   font-size: 18px;
		   }

		   .tabela-scroll tbody tr {
			   border-bottom: 3px solid #e0e7ef;
			   background: #fff;
			   transition: background 0.2s;
		   }
		   .tabela-scroll tbody tr:nth-child(even) {
			   background: #f8f0e4;
		   }
		   .tabela-scroll tbody tr:hover {
			   background: #e9e0d1;
		   }

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

	<main class="menu-page">
		<section class="menu-shell">
			<div class="menu-shell__header">
			
				
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
							<?php
							$anoAnterior = null;
							foreach ($linhas as $linha):
								$valorFeriado = $linha['feriado'] ?? '';
								$dataBr = $valorFeriado;
								$anoLinha = '';
								if (!empty($valorFeriado)) {
									$dt = DateTime::createFromFormat('Y-m-d', $valorFeriado);
									if ($dt instanceof DateTime) {
										$dataBr = $dt->format('j/n/y');
										$anoLinha = $dt->format('Y');
									} else {
										$dt2 = DateTime::createFromFormat('d/m/Y', $valorFeriado);
										if ($dt2 instanceof DateTime) {
											$dataBr = $dt2->format('j/n/y');
											$anoLinha = $dt2->format('Y');
										}
									}
								}
								if ($anoLinha !== $anoAnterior) {
									echo '<tr style="background:#e0e7ef;"><td colspan="2" style="font-weight:bold;text-align:left;padding-left:18px;">Ano ' . htmlspecialchars($anoLinha, ENT_QUOTES, 'UTF-8') . '</td></tr>';
									$anoAnterior = $anoLinha;
								}
							?>
								<tr>
									<td><?php echo htmlspecialchars($dataBr, ENT_QUOTES, 'UTF-8'); ?></td>
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