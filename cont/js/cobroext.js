/**
 * @author Carmen Gutierrez
 */
$(document).ready(function(){
	$("#cliente,#moneda,#tipocambio,#banco,#cuentacliente,#xml").select2({
         width : "150px"
        });
       
   
});
function fechadefault(){
	if($('#idperiodo').val()<10){ fec=0+$('#idperiodo').val(); }else{ fec=$('#idperiodo').val(); }
	//$("#fecha").val($("#ejercicio").val()+'-'+fec+'-01');
	     
   $.datepicker.setDefaults($.datepicker.regional['es-MX']);
    $("#fecha").datepicker({
	 	maxDate: 365,
	 	dateFormat: 'yy-mm-dd',
        numberOfMonths: 1,
        defaultDate:$("#ejercicio").val()+'-'+fec+'-01'
        
    });
}
function checa() {  
		var req_vi = $("input[name='radio']:checked").val();
		if(req_vi==1){
			$('#xmlsube').hide();
			$('#xml').show();
		}else if(req_vi==2){
			$('#xml').hide();
			$('#xmlsube').show();

		}
    }
// function cambio(){
	// if($("#radio").val()==1){
		// $("#provision").show();
	// }else if($("#radio").val()==2){
		// alert("add");
		// $("#provision").hide();
// 		
	// }
// }

// function agregacli(){
// 	
	// var banco=$("#banco").val();
	// var cliente=$('#cliente').val();
	// var importe=$('#importe').val();
	// var concepto=$('#concepto').val();
	// var xml=$('#provision').val();
	// //alert(banco);
	// $.post('index.php?c=CaptPolizas&f=tabla',{
		// banco:banco,
		// cliente:cliente,
		// importe:importe,
		// concepto:concepto,
		// xml:xml},
		// function(respues) {
// window.location.reload();			
	// });
// }
function borra(cont){
	$.post('index.php?c=CaptPolizas&f=borra',{
		cont:cont},
		function(respues) {
window.location.reload();
	});
}
function guarda(){
		 
	var fecha=$('#fecha').val();
	if(fecha!=""){
		var fec;
		var sep=fecha.split('-');
		if($('#idperiodo').val()<10){ fec=0+$('#idperiodo').val(); }else{ fec=$('#idperiodo').val(); }
		
		if(fec==sep[1] && sep[0]==$("#ejercicio").val()){
			$("#agregaprevio").hide();
			$("#load").show();
			$.post('index.php?c=automaticasMonedaExt&f=guardaCobro',{
				fecha:fecha,
				unsolobanco:$("#unsolobanco").val(),
				numeroforma:$("#numeroformapago").val()
				},
				function(respues) {
					//alert(respues);
					var r=respues.split('-_-');
					 if(r[1]==1){
					 	$("#load").hide();
					 	$("#agregaprevio").show();
						 
						 if (confirm("Poliza generada Correctamente!. \n Desea ver la poliza?")){
						 	$.post('ajax.php?c=CaptPolizas&f=consultaultima',{},
						 		function (idpoli){ 
						 			window.location= 'index.php?c=automaticasMonedaExt&f=vercobroext';
						 			window.parent.preguntar=false;
						 			window.parent.quitartab("tb0",0,"Polizas");
						 			window.parent.agregatab('../../modulos/cont/index.php?c=CaptPolizas&f=ModificarPoliza&id='+idpoli+'&im=1','Polizas','',0);
									window.parent.preguntar=true;
									
						 		});
						 }else{
						 	window.location= 'index.php?c=automaticasMonedaExt&f=vercobroext';
						 }
					 }else if(r[1]==0){
					 	 alert("No se pueden generar polizas automaticas en el periodo 13.");
						 $("#load").hide(); $("#agregaprevio").show();
					}else if(r[1]==2){
						alert("Error al generar Poliza");
						$("#load").hide(); $("#agregaprevio").show();
					}
			});
		}else{
			
			alert("Elija una fecha acorde al periodo y ejercicio actual (mes:"+mes($('#idperiodo').val())+",año:"+$("#ejercicio").val()+")");
			 $('#fecha').css("border-color","red");
		}
	}else{
			alert("Elija una fecha para la poliza");
			$('#fecha').css("border-color","red");
		}
}
var nav4 = window.Event ? true : false; 

function valida(e) { // 1
		var key = nav4 ? e.which : e.keyCode;  
return (key <= 13 || (key >= 48 && key <= 57 || key==46)); 
	}
	

