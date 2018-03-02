<?php
/**
 * Librería para descarga masiva de CFDI emitidos y recibidos
 * del servidor del SAT.
 *
 * @author  Noel Miranda <noelmrnd@gmail.com>
 * @version 3.1.1
 */

class DescargaMasivaCfdi {
    const URL_CFDICONT = 'https://cfdicontribuyentes.accesscontrol.windows.net/v2/wsfederation';
    const URL_CFDIAU = 'https://cfdiau.sat.gob.mx/nidp/app';
    const URL_PORTAL_CFDI = 'https://portalcfdi.facturaelectronica.sat.gob.mx/';
    const URL_CONSULTA_RECEPTOR = 'https://portalcfdi.facturaelectronica.sat.gob.mx/ConsultaReceptor.aspx';
    const URL_CONSULTA_EMISOR = 'https://portalcfdi.facturaelectronica.sat.gob.mx/ConsultaEmisor.aspx';

    const HEADER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36';
    const HEADER_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';

    private $cookie;
    private $sesion;


    public function __construct() {
        // ocultar "Warnings" por errores de HTML en las paginas del SAT
        libxml_use_internal_errors(true);
    }

    /**
     * Realiza en inicio de sesión en el portal del SAT
     * mediante la FIEL
     * @param UtilCertificado $certificado objeto con informacion
     * del certificado FIEL
     * @return boolean resultado del inicio de sesion
     */
    public function iniciarSesionFiel($cert){
        $url = 'https://cfdiau.sat.gob.mx/nidp/app/login?id=SATx509Custom&sid=0&option=credential&sid=0';
        $headers = array(
            'Referer' => self::URL_CFDIAU, // requerido
            'User-Agent' => self::HEADER_USER_AGENT, // requerido
            'Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8'
        );

        // 1
        $respuesta = $this->getGetResponse(self::URL_PORTAL_CFDI);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 2
        $respuesta = $this->getPostResponse($url, null, $headers);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 3
        $document = new DOMDocument();
        $document->loadHTML( $respuesta->getBody() );
        if(!$document) {
            return false;
        }
        $post = array();
        $form = $document->getElementById('certform');
        foreach (array('input','select') as $element) {
            foreach ($form->getElementsByTagName($element) as $val) {
                $name = $val->getAttribute('name');
                if(!empty($name)){
                    $post[$name] = utf8_decode($val->getAttribute('value'));
                }
            }
        }
        if(!$post) {
            return false;
        }
        $guid = $post['guid'];
        $serie = $cert->getNumeroCertificado();
        $rfc = $cert->getRFC();
        $validez = $cert->getRangoValidez();
        $co = $guid . '|' . $rfc . '|' . $serie;
        $laFirma = base64_encode($cert->firmarCadena($co, OPENSSL_ALGO_SHA1));
        $token = base64_encode(base64_encode($co) . '#' . $laFirma);
        $post['token'] = $token;
        $post['fert'] = gmdate('ymdHis', $validez['to']).'Z';
        $respuesta = $this->getPostResponse($url, $post, $headers);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 4
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }
        $respuesta = $this->getPostResponse(self::URL_CFDICONT, $post, $headers);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 5
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }
        $respuesta = $this->getPostResponse(self::URL_PORTAL_CFDI, $post, $headers);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 6
        $respuesta = $this->getGetResponse(self::URL_PORTAL_CFDI);
        if($respuesta->getStatusCode() != 200){
            return false;
        }elseif(strpos($respuesta->getBody(), $rfc) === false){
            return false;
        }else{
            return true;
        }
    }

    /**
     * Realiza en inicio de sesión en el portal del SAT
     * mediante la CIEC
     * @param string $rfc RFC
     * @param string $contrasena Contraseña
     * @return boolean resultado del inicio de sesion
     */
    public function iniciarSesionCiec($rfc, $contrasena){
        $rfc = strtoupper($rfc);
        $url = 'https://cfdiau.sat.gob.mx/nidp/app/login?id=4&sid=0&option=credential';

        // 1
        $respuesta = $this->getGetResponse($url);
        if($respuesta->getStatusCode() != 200 || !$respuesta->getBody()){
            return false;
        }

        // 2
        $respuesta = $this->getPostResponse(
            $url,
            array(
                'option'=>'credential',
                'Ecom_User_ID'=>$rfc,
                'Ecom_Password'=>$contrasena,
                'submit'=>'Enviar'
            )
        );
        if($respuesta->getStatusCode() != 200 || !$respuesta->getBody()){
            return false;
        }

        // 3
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }
        $respuesta = $this->getPostResponse(self::URL_CFDICONT, $post);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 4
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }
        $respuesta = $this->getPostResponse(self::URL_PORTAL_CFDI, $post);
        if($respuesta->getStatusCode() != 200){
            return false;
        }elseif(strpos($respuesta->getBody(), $rfc) === false){
            return false;
        }else{
            return true;
        }
    }

    /**
     * Realiza en inicio de sesión en el portal del SAT
     * mediante la CIEC con Captcha
     * @param string $rfc RFC
     * @param string $contrasena Contraseña
     * @param string $captcha caracteres del captcha
     * @return boolean resultado del inicio de sesion
     */
    public function iniciarSesionCiecCaptcha($rfc, $contrasena, $captcha){
        $rfc = strtoupper($rfc);

        // 1
        $respuesta = $this->getPostResponse(
            'https://cfdiau.sat.gob.mx/nidp/wsfed/ep?id=SATUPCFDiCon&sid=0&option=credential&sid=0',
            array(
                'option'=>'credential',
                'Ecom_User_ID'=>$rfc,
                'Ecom_Password'=>$contrasena,
                'jcaptcha'=>$captcha,
                'submit'=>'Enviar'
            )
        );
        if($respuesta->getStatusCode() != 200 || !$respuesta->getBody()){
            return false;
        }
        
        // 2
        $respuesta = $this->getGetResponse('https://cfdiau.sat.gob.mx/nidp/wsfed/ep?sid=0');
        if($respuesta->getStatusCode() != 200 || !$respuesta->getBody()){
            return false;
        }
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }

        // 3
        $respuesta = $this->getPostResponse(self::URL_CFDICONT, $post);
        if($respuesta->getStatusCode() != 200){
            return false;
        }
        $post = $this->getFormData( $respuesta->getBody() );
        if(!$post) {
            return false;
        }

        // 4
        $respuesta = $this->getPostResponse(self::URL_PORTAL_CFDI, $post);
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 5
        $respuesta = $this->getGetResponse(self::URL_PORTAL_CFDI);
        if($respuesta->getStatusCode() != 200){
            return false;
        }elseif(strpos($respuesta->getBody(), $rfc) === false){
            return false;
        }else{
            return true;
        }
    }

    /**
     * Obtiene la imagen del captcha requerido para
     * el inicio de sesión con CIEC/Captcha
     * @return string contenido de la imagen del captcha en Base 64
     */
    public function obtenerCaptcha() {
        // 1
        $respuesta = $this->getGetResponse('https://portalcfdi.facturaelectronica.sat.gob.mx');
        if($respuesta->getStatusCode() != 200 || !$respuesta->getBody()){
            return false;
        }

        // 2
        $respuesta = $this->getPostResponse('https://cfdiau.sat.gob.mx/nidp/wsfed/ep?id=SATUPCFDiCon&sid=0&option=credential&sid=0');
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        // 3
        $respuesta = $this->getGetResponse('https://cfdiau.sat.gob.mx/nidp/jcaptcha.jpg');
        if($respuesta->getStatusCode() != 200){
            return false;
        }

        return base64_encode($respuesta->getBody());
    }

    /**
     * Permite obtener los CFDI emitidos/recibidos utilizando
     * las opciones que ofrece el portal del SAT
     * @param object $filtros configuración de los filtros a utilizar
     * @return array objetos XmlInfo de los XML encontrados
     */
    public function buscar($filtros) {
        if(get_class($filtros) == 'BusquedaEmitidos') {
            $url = self::URL_CONSULTA_EMISOR;
            $modulo = 'emitidos';
        }elseif(get_class($filtros) == 'BusquedaRecibidos') {
            $url = self::URL_CONSULTA_RECEPTOR;
            $modulo = 'recibidos';
        }else{
            return false;
        }

        $post = $this->obtenerDatosForm($url);
        if(!$post){
            return false;
        }

        $encabezados = self::headerPortalCfdi();
        $respuesta = $this->getPostResponse($url, $post, $encabezados);
        $html = $respuesta->getBody();
        $post = $filtros->obtenerFormularioAjax($post, $html);
        $respuesta = $this->getPostResponse($url, $post, $encabezados);
        $html = $respuesta->getBody();
        $objects = $this->getXmlObjects($html, $modulo);
        
        return empty($objects)
            ? null
            : $objects;
    }

    /**
     * Devuelve el XML del CFDI como string
     * @param string $url del XML
     * @return string datos del XML, o NULL
     */
    public function obtenerXml($url){
        if(!empty($url)) {
            $xml = $this->obtenerArchivoString($url);
            if(!empty($xml)) {
                return $xml;
            }
        }

        return null;
    }

    /**
     * Guarda el XML del CFDI en la ruta especificada
     * @param string $url del XML
     * @param string $dir ubicación del archivo
     * @param string $nombre nombre del archivo (sin extensión)
     */
    public function guardarXml($url, $dir, $nombre){
        if(empty($url)) {
            return false;
        }

        $resource = fopen($dir.DIRECTORY_SEPARATOR.$nombre.'.xml', 'w');

        $saved = false;
        $str = $this->obtenerArchivoString($url);
        if(!empty($str)) {
            $bytes = fwrite($resource, $str);
            $saved = ($bytes !== false);
            fclose($resource);
        }

        return $saved;
    }

    /**
     * Guarda el acuse de cancelación de un XML en la ruta especificada
     * @param string $url del acuse
     * @param string $dir ubicación del archivo
     * @param string $nombre nombre del archivo sin, incluir extensión
     */
    public function guardarAcuse($url, $dir, $nombre){
        if(empty($url)) {
            return false;
        }

        $resource = fopen($dir.DIRECTORY_SEPARATOR.$nombre.'.pdf', 'w');

        $saved = false;
        $str = $this->obtenerArchivoString($url);
        if(!empty($str)) {
            $bytes = fwrite($resource, $str);
            $saved = ($bytes !== false);
            fclose($resource);
        }

        return $saved;
    }

    /**
     * Obtiene los datos de la sesión actual
     * @return string datos de la sesion actual
     */
    public function obtenerSesion(){
        return base64_encode(
            json_encode(RespuestaCurl::getCookie())
        );
    }

    /**
     * Restaura una sesion previa
     * @param string $sesion datos de una sesion anterior
     */
    public function restaurarSesion($sesion){
        if(!empty($sesion)) {
            return RespuestaCurl::setCookie(
                json_decode(base64_decode($sesion), true)
            );
        }

        return false;
    }

    private function getPostResponse($url, $post=null, $encabezados=null){
        return RespuestaCurl::request($url, $post, $encabezados);
    }
    
    private function getGetResponse($url, $encabezados=null){
        return RespuestaCurl::request($url, null, $encabezados);
    }

    private function getXmlObjects($html, $modulo){
        $document = new DOMDocument();
        $document->loadHTML($html);
        if(!$document) return null;
        $xp = new DOMXPath($document);
        $trs = $xp->query('//table[@id="ctl00_MainContent_tblResult"]/tr');
        if(!$trs) return null;
        $xmls = array();
        foreach ($trs as $trElement) {
            if($xml = XmlInfo::fromHtmlElement($trElement, $modulo)){
                $xmls[] = $xml;
            }
        }
        return $xmls;
    }

    private function getFormData($html){
        $document = new DOMDocument();
        $document->loadHTML($html);
        if(!$document) return null;
        $form = $document->getElementsByTagName('form')->item(0);
        if(!$form) return null;
        $post = array();
        foreach (array('input','select') as $element) {
            foreach ($form->getElementsByTagName($element) as $val) {
                $name = $val->getAttribute('name');
                if(!empty($name)){
                    $post[$name] = utf8_decode($val->getAttribute('value'));
                }
            }
        }
        return $post;
    }

    private function obtenerDatosForm($url){
        $respuesta = $this->getGetResponse($url);
        $html = $respuesta->getBody();
        $reqOk = $respuesta->getStatusCode() == 200
            && strpos($html, 'Portal Contribuyentes CFDI | Buscar CFDI') !== false;
        if($reqOk){
            $post = $this->getFormData($html);
            if($post) {
                unset(
                    $post['seleccionador'],
                    $post['ctl00$MainContent$BtnDescargar'],
                    $post['ctl00$MainContent$BtnCancelar'],
                    $post['ctl00$MainContent$BtnImprimir']
                );
                if($respuesta->getStatusCode() == 200){
                    return $post;
                }
            }
        }

        return null;
    }

    private function obtenerArchivoString($url){
        if(empty($url)) return false;

        $respuesta = RespuestaCurl::request($url, null, null);
        if($respuesta->getStatusCode() == 200) {
            return $respuesta->getBody();
        }else{
            return null;
        }
    }

    private static function headerPortalCfdi(){
        $arr = array(
            'Referer' => self::URL_PORTAL_CFDI, // requerido
            'User-Agent' => self::HEADER_USER_AGENT, // requerido
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
        );

        // ajax
        $arr['X-MicrosoftAjax'] = 'Delta=true';
        $arr['X-Requested-With'] = 'XMLHttpRequest';

        return $arr;
    }

    public function rfc_pass($n)
    {
        require '../../../../../netwarelog/webconfig.php';
        $conn = new mysqli($servidor,$usuariobd,$clavebd, $bd);

        //Buscar el RFC de la instancia
        $rfc = $conn->query("SELECT rfc, pass_ciec FROM pvt_configura_facturacion WHERE id = 1");
        $rfc = $rfc->fetch_assoc();
        $conn->close();
        if($n)
            $return = $rfc["pass_ciec"];
        else
            $return = $rfc["rfc"];
        return $return;
    }
}

