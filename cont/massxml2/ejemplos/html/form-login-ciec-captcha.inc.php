<form method="POST" class="login-form">
  <input type="hidden" name="accion" value="login_ciecc" />
  <div class="row">
  <div class='col-sm-12 col-md-6 col-md-offset-3'>
    <div class="col-sm-6 form-group">
      <label for="login-ciec-rfc">RFC</label>
      <input type="hidden" class="form-control" id="login-ciec-rfc" name="rfc" value="<?php echo $descargaCfdi->rfc_pass(0) ?>">
      <h3 title="Configure su RFC y clave Ciec en la configuración de facturación."><?php echo $descargaCfdi->rfc_pass(0) ?></h3>
      <!--<label for="login-ciec-pwd">Contraseña</label>-->
      <input type="hidden" autocomplete="new-password" class="form-control" id="login-ciec-pwd" name="pwd" value="<?php echo $descargaCfdi->rfc_pass(1) ?>">
    </div>
    <div class="col-sm-6 form-group">
      <label for="login-ciec-captcha">Captcha</label>
      <input type="text" class="form-control" autocomplete="off" id="login-ciec-captcha" name="captcha" placeholder="Ingrese captcha">
    </div>
    <div class="col-sm-6 form-group">
      <span style="padding:0;overflow:hidden;padding:0 10px;background:#fff;">
          <img style="" src="data:image/jpeg;base64,<?php echo $descargaCfdi->obtenerCaptcha(); ?>" />
        </span>
        <img title="Recargar captcha" onclick="reload()" src='../../../../pos/images/reload.png'/>
    </div>
        
      
    </div>
    <div class="col-sm-2 form-group">
      <label>&nbsp;</label><br>
      <button type="submit" class="btn btn-primary btn-block" id='iniciar_ses'>Iniciar sesión</button>
    </div>
  </div>
  <input type="hidden" name="sesion" value="<?php echo $descargaCfdi->obtenerSesion() ?>" />
  </form>
  </div>