function mes(periodo){
 	var p1;
    if(periodo==1 ){ p1='Enero'; }
    if(periodo==2){ p1='Febrero'; }	
	if(periodo==3){ p1='Marzo';  }
    if(periodo==4){ p1='Abril';}
    if(periodo==5){ p1='Mayo';}
    if(periodo==6){ p1='Junio';}
    if(periodo==7){ p1='Julio';}
    if(periodo==8){ p1='Agosto'; }
    if(periodo==9){ p1='Septiembre'; }
    if(periodo==10){ p1='Octubre';  }
    if(periodo==11){ p1='Noviembre';  }
    if(periodo==12){ p1='Diciembre';}
     return p1;
}
  
  function validacuenta(){
  	if($('#cliente').val().indexOf('-') == -1){//cuenta de catalogo
  		var cliente=$('#cliente').val().split('/');
  		$.post('ajax.php?c=CaptPolizas&f=validacuenta',
  		{
  			cliente:cliente[0]
  		},function(a){
  			if(a>0){
  				$('#clientesincuenta').val(a);
  				$('#muestra').hide();
  			}else{
  				$('#muestra').show();
  				alert("El cliente no tiene una cuenta asociada agrege la cuenta para el movimiento");
  				
  			}
  		});
  	}else{$('#muestra').hide();}
  	
  } 
  function agregacuenta(){
  	
  	$('#clientesincuenta').val($('#cuentacliente').val());
  }
  function validacli(){
  	if($('#xml').val()==0 || !$('#xml').val()){
		alert('Seleccione una factura');
		return false;
	}
	if($('#tipocambio').val()==0 && $('#tipocambio2').val()=="" ){
		alert('Seleccione o Escriba un tipo de Cambio');
		return false;
	}
  	if($('#cliente').val()==0){
			alert("Elija un cliente");
			return false;
	}
	
	
  }	
function mandaasignarcuenta(){
	window.parent.agregatab("../../modulos/cont/index.php?c=Config&f=configAccounts","Asignación de Cuentas","",1647);
} 
function calculaIVAIEPS(inputallenar,cont,tipocambio){
		//alert($("#"+valor).val());
		var calculoiva = (($("#imporinput2"+cont).val())/1.16)* .16;
		$("#ivacobradoinput"+cont).val(calculoiva.toFixed(2));
		$("#ivapendienteinput"+cont).val(calculoiva.toFixed(2));
		$("#ivacobradomxn"+cont).html( ( (calculoiva).toFixed(2) * tipocambio).toFixed(2) );
		$("#ivapendientemxn"+cont).html( ( (calculoiva).toFixed(2) * tipocambio).toFixed(2));

		
		var calculoieps = (($("#imporinput2"+cont).val())/1.08)* .08;
		$("#icobradoinput"+cont).val(calculoieps.toFixed(2));
		$("#ipendienteinput"+cont).val(calculoieps.toFixed(2));
		$("#ipendientemxn"+cont).html( ( (calculoieps).toFixed(2) * tipocambio).toFixed(2) );
		$("#icobradomxn"+cont).html( ( (calculoieps).toFixed(2) * tipocambio).toFixed(2) );
		
		$("#imporinput"+cont).val($("#imporinput2"+cont).val());
		$("#imporintromxn"+cont).html( ($("#imporinput2"+cont).val() * tipocambio).toFixed(2));
		$("#imporintro2mxn"+cont).html( ($("#imporinput2"+cont).val() * tipocambio).toFixed(2));

		
	}
 function rellena(relleno,valor){
		//alert($("#"+valor).val());
		$("#"+relleno).val($("#"+valor).val());
	}
	
function consultaTipoCambio(id){
	if(id!=1){
		$("#consul").show();
		periodo = $("#inicio_mes").html().split('-');
		$.post("ajax.php?c=automaticasMonedaExt&f=consultaTipoCambioCobro",{
			idmoneda:id,
			periodot:periodo[2]+"-"+periodo[1]
		},function (resp){
			$("#consul").hide();
			window.location.reload();
		});
	}
	
}	
function cancela(){
	if(confirm("Esta seguro de eliminar los datos capturados?")){
		$.post('ajax.php?c=automaticasMonedaExt&f=cancelaCobro',{},function(){ window.location.reload();});
	}
}
function cambiaintro(){
	$("#int2").show();
	$("#tipocambio").val(0);
	$("#int").hide();
	$(".t1").hide();
	$(".t2").show();
	
}
function listadoin(){
	$("#int").show();
	$("#int2").hide();
	$(".t1").show();
	$(".t2").hide();
}
function numeros(e){
  var keynum = window.event ? window.event.keyCode : e.which;
if ((keynum == 8) || (keynum == 46))
return true;
 
return /\d/.test(String.fromCharCode(keynum));
}