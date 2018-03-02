<?php
$tipo = null;
switch (empty($_GET['tipo']) ? null : $_GET['tipo']) {
	case 'ciecc':
	default:
		$tipo = 'ciecc';
		require dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'DescargaMasivaCfdi.php';
		$descargaCfdi = new DescargaMasivaCfdi;
		break;
	
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Descarga Masiva de CFDIs</title>
		<link rel="stylesheet" href="../../../css/bootstrap/bootstrap.css">
		<link href="css/styles.css" rel="stylesheet">
		<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	<body>
	<div class="row">
		<div class="col-sm-4"><a class="btn btn-default" href="javascript:backToDigitalWHouse();" role="button"><span class="glyphicon glyphicon-arrow-left"></span> Regresar</a></div>
		<div class="col-sm-8"><h3 style='color:#005A8F;'>Descarga Masiva de XML's desde el SAT</h3></div>
	</div>
<?php
	require 'form-login-ciec-captcha.inc.php';	
?>
				<div class="tablas-resultados">
					<div class="overlay"></div>
					<ul class="nav nav-tabs" role="tablist">
						<li role="presentation" class="active"><a href="#recibidos" aria-controls="recibidos" role="tab" data-toggle="tab">Recibidos</a></li>
						<li role="presentation"><a href="#emitidos" aria-controls="emitidos" role="tab" data-toggle="tab">Emitidos</a></li>
					</ul>
					<div class="tab-content">
					    <div role="tabpanel" class="tab-pane active" id="recibidos">
					    	<?php require 'form-recibidos.inc.php' ?>
							<form method="POST" class="descarga-form">
								<input type="hidden" name="accion" value="descargar-recibidos" />
								<input type="hidden" name="sesion" class="sesion-ipt" />
								<div style="overflow:auto">
									<table class="table table-hover table-condensed" id="tabla-recibidos">
										<thead>
											<tr>
												<th class="text-center">XML</th>
												<!--<th class="text-center">Acuse</th>-->
												<th>Efecto</th>
												<th>Razón Social</th>
												<th>RFC</th>
												<th>Estado</th>
												<th>Folio Fiscal</th>
												<th>Emisión</th>
												<th>Total</th>
												<th>Certificación</th>
												<th>Cancelación</th>
												<th>PAC</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
								<div class="text-right">
									<button type="submit" class="btn btn-primary" id='descargar_sel_reci'>Descargar seleccionados</button>
								</div>
							</form>
					    </div>
					    <div role="tabpanel" class="tab-pane" id="emitidos">
							<?php require 'form-emitidos2.inc.php' ?>
							<form method="POST" class="descarga-form">
								<input type="hidden" name="accion" value="descargar-emitidos" />
								<input type="hidden" name="sesion" class="sesion-ipt" />
								<div style="overflow:auto">
									<table class="table table-hover table-condensed" id="tabla-emitidos">
										<thead>
											<tr>
												<th class="text-center">XML</th>
												<!--<th class="text-center">Acuse</th>-->
												<th>Efecto</th>
												<th>Razón Social</th>
												<th>RFC</th>
												<th>Estado</th>
												<th>Folio Fiscal</th>
												<th>Emisión</th>
												<th>Total</th>
												<th>Certificación</th>
												<th>PAC</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
								<div class="text-right">
									<button type="submit" class="btn btn-primary" id='descargar_sel_emi'>Descargar seleccionados</button>
								</div>
							</form>
						</div>
					</div>
				</div>

		<script src="js/jquery-3.1.1.min.js"></script>
		<script src="../../../js/bootstrap/bootstrap.min.js"></script>
		<script src="js/code.js"></script>
	</body>
</html>
<script language="javascript">
function reload()
{
	location.reload();
}
function backToDigitalWHouse()
{
    window.history.back();
}
</script>