class BusquedaRecibidos {
    const ESTADO_TODOS       = '-1';
    const ESTADO_VIGENTE     =  '1';
    const ESTADO_CANCELADO   =  '0';

    private $anio;
    private $mes;
    private $dia             =  '0';
    private $hora_inicial    =  '0';
    private $minuto_inicial  =  '0';
    private $segundo_inicial =  '0';
    private $hora_final      = '23';
    private $minuto_final    = '59';
    private $segundo_final   = '59';
    private $rfc_emisor      = '';
    private $folio_fiscal    = '';
    private $tipo            = '-1';
    private $tipoBusqueda    = 'fecha';
    private $estado          = self::ESTADO_TODOS;


    public function __construct() {
        $this->anio = date('Y');
        $this->mes = date('n');
    }

    /**
     * Permite indicar el estado de los CFDI a buscar
     * @param string $estado
     */
    public function establecerEstado($estado){
        $this->estado = (string)$estado;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la fecha de búsqueda
     * @param int $anio año a 4 dígitos
     * @param int $mes mes del 1 a 12
     * @param int $dia día del 1 al 31. Si no se especifica,
     * no se tomará en cuenta el día al hacer la búsqueda
     */
    public function establecerFecha($anio, $mes, $dia=null){
        $this->anio = (string)$anio;
        $this->mes = ltrim((string)$mes, '0');
        if($dia == null) {
            $this->dia = '0';
        }else{
            $this->dia = str_pad($dia, 2, '0', STR_PAD_LEFT);
        }
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la hora inicial de búsqueda
     * @param int $hora hora en formato de 24 horas (0-23)
     * @param int $minuto minuto del 0 al 59
     * @param int $segundo segundo del 0 al 59
     */
    public function establecerHoraInicial($hora='0', $minuto='0', $segundo='0'){
        $this->hora_inicial = (string)$hora;
        $this->minuto_inicial = (string)$minuto;
        $this->segundo_inicial = (string)$segundo;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la hora final de búsqueda
     * @param int $hora hora en formato de 24 horas (0-23)
     * @param int $minuto minuto del 0 al 59
     * @param int $segundo segundo del 0 al 59
     */
    public function establecerHoraFinal($hora='23', $minuto='59', $segundo='59'){
        $this->hora_final = (string)$hora;
        $this->minuto_final = (string)$minuto;
        $this->segundo_final = (string)$segundo;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite establecer el RFC del emisor
     * @param string $rfc RFC del emisor
     */
    public function establecerRfcEmisor($rfc){
        $this->rfc_emisor = $rfc_emisor;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite establecer el UUID
     * @param string $uuid el UUID
     */
    public function establecerFolioFiscal($uuid){
        $this->folio_fiscal = $uuid;
        $this->setTipoBusqueda('folio');
    }

    public function obtenerFormulario(){
        return array(
            '__ASYNCPOST' =>'true',
            '__EVENTARGUMENT' => '',
            '__EVENTTARGET' => '',
            '__LASTFOCUS' => '',
            'ctl00$MainContent$BtnBusqueda' => 'Buscar CFDI',
            'ctl00$MainContent$CldFecha$DdlAnio' => $this->anio,
            'ctl00$MainContent$CldFecha$DdlDia' => $this->dia,
            'ctl00$MainContent$CldFecha$DdlHora' => $this->hora_inicial,
            'ctl00$MainContent$CldFecha$DdlHoraFin' => $this->hora_final,
            'ctl00$MainContent$CldFecha$DdlMes' => $this->mes,
            'ctl00$MainContent$CldFecha$DdlMinuto' => $this->minuto_inicial,
            'ctl00$MainContent$CldFecha$DdlMinutoFin' => $this->minuto_final,
            'ctl00$MainContent$CldFecha$DdlSegundo' => $this->segundo_inicial,
            'ctl00$MainContent$CldFecha$DdlSegundoFin' => $this->segundo_final,
            'ctl00$MainContent$DdlEstadoComprobante' => $this->estado,
            'ctl00$MainContent$TxtRfcReceptor' => $this->rfc_emisor,
            'ctl00$MainContent$TxtUUID' => $this->folio_fiscal,
            'ctl00$MainContent$ddlComplementos' => $this->tipo,
            'ctl00$MainContent$hfInicialBool' => 'false',
            'ctl00$ScriptManager1' =>
                'ctl00$MainContent$UpnlBusqueda|ctl00$MainContent$BtnBusqueda',
            'ctl00$MainContent$FiltroCentral' =>
                ($this->tipoBusqueda == 'fecha') ? 'RdoFechas' : 'RdoFolioFiscal'
        );
    }

    public function obtenerFormularioAjax($post, $fuente){
        $valores = explode('|', $fuente);
        $validos = array(
            'EVENTTARGET',
            '__EVENTARGUMENT',
            '__LASTFOCUS',
            '__VIEWSTATE'
        );
        $valCount = count($valores);
        $items = array();
        for ($i=0; $i < $valCount; $i++) { 
            $item = $valores[$i];
            if(in_array($item, $validos)) {
                $items[$item] = $valores[$i+1];
            }
        }

        return array_merge(
            array_merge($post, $this->obtenerFormulario()),
            $items
        );
    }

    private function setTipoBusqueda($tipo){
        $this->tipoBusqueda = $tipo;
        if($tipo == 'fecha'){
            $this->folio_fiscal = '';
        }
    }
}

class BusquedaEmitidos {
    const ESTADO_TODOS       = '-1';
    const ESTADO_VIGENTE     =  '1';
    const ESTADO_CANCELADO   =  '0';

    private $fecha_inicial;
    private $hora_inicial    =  '0';
    private $minuto_inicial  =  '0';
    private $segundo_inicial =  '0';
    private $fecha_final;
    private $hora_final      = '23';
    private $minuto_final    = '59';
    private $segundo_final   = '59';
    private $rfc_receptor    = '';
    private $folio_fiscal    = '';
    private $tipo            = '-1';
    private $tipoBusqueda    = 'fecha';
    private $estado          = self::ESTADO_TODOS;


    public function __construct() {
        $this->hfInicial = date('Y');
        $this->hfFinal = date('n');
    }

    /**
     * Permite indicar el estado de los CFDI a buscar
     * @param string $estado
     */
    public function establecerEstado($estado){
        $this->estado = (string)$estado;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la fecha inicial de búsqueda
     * @param int $anio año a 4 dígitos
     * @param int $mes mes del 1 a 12
     * @param int $dia día del 1 al 31
     */
    public function establecerFechaInicial($anio, $mes, $dia){
        $this->hfInicial = (string)$anio;
        $this->fecha_inicial = 
            str_pad($dia, 2, '0', STR_PAD_LEFT) . '/' .
            str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' .
            (string)$anio;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la fecha final de búsqueda
     * @param int $anio año a 4 dígitos
     * @param int $mes mes del 1 a 12
     * @param int $dia día del 1 al 31
     */
    public function establecerFechaFinal($anio, $mes, $dia){
        $this->hfFinal = (string)$anio;
        $this->fecha_final =
            str_pad($dia, 2, '0', STR_PAD_LEFT) . '/' .
            str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' .
            (string)$anio;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la hora inicial de búsqueda
     * @param int $hora hora en formato de 24 horas (0-23)
     * @param int $minuto minuto del 0 al 59
     * @param int $segundo segundo del 0 al 59
     */
    public function establecerHoraInicial($hora='0', $minuto='0', $segundo='0'){
        $this->hora_inicial = (string)$hora;
        $this->minuto_inicial = (string)$minuto;
        $this->segundo_inicial = (string)$segundo;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite indicar la hora final de búsqueda
     * @param int $hora hora en formato de 24 horas (0-23)
     * @param int $minuto minuto del 0 al 59
     * @param int $segundo segundo del 0 al 59
     */
    public function establecerHoraFinal($hora='23', $minuto='59', $segundo='59'){
        $this->hora_final = (string)$hora;
        $this->minuto_final = (string)$minuto;
        $this->segundo_final = (string)$segundo;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite establecer el RFC del receptor
     * @param string $rfc RFC del emisor
     */
    public function establecerRfcReceptor($rfc){
        $this->rfc_receptor = $rfc;
        $this->setTipoBusqueda('fecha');
    }

    /**
     * Permite establecer el UUID
     * @param string $uuid el UUID
     */
    public function establecerFolioFiscal($uuid){
        $this->folio_fiscal = $uuid;
        $this->setTipoBusqueda('folio');
    }

    public function obtenerFormulario(){
        return array(
            '__ASYNCPOST' =>'true',
            '__EVENTARGUMENT' => '',
            '__EVENTTARGET' => '',
            '__LASTFOCUS' => '',
            'ctl00$MainContent$BtnBusqueda' => 'Buscar CFDI',
            
            'ctl00$MainContent$hfInicial' => $this->hfInicial,
            'ctl00$MainContent$hfInicialBool' => 'false',
            'ctl00$MainContent$CldFechaInicial2$Calendario_text' => $this->fecha_inicial,
            'ctl00$MainContent$CldFechaInicial2$DdlHora' => $this->hora_inicial,
            'ctl00$MainContent$CldFechaInicial2$DdlMinuto' => $this->minuto_inicial,
            'ctl00$MainContent$CldFechaInicial2$DdlSegundo' => $this->segundo_inicial,

            'ctl00$MainContent$hfFinal' => $this->hfFinal,
            'ctl00$MainContent$hfFinalBool' => 'false',
            'ctl00$MainContent$CldFechaFinal2$Calendario_text' => $this->fecha_final,
            'ctl00$MainContent$CldFechaFinal2$DdlHora' => $this->hora_final,
            'ctl00$MainContent$CldFechaFinal2$DdlMinuto' => $this->minuto_final,
            'ctl00$MainContent$CldFechaFinal2$DdlSegundo' => $this->segundo_final,
            
            'ctl00$MainContent$DdlEstadoComprobante' => $this->estado,
            'ctl00$MainContent$TxtRfcReceptor' => $this->rfc_receptor,
            'ctl00$MainContent$TxtUUID' => $this->folio_fiscal,
            'ctl00$MainContent$ddlComplementos' => $this->tipo,
            
            

            'ctl00$ScriptManager1' =>
                'ctl00$MainContent$UpnlBusqueda|ctl00$MainContent$BtnBusqueda',
            'ctl00$MainContent$FiltroCentral' =>
                ($this->tipoBusqueda == 'fecha') ? 'RdoFechas' : 'RdoFolioFiscal'
        );
    }

    public function obtenerFormularioAjax($post, $fuente){
        $valores = explode('|', $fuente);
        $validos = array(
            'EVENTTARGET',
            '__EVENTARGUMENT',
            '__LASTFOCUS',
            '__VIEWSTATE'
        );
        $valCount = count($valores);
        $items = array();
        for ($i=0; $i < $valCount; $i++) { 
            $item = $valores[$i];
            if(in_array($item, $validos)){
                $items[$item] = $valores[$i+1];
            }
        }

        return array_merge(
            array_merge(
                $post,
                $this->obtenerFormulario()
            ),
            $items
        );
    }

    private function setTipoBusqueda($tipo){
        $this->tipoBusqueda = $tipo;
        if($tipo == 'fecha'){
            $this->folio_fiscal = '';
        }
    }
}

class XmlInfo {
    public $urlDescargaXml;
    public $urlDescargaAcuse;
    public $folioFiscal;
    public $emisorRfc;
    public $emisorNombre;
    public $receptorRfc;
    public $receptorNombre;
    public $fechaEmision;
    public $fechaCertificacion;
    public $pacCertifico;
    public $total;
    public $efecto;
    public $estado;
    public $fechaCancelacion;

    /**
     * @deprecated 3.0.0 Utilice la variable $urlDescargaAcuse
     */
    public $urlAcuseXml;


    public function esVigente(){
        return $this->estado === 'Vigente';
    }
    public function esCancelado(){
        return $this->estado === 'Cancelado';
    }

    public static function fromHtmlElement($trElement, $modulo){
        $tds = $trElement->getElementsByTagName('td');

        if($tds->length == 0) {
            return null;
        }

        $xml = new self;
        $xml->folioFiscal = $tds->item(1)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->emisorRfc = $tds->item(2)->getElementsByTagName('span')->item(0)->nodeValue;
        //$xml->emisorNombre = utf8_decode($tds->item(3)->getElementsByTagName('span')->item(0)->nodeValue);
        $xml->emisorNombre = $tds->item(3)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->receptorRfc = $tds->item(4)->getElementsByTagName('span')->item(0)->nodeValue;
        //$xml->receptorNombre = utf8_decode($tds->item(5)->getElementsByTagName('span')->item(0)->nodeValue);
        $xml->receptorNombre = $tds->item(5)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->fechaEmision = $tds->item(6)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->fechaCertificacion = $tds->item(7)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->pacCertifico = $tds->item(8)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->total = $tds->item(9)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->efecto = $tds->item(10)->getElementsByTagName('span')->item(0)->nodeValue;
        $xml->estado = $tds->item(11)->getElementsByTagName('span')->item(0)->nodeValue;

        if($modulo == 'recibidos') {
            $xml->fechaCancelacion = $tds->item(12)->getElementsByTagName('span')->item(0)->nodeValue;
        }

        foreach ($tds->item(0)->getElementsByTagName('img') as $imgElement) {
            $onclick = $imgElement->getAttribute('onclick');
            if(strpos($onclick, 'RecuperaCfdi.aspx') !== false) {
                $xml->urlDescargaXml = DescargaMasivaCfdi::URL_PORTAL_CFDI . str_replace(
                    array('return AccionCfdi(\'','\',\'Recuperacion\');'),
                    '',
                    $onclick
                );
            }elseif(strpos($onclick, 'AcuseCancelacion.aspx') !== false) {
                $xml->urlDescargaAcuse = DescargaMasivaCfdi::URL_PORTAL_CFDI . str_replace(
                    array('AccionCfdi(\'','\',\'Acuse\');'),
                    '',
                    $onclick
                );
                $xml->urlAcuseXml = $xml->urlDescargaAcuse;
            }
        }
        return $xml;
    }
}

class RespuestaCurl {
    private $respuesta;
    private static $cookie = array();
    public static $defaultOptions = array(
        CURLOPT_ENCODING       => "UTF-8",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_CONNECTTIMEOUT => 120,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_MAXREDIRS      => 10,
        CURLINFO_HEADER_OUT    => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    );


    public function __construct($respuesta){
        $this->respuesta = $respuesta;
    }

    public static function request( $url, $post=null, $headers=null, $useCookies=true ){
        $options = self::$defaultOptions;
        $options[CURLOPT_URL] = $url;

        if($useCookies){
            if($cookie = self::getCookieString()){
                $options[CURLOPT_COOKIE] = $cookie;
            }
        }

        if($post){
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post);
        }else{
            $options[CURLOPT_CUSTOMREQUEST] = 'GET';
        }

        if(!empty($headers)){
            $postStr = array();
            foreach ($headers as $key => $value) {
                $postStr[] = $key.': '.$value;
            }
            $options[CURLOPT_HTTPHEADER] = $postStr;
        }

        $ch = curl_init();
        curl_setopt_array( $ch, $options );

        $rawContent = curl_exec( $ch );
        $err        = curl_errno( $ch );
        $errmsg     = curl_error( $ch );
        $data       = curl_getinfo( $ch );
        $multi      = curl_multi_getcontent( $ch );
        curl_close( $ch );

        $headerContent = substr($rawContent, 0, $data['header_size']);
        $content = trim(str_replace($headerContent, '', $rawContent));

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerContent, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            $pos = strpos($item, '=');
            $cookies[ substr($item, 0, $pos) ] = substr($item, $pos+1);
        }
        self::$cookie = array_merge(self::$cookie, $cookies);

        // $data['errno']   = $err;
        // $data['errmsg']  = $errmsg;
        // $data['headers'] = $headerContent;
        $data['content'] = $content;
        $data['cookies'] = $cookies;

        return new self($data);
    }

    public static function setCookie($cookie){
        self::$cookie = $cookie;
        return true;
    }

    public static function getCookie(){
        return self::$cookie;
    }

    public function getStatusCode(){
        return $this->respuesta['http_code'];
    }

    public function getBody(){
        return $this->respuesta['content'];
    }

    public static function getCookieString(){
        if(!empty(self::$cookie)){
            $str = '';
            foreach (self::$cookie as $key => $value) {
                $str .= $key.'='.$value.'; ';
            }
            $str = rtrim($str, '; ');
            return $str;
        }
        return '';
    }
}

class MultiCurl {
    private $_curl_version;
    private $_maxConcurrent = 0;    //max. number of simultaneous connections allowed
    private $_options       = array();   //shared cURL options
    private $_headers       = array();   //shared cURL request headers
    private $_callback      = null; //default callback
    private $_timeout       = 5000; //all requests must be completed by this time
    public $requests        = array();   //request_queue


    function __construct($max_concurrent = 10) {
        $this->setMaxConcurrent($max_concurrent);
        $v = curl_version();
        $this->_curl_version = $v['version'];
    }

    public function setMaxConcurrent($max_requests) {
        if($max_requests > 0) {
            $this->_maxConcurrent = $max_requests;
        }
    }

    public function setOptions(array $options) {
        $this->_options = $options;
    }

    public function setHeaders(array $headers) {
        if(is_array($headers) && count($headers)) {
            $this->_headers = $headers;
        }
    }

    public function setCallback(callable $callback) {
        $this->_callback = $callback;
    }

    public function setTimeout($timeout) { //in milliseconds
        if($timeout > 0) {
            $this->_timeout = $timeout;
        }
    }

    //Add a request to the request queue
    public function addRequest($url, $user_data = null) { //Add to request queue
        $this->requests[] = array(
            'url' => $url,
            'user_data' => $user_data
        );
        return count($this->requests) - 1; //return request number/index
    }

    //Reset request queue
    public function reset() {
        $this->requests = array();
    }

    //Execute the request queue
    public function execute() {
        //the request map that maps the request queue to request curl handles
        $requests_map = array();
        $multi_handle = curl_multi_init();
        $num_outstanding = 0;
        //start processing the initial request queue
        $num_initial_requests = min($this->_maxConcurrent, count($this->requests));
        for($i = 0; $i < $num_initial_requests; $i++) {
            $this->initRequest($i, $multi_handle, $requests_map);
            $num_outstanding++;
        }
        do{
            do{
                $mh_status = curl_multi_exec($multi_handle, $active);
            } while($mh_status == CURLM_CALL_MULTI_PERFORM);
            if($mh_status != CURLM_OK) {
                break;
            }
            //a request is just completed, find out which one
            while($completed = curl_multi_info_read($multi_handle)) {
                $this->processRequest($completed, $multi_handle, $requests_map);
                $num_outstanding--;
                //try to add/start a new requests to the request queue
                while(
                    $num_outstanding < $this->_maxConcurrent && //under the limit
                    $i < count($this->requests) && isset($this->requests[$i]) // requests left
                ) {
                    $this->initRequest($i, $multi_handle, $requests_map);
                    $num_outstanding++;
                    $i++;
                }
            }
            usleep(15); //save CPU cycles, prevent continuous checking
        } while ($active || count($requests_map)); //End do-while
        $this->reset();
        curl_multi_close($multi_handle);
    }

    //Build individual cURL options for a request
    private function buildOptions(array $request) {
        $url = $request['url'];
        $options = $this->_options;
        $headers = $this->_headers;
        //the below will overide the corresponding default or individual options
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_NOSIGNAL] = 1;
        if(version_compare($this->_curl_version, '7.16.2') >= 0) {
            $options[CURLOPT_CONNECTTIMEOUT_MS] = $this->_timeout;
            $options[CURLOPT_TIMEOUT_MS] = $this->_timeout;
            unset($options[CURLOPT_CONNECTTIMEOUT]);
            unset($options[CURLOPT_TIMEOUT]);
        } else {
            $options[CURLOPT_CONNECTTIMEOUT] = round($this->_timeout / 1000);
            $options[CURLOPT_TIMEOUT] = round($this->_timeout / 1000);
            unset($options[CURLOPT_CONNECTTIMEOUT_MS]);
            unset($options[CURLOPT_TIMEOUT_MS]);
        }
        if($url) {
            $options[CURLOPT_URL] = $url;
        }
        if($headers) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        return $options;
    }
    
    private function initRequest($request_num, $multi_handle, &$requests_map) {
        $request =& $this->requests[$request_num];
        $ch = curl_init();
        $options = $this->buildOptions($request);
        $request['options_set'] = $options; //merged options
        $opts_set = curl_setopt_array($ch, $options);
        if(!$opts_set) {
            echo 'options not set';
            exit;
        }
        curl_multi_add_handle($multi_handle, $ch);
        //add curl handle of a new request to the request map
        $ch_hash = (string) $ch;
        $requests_map[$ch_hash] = $request_num;
    }
    
    private function processRequest($completed, $multi_handle, array &$requests_map) {
        $ch = $completed['handle'];
        $ch_hash = (string) $ch;
        $request =& $this->requests[$requests_map[$ch_hash]]; //map handler to request index to get request info
        $request_info = curl_getinfo($ch);
        $url = $request['url'];
        $user_data = $request['user_data'];
        
        if(curl_errno($ch) !== 0 || intval($request_info['http_code']) !== 200) { //if server responded with http error
            $response = false;
        } else { //sucessful response
            $response = curl_multi_getcontent($ch);
        }
        //get request info
        $options = $request['options_set'];
        if($response && !empty($options[CURLOPT_HEADER])) {
            $k = intval($request_info['header_size']);
            $response = substr($response, $k);
        }
        //remove completed request and its curl handle
        unset($requests_map[$ch_hash]);
        curl_multi_remove_handle($multi_handle, $ch);
        //call the callback function and pass request info and user data to it
        if($this->_callback) {
            call_user_func($this->_callback, $url, $response, $user_data);
        }
        $request = null; //free up memory now just incase response was large
    }
}

class DescargaAsincrona {
    private $resultados;
    private $totalOk;
    private $totalErr;
    private $timeSec;
    private $mc;

    public function __construct($maxSimultaneos=10) {
        $this->mc = new MultiCurl($maxSimultaneos);

        $opts = RespuestaCurl::$defaultOptions;
        $opts[CURLOPT_COOKIE] = RespuestaCurl::getCookieString();
        $opts[CURLOPT_CUSTOMREQUEST] = 'GET';
        $this->mc->setOptions($opts);

        $this->mc->setCallback(function($url, $response, $user_data) {
            $ok = $this->guardarArchivo(
                $response,
                $user_data['dir'],
                $user_data['fn'],
                $user_data['ext']
            );
            $this->resultados[] = array(
                'uuid' => $user_data['uuid'],
                'guardado' => $ok
            );
            if($ok) {
                $this->totalOk++;
            }else{
                $this->totalErr++;
            }
        });
    }

    public function agregarXml($url, $dir, $uuid, $nombreArchivo=null) {
        $name = "TEMP_";
        $name .= $nombreArchivo ? $nombreArchivo : $uuid;
        $this->mc->addRequest($url, array(
            'ext'=>'xml',
            'dir'=>$dir,
            'uuid'=>$uuid,
            'fn'=>$name
        ));
    }

    public function agregarAcuse($url, $dir, $uuid, $nombreArchivo=null) {
        /*$this->mc->addRequest($url, array(
            'ext'=>'pdf',
            'dir'=>$dir,
            'uuid'=>$uuid,
            'fn'=>$nombreArchivo ? $nombreArchivo : $uuid
        ));*/
    }

    public function procesar() {
        // restaurar valores
        $this->resultados = array();
        $this->totalOk = 0;
        $this->totalErr = 0;
        $this->timeSec = 0;

        $time = microtime(true);
        $this->mc->execute();
        $this->timeSec = microtime(true) - $time;
        $this->mc = null;

        return true;
    }

    public function totalDescargados() {
        return $this->totalOk;
    }

    public function totalErrores() {
        return $this->totalErr;
    }

    public function segundosTranscurridos() {
        return round($this->timeSec, 3);
    }

    public function resultado() {
        return $this->resultados;
    }

    private function guardarArchivo($str, $dir, $nombre, $ext) {
        $resource = fopen($dir.DIRECTORY_SEPARATOR.$nombre.'.'.$ext, 'w');
        $saved = false;
        if(!empty($str)) {
            $bytes = fwrite($resource, $str);
            $saved = ($bytes !== false);
            fclose($resource);
        }
        return $saved;
    }

    public function renombrar_y_BD()
    {
        //Path si esta conectado a despachos virtuales
        $path = "../../../";
        if(isset($_COOKIE['inst_lig']))
            $path = "../../../../../../../".$_COOKIE['inst_lig']."/webapp/modulos/cont/";

        $files = glob($path.'xmls/facturas/temporales/TEMP_*');
        foreach($files as $file)
        {
            $uuid = explode('TEMP_',$file);
            $uuid = str_replace('.xml', '', $uuid[1]);
            $this->renombrar_y_BD2($uuid);
        }
        //Borra los xml sobrantes que fallaron
        $temps = glob($path.'xmls/facturas/temporales/TEMP_*');
        foreach($temps as $temp)
        {
            unlink($temp); // lo elimina
        }

    }

    public function renombrar_y_BD2($uuid)
    {
        global $xp;

        //Path si esta conectado a despachos virtuales
        $path = "../../../";
        if(isset($_COOKIE['inst_lig']))
            $path = "../../../../../../../".$_COOKIE['inst_lig']."/webapp/modulos/cont/";

        $datos = array();
        require '../../../../../netwarelog/webconfig.php';

        $arrSTRIP = array('Š'=>'S','š'=>'s','Ž'=>'Z','ž'=>'z','À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'A','Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y','Þ'=>'B','ß'=>'Ss','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ð'=>'o','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ý'=>'y','þ'=>'b','ÿ'=>'y','.'=>'',"'"=>'',"/"=>"");

        $conn = new mysqli($servidor,$usuariobd,$clavebd, $bd);

        //Buscar el RFC de la instancia
        $rfc = $conn->query("SELECT rfc AS RFC FROM pvt_configura_facturacion WHERE id = 1");
        $rfc = $rfc->fetch_assoc();
        $rfc = $rfc["RFC"];
        //Ruta donde se guardan las factyras
        $ruta = $path.'xmls/facturas/temporales/';
        $existeFac = $conn->query("SELECT COUNT(*) AS existe FROM cont_facturas WHERE uuid LIKE '%$uuid%';");
        $existeFac = $existeFac->fetch_assoc();
        $existeFac = $existeFac['existe'];
        //Lee el contenido de la factura para extrar datos
        if(!intval($existeFac))
        {
            if($texto = file_get_contents($ruta . 'TEMP_' . $uuid . ".xml"))
            {
                $xml    = new DOMDocument();
                $xml->loadXML($texto);
                $xp = new DOMXpath($xml);

                if($this->getpath("//cfdi:Comprobante//@Version"))
                    $version = $this->getpath("//cfdi:Comprobante//@Version");
                else
                    $version = $this->getpath("//cfdi:Comprobante//@version");

                $version = $version[0];
                
                $datos['uuid'] = $uuid;

                if($version == '3.3')//si es factura 3.3
                {
                    $datos['folio'] = $this->getpath("//cfdi:Comprobante//@Folio");
                    if($rfc == $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@Rfc"))
                    {
                        $datos['razon'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@Nombre");
                        $datos['rfc'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@Rfc");
                        $datos['er'] = "E";
                        $datos['tipo'] = "Ingresos";
                    }
                    if($rfc == $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@Rfc"))
                    {
                        $datos['razon'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@Nombre");
                        $datos['rfc'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@Rfc");
                        $datos['er'] = "R";
                        $datos['tipo'] = "Egresos";
                    }
                    $datos['serie'] = $this->getpath("//cfdi:Comprobante//@Serie");
                    $datos['emisor'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@Nombre");
                    $datos['receptor'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@Nombre");
                    $datos['importe'] = $this->getpath("//cfdi:Comprobante//@Total");
                    $datos['fecha'] = $this->getpath('//cfdi:Comprobante//@Fecha');
                    $datos['tipoComp'] = $this->getpath("//@TipoDeComprobante");
                    if($datos['tipoComp'] == "P")
                    {
                        $datos['IdDocumento']    = $this->getpath("//@IdDocumento");
                        $datos['ImpPagado']      = $this->getpath("//@ImpPagado");
                        $datos['ImpSaldoAnt']    = $this->getpath("//@ImpSaldoAnt");
                        $datos['ImpSaldoInsoluto'] = $this->getpath("//@ImpSaldoInsoluto");
                        $datos['MonedaDR']       = $this->getpath("//@MonedaDR");
                        $datos['MetodoDePagoDR'] = $this->getpath("//@MetodoDePagoDR");
                        if($this->getpath("//@NumParcialidad"))
                            $datos['NumParcialidad'] = $this->getpath("//@NumParcialidad");
                        
                        if(is_array($datos['folio']))
                            $datos['folio'] = "";
                    }
                }
                else//si es factura 3.2
                {
                    $datos['folio'] = $this->getpath("//cfdi:Comprobante//@folio");
                    if($rfc == $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@rfc"))
                    {
                        $datos['razon'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@nombre");
                        $datos['rfc'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@rfc");
                        $datos['er'] = "E";
                        $datos['tipo'] = "Ingresos";
                    }
                    if($rfc == $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@rfc"))
                    {
                        $datos['razon'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@nombre");
                        $datos['rfc'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@rfc");
                        $datos['er'] = "R";
                        $datos['tipo'] = "Egresos";
                    }
                    $datos['serie'] = $this->getpath("//cfdi:Comprobante//@serie");
                    $datos['emisor'] = $this->getpath("//cfdi:Comprobante//cfdi:Emisor//@nombre");
                    $datos['receptor'] = $this->getpath("//cfdi:Comprobante//cfdi:Receptor//@nombre");
                    $datos['importe'] = $this->getpath("//cfdi:Comprobante//@total");
                    $datos['fecha'] = $this->getpath('//cfdi:Comprobante//@fecha');
                }
                

                if($this->getpath("//@NumEmpleado"))
                {
                    $datos['tipo'] = "Nomina";
                    $datos['NumEmpleado'] = $this->getpath("//@NumEmpleado");
                }
                $datos['version'] = $version;
                $datos['moneda'] = $this->getpath('//cfdi:Comprobante//@Moneda');

                //Convertir el XML en una cadena json
                //Convertir el XML en una cadena json
                $cont_xml = simplexml_load_file($ruta . 'TEMP_' . $uuid . ".xml");
                $cont_array = $this->xmlToArray($cont_xml);
                $datos['json'] = utf8_encode(json_encode($cont_array));

                $newname = $datos['folio'] . "_" . strtoupper(strtr($datos['razon'], $arrSTRIP)) . "_" . $datos['uuid'] . ".xml";
                $datos['xml'] = $newname;

                //Renombrar el archivo xml al formato folio_razon social_uuid.xml
                rename($ruta . 'TEMP_' . $uuid . ".xml",$ruta . $newname);
                
                //Guardar en la base de datos de facturas
                $myQuery = "INSERT INTO cont_facturas (id, folio, uuid, er, tipo, serie, emisor, receptor, importe, moneda, rfc, fecha, fecha_subida, xml, version, cancelada, json, temporal, origen) VALUES(0, '".$datos['folio']."', '".$datos['uuid']."', '".$datos['er']."', '".$datos['tipo']."', '".$datos['serie']."', '".strtoupper(strtr($datos['emisor'], $arrSTRIP))."', '".strtoupper(strtr($datos['receptor'], $arrSTRIP))."', ".$datos['importe'].", '".$datos['moneda']."', '".$datos['rfc']."', '".$datos['fecha']."', DATE_SUB(NOW(), INTERVAL 6 HOUR), '".$datos['xml']."', ".$datos['version'].", 0, '".$datos['json']."', 1, 1);";

                if($conn->query($myQuery))
                {
                    //Si se trata de un complemento de pago
                    if($datos['tipoComp'] == "P")
                    {
                        if(!intval($datos['NumParcialidad']))
                            $datos['NumParcialidad'] = 0;

                        if(is_array($datos['IdDocumento']))//Si es un array con varios registros
                        {
                            for($h=0;$h<=count($datos['IdDocumento'])-1;$h++)
                            {
                                $conn->query("INSERT IGNORE INTO cont_facturas_relacion VALUES('".$datos['uuid']."','".$datos['IdDocumento'][$h]."',".$datos['ImpPagado'][$h].",".$datos['ImpSaldoAnt'][$h].",".$datos['ImpSaldoInsoluto'][$h].",'".$datos['MonedaDR'][$h]."','".$datos['MetodoDePagoDR'][$h]."',".$datos['NumParcialidad'][$h].");");
                            }
                        }
                        else//si no es un array
                        {
                            $conn->query("INSERT IGNORE INTO cont_facturas_relacion VALUES('".$datos['uuid']."','".$datos['IdDocumento']."',".$datos['ImpPagado'].",".$datos['ImpSaldoAnt'].",".$datos['ImpSaldoInsoluto'].",'".$datos['MonedaDR']."','".$datos['MetodoDePagoDR']."',".$datos['NumParcialidad'].");");
                        }
                    }

                    //Guardar datos del cliente
                    if($datos['er'] == "E" && $datos['tipo'] != "Nomina")
                    {
                        $myQuery = "INSERT INTO comun_cliente(nombre,rfc) 
                                    SELECT * FROM (SELECT '".strtoupper(strtr($datos['razon'], $arrSTRIP))."','".$datos['rfc']."') AS tmp
                                    WHERE NOT EXISTS (SELECT rfc FROM comun_cliente WHERE rfc = '".$datos['rfc']."') LIMIT 1;";
                        $cliPro = 1;                        
                    }

                    //Guardar datos del proveedor
                    if($datos['er'] == "R")
                    {
                        $myQuery = "INSERT INTO mrp_proveedor(razon_social, rfc,idtipotercero, idtipoperacion)
                                    SELECT * FROM (SELECT '".strtoupper(strtr($datos['razon'], $arrSTRIP))."','".$datos['rfc']."',0 AS ter,0 AS op) AS tmp
                                    WHERE NOT EXISTS (SELECT rfc FROM mrp_proveedor WHERE rfc = '".$datos['rfc']."') LIMIT 1;";
                        $cliPro = 2;       
                    }

                    //Guardar datos del empleado
                    if($datos['er'] == "E" && $datos['tipo'] == "Nomina")
                    {
                        $myQuery = "INSERT INTO nomi_empleados(idEmpleado, codigo, nombreEmpleado, idestado, idmunicipio, rfc)
                                    SELECT * FROM (SELECT 0 AS id,'".$datos['NumEmpleado']."','".strtoupper(strtr($datos['razon'], $arrSTRIP))."',14,0,'".$datos['rfc']."') AS tmp
                                    WHERE NOT EXISTS (SELECT rfc FROM nomi_empleados WHERE rfc = '".$datos['rfc']."') LIMIT 1;";

                        $cliPro = 0;                        
                    }

                    //Guarda los datos
                    $conn->query($myQuery);
                    $insert_id = $conn->insert_id;

                    if($cliPro)//Si es cliente o proveedor entonces entra si es empleado no entra
                    {
                        //Guarda en datos de facturacion del cliente o proveedor
                        $myQuery2 = "INSERT INTO comun_facturacion(nombre,rfc,razon_social,cliPro)
                                        SELECT * FROM (SELECT $insert_id,'".$datos['rfc']."','".strtoupper(strtr($datos['razon'], $arrSTRIP))."',$cliPro) AS tmp
                                        WHERE NOT EXISTS (SELECT rfc FROM comun_facturacion WHERE rfc = '".$datos['rfc']."') LIMIT 1;";
                        $conn->query($myQuery2);
                    }
                }
            }
        }
        else
            $this->totalOk--;
        $conn->close();
    }

    public function getpath($qry)
    {
        global $xp;
        $prm = array();
        $nodelist = $xp->query($qry);
        foreach ($nodelist as $tmpnode)
        {
                $prm[] = trim($tmpnode->nodeValue);
            }
        $ret = (sizeof($prm)<=1) ? $prm[0] : $prm;
        return($ret);
    }

    public function xmlToArray($xml, $options = array()) {
        $defaults = array(
            'namespaceSeparator' => ':',//you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(),   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false       //replace values for above search values (as passed to str_replace())
        );
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace
     
        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) $attributeName =
                        str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                $attributeKey = $options['attributePrefix']
                        . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                        . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }
     
        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = $this->xmlToArray($childXml, $options);
                list($childTagName, $childProperties) = each($childArray);
     
                //replace characters in tag name
                if ($options['keySearch']) $childTagName =
                        str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                //add namespace prefix, if any
                if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
     
                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                            in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? array($childProperties) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
            }
        }
     
        //get text content of node
        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;
     
        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
                ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
     
        //return node as array
        return array(
            $xml->getName() => $propertiesArray
        );
    }
}