<?php

namespace Blacktrue\Scraping;

use Blacktrue\Scraping\Contracts\Filters;
use Blacktrue\Scraping\Exceptions\SATException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Sunra\PhpSimple\HtmlDomParser;

define('MAX_FILE_SIZE', 1000000000000);

/**
 * Class SATScraper.
 */
class SATScraper
{
    const SAT_CREDENTIAL_ERROR = 'El RFC o CIEC son incorrectos';
    //const SAT_URL_LOGIN = 'https://cfdiau.sat.gob.mx/nidp/app/login?id=4&sid=1&option=credential&sid=1';
    //const SAT_URL_LOGIN = 'https://portalcfdi.facturaelectronica.sat.gob.mx/';
    const SAT_URL_LOGIN = 'https://cfdiau.sat.gob.mx/nidp/wsfed/ep?id=SATUPCFDiCon&sid=5&option=credential&sid=5';
    //const SAT_URL_LOGIN = 'https://cfdiau.sat.gob.mx//nidp/app/login?id=20&sid=0';
    const SAT_HOST_CFDI_AUTH = 'cfdiau.sat.gob.mx';
    const SAT_HOST_PORTAL_CFDI = 'portalcfdi.facturaelectronica.sat.gob.mx';
    const SAT_URL_PORTAL_CFDI = 'https://portalcfdi.facturaelectronica.sat.gob.mx/';
    const SAT_URL_WSFEDERATION = 'https://cfdicontribuyentes.accesscontrol.windows.net/v2/wsfederation';
    const SAT_URL_PORTAL_CFDI_CONSULTA = 'https://portalcfdi.facturaelectronica.sat.gob.mx/Consulta.aspx';
    const SAT_URL_PORTAL_CFDI_CONSULTA_RECEPTOR = 'https://portalcfdi.facturaelectronica.sat.gob.mx/ConsultaReceptor.aspx';
    const SAT_URL_PORTAL_CFDI_CONSULTA_EMISOR = 'https://portalcfdi.facturaelectronica.sat.gob.mx/ConsultaEmisor.aspx';

    /**
     * @var string
     */
    protected $rfc;

    /**
     * @var string
     */
    protected $ciec;

    /**
     * @var string
     */
    protected $jcaptcha;

    /**
     * @var string
     */
    protected $tipoDescarga = 'recibidos';

    /**
     * @var bool
     */
    protected $cancelados = false;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CookieJar
     */
    protected $cookie;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $requests = [];

    /**
     * @var null
     */
    protected $onFiveHundred = null;

    /**
     * SATScraper constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->data = [];
        $this->requests = [];

        $this->rfc = $options['rfc'];
        $this->ciec = $options['ciec'];
        $this->jcaptcha = $options['jcaptcha'];
        $this->tipoDescarga = isset($options['tipoDescarga']) ? $options['tipoDescarga'] : 'recibidos';
        $this->cancelados = isset($options['cancelados']) ? $options['cancelados'] : false;
        $this->client = new Client();
        $this->cookie = new CookieJar();
        $this->init();
    }

    /**
     * @return $this
     *
     * @throws SATException
     */
    protected function init()
    {
        $this->login();
        $data = $this->dataAuth();
        $data = $this->postDataAuth($data);
        $data = $this->start($data);
        $this->selectType($data);
    }

    /**
     * @return string
     *
     * @throws SATException
     */
    private function login()
    {
        $response = $this->client->post(self::SAT_URL_LOGIN, [
            'future' => true,
            'verify' => false,
            'cookies' => $this->cookie,
            'headers' => Headers::post(
                self::SAT_HOST_CFDI_AUTH,
                self::SAT_URL_LOGIN
            ),
            'form_params' => [
                'Ecom_Password' => $this->ciec,
                'Ecom_User_ID' => $this->rfc,
                'jcaptcha' => $this->jcaptcha,
                'option' => 'credential',
                'submit' => 'Enviar',
            ],
        ])->getBody()->getContents();
        

        if (strpos($response, 'Ecom_Password') !== false) {
            throw new SATException(self::SAT_CREDENTIAL_ERROR);
        }

        return $response;
    }

    /**
     * @return array
     */
    public function dataAuth()
    {
        $response = $this->client->get(self::SAT_URL_PORTAL_CFDI, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
        ])->getBody()->getContents();
        $inputs = $this->parseInputs($response);

        return $inputs;
    }

    /**
     * @param array $inputs
     *
     * @return array
     */
    public function postDataAuth(array $inputs)
    {
        $response = $this->client->post(self::SAT_URL_WSFEDERATION, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
            'form_params' => array_map('utf8_decode',$inputs),
        ])->getBody()->getContents();
        
        $inputs = $this->parseInputs($response);

        return $inputs;
    }
    
    /**
     * @param array $inputs
     *
     * @return array
     */
    public function start(array $inputs = [])
    {
        $response = $this->client->post(self::SAT_URL_PORTAL_CFDI, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
            'form_params' => array_map('utf8_decode',$inputs),
        ])->getBody()->getContents();
        $inputs = $this->parseInputs($response);

        return $inputs;
    }

    /**
     * @param array $inputs
     *
     * @return string
     */
    public function selectType(array $inputs)
    {
        $rdoTipoBusqueda = 'RdoTipoBusquedaReceptor';
        if ($this->tipoDescarga == 'emitidos') {
            $rdoTipoBusqueda = 'RdoTipoBusquedaEmisor';
        }

        $data = [
            'ctl00$MainContent$TipoBusqueda' => $rdoTipoBusqueda,
            '__ASYNCPOST' => 'true',
            '__EVENTTARGET' => '',
            '__EVENTARGUMENT' => '',
            'ctl00$ScriptManager1' => 'ctl00$MainContent$UpnlBusqueda|ctl00$MainContent$BtnBusqueda',
        ];

        $data = array_merge($inputs, $data);

        $response = $this->client->post(self::SAT_URL_PORTAL_CFDI_CONSULTA, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
            'form_params' => array_map('utf8_decode',$inputs),
            'headers' => Headers::post(
                self::SAT_HOST_CFDI_AUTH,
                self::SAT_URL_PORTAL_CFDI
            ),
        ])->getBody()->getContents();

        return $response;
    }

    /**
     * @param array $uuids
     */
    public function downloadListUUID(array $uuids = [])
    {
        $this->data = [];
        foreach ($uuids as $uuid) {
            $filters = new FiltersReceived();
            if ($this->tipoDescarga == 'emitidos') {
                $filters = new FiltersIssued();
            }

            $filters->taxId = $uuid;

            if ($this->cancelados == true) {
                $filters->stateVoucher = '0';
            }

            $html = $this->runQueryDate($filters);
            $this->makeData($html);
        }
    }

    /**
     * @param int $startYear
     * @param int $startMonth
     * @param int $startDay
     * @param int $endYear
     * @param int $endMonth
     * @param int $endDay
     *
     * @throws SATException
     */
    public function downloadPeriod($startYear, $startMonth, $startDay, $endYear, $endMonth, $endDay)
    {
        if ($endYear >= $startYear && $endMonth >= $startMonth) {
            $dateCurrent = strtotime($startDay.'-'.$startMonth.'-'.$startYear.'00:00:00');
            $endDate = strtotime($endDay.'-'.$endMonth.'-'.$endYear.'00:00:00');
            $this->data = [];

            while ($dateCurrent <= $endDate) {
                list($pYear, $pMonth, $pDay) = explode('-', date('Y-m-d', $dateCurrent));

                $this->downloadDay((int) $pYear, (int) $pMonth, (int) $pDay);

                $dateCurrent1 = date('Y-m-d', $dateCurrent);
                $dateNow = strtotime('+1 day', strtotime($dateCurrent1));
                $dateCurrent = strtotime(date('Y-m-d', $dateNow));
            }
        } else {
            throw new SATException('Las fechas finales no pueden ser menor a las iniciales');
        }
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     */
    protected function downloadDay($year, $month, $day)
    {
        $secondInitial = 1;
        $secondEnd = 86400;
        $queryStop = false;
        $totalRecords = 0;

        while ($queryStop === false) {
            $result = $this->downloadSeconds((int) $year, (int) $month, (int) $day, (int) $secondInitial, (int) $secondEnd);

            if ($result >= 500 && !is_null($this->onFiveHundred) && is_callable($this->onFiveHundred)) {
                $params = [
                    'count' => $result,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'secondIni' => $secondInitial,
                    'secondFin' => $secondEnd,
                ];
                call_user_func($this->onFiveHundred, $params);
            }

            if ($result < 500 && $result !== '-1') {
                $totalRecords = (int) $totalRecords + $result;
                if ($secondEnd == 86400) {
                    $queryStop = true;
                }
                if ($secondEnd < 86400) {
                    $secondInitial = (int) $secondEnd + 1;
                    $secondEnd = 86400;
                }
            } else {
                if ($secondEnd > $secondInitial) {
                    $secondEnd = floor($secondInitial + (($secondEnd - $secondInitial) / 2));
                } elseif ($secondEnd <= $secondInitial) {
                    $totalRecords = (int) $totalRecords + $result;
                    if ($secondEnd == 86400) {
                        $queryStop = true;
                    } elseif ($secondEnd < 86400) {
                        $secondInitial = $secondEnd + 1;
                        $secondEnd = 86400;
                    }
                }
            }
        }
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $startSec
     * @param int $endSec
     *
     * @return int
     */
    protected function downloadSeconds($year, $month, $day, $startSec, $endSec)
    {
        $filters = new FiltersReceived();
        if ($this->tipoDescarga == 'emitidos') {
            $filters = new FiltersIssued();
        }
        $filters->year = $year;
        $filters->month = $month;
        $filters->day = $day;

        if ($startSec != '0') {
            $time = $filters->converterSecondsToHours($startSec);
            $time_start = explode(':', $time);
            $filters->hour_start = $time_start[0];
            $filters->minute_start = $time_start[1];
            $filters->second_start = $time_start[2];
        }

        $time = $filters->converterSecondsToHours($endSec);

        $time_end = explode(':', $time);
        $filters->hour_end = $time_end[0];
        $filters->minute_end = $time_end[1];
        $filters->second_end = $time_end[2];

        if ($this->cancelados == true) {
            $filters->stateVoucher = '0';
        }

        $html = $this->runQueryDate($filters);
        $elements = $this->makeData($html);

        return $elements;
    }

    /**
     * @param Filters $filters
     *
     * @return string
     */
    protected function runQueryDate(Filters $filters)
    {
        if ($this->tipoDescarga == 'emitidos') {
            $url = self::SAT_URL_PORTAL_CFDI_CONSULTA_EMISOR;
            $result = $this->enterQueryTransmitter($filters);
        } else {
            $url = self::SAT_URL_PORTAL_CFDI_CONSULTA_RECEPTOR;
            $result = $this->enterQueryReceiver($filters);
        }

        $html = $result['html'];
        $inputs = $result['inputs'];

        $values = $this->getSearchValues($html, $inputs, $filters);

        $response = $this->client->post($url, [
            'form_params' => array_map('utf8_decode',$values),
            'headers' => Headers::postAjax(
                self::SAT_HOST_PORTAL_CFDI,
                $url
            ),
            'cookies' => $this->cookie,
            'future' => true,
            'verify' => false,
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * @param Filters $filters
     *
     * @return array
     */
    protected function enterQueryReceiver(Filters $filters)
    {
        $response = $this->client->get(self::SAT_URL_PORTAL_CFDI_CONSULTA_RECEPTOR, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
        ]);

        $html = $response->getBody()->getContents();

        $inputs = $this->parseInputs($html);
        $post = array_merge($inputs, $filters->getFormPostDates());

        $response = $this->client->post(self::SAT_URL_PORTAL_CFDI_CONSULTA_RECEPTOR, [
            'form_params' => array_map('utf8_decode',$post),
            'headers' => Headers::postAjax(
                self::SAT_HOST_PORTAL_CFDI,
                self::SAT_URL_PORTAL_CFDI_CONSULTA_RECEPTOR
            ),
            'future' => true,
            'verify' => false,
            'cookies' => $this->cookie,
        ]);

        return [
            'html' => $response->getBody()->getContents(),
            'inputs' => $inputs,
        ];
    }

    /**
     * @param Filters $filters
     *
     * @return array
     */
    protected function enterQueryTransmitter(Filters $filters)
    {
        $response = $this->client->get(self::SAT_URL_PORTAL_CFDI_CONSULTA_EMISOR, [
            'future' => true,
            'cookies' => $this->cookie,
            'verify' => false,
        ]);

        $html = $response->getBody()->getContents();

        $inputs = $this->parseInputs($html);
        $post = array_merge($inputs, $filters->getFormPostDates());

        $response = $this->client->post(self::SAT_URL_PORTAL_CFDI_CONSULTA_EMISOR, [
            'form_params' => array_map('utf8_decode',$post),
            'headers' => Headers::postAjax(
                self::SAT_HOST_PORTAL_CFDI,
                self::SAT_URL_PORTAL_CFDI_CONSULTA_EMISOR
            ),
            'future' => true,
            'verify' => false,
            'cookies' => $this->cookie,
        ]);

        return [
            'html' => $response->getBody()->getContents(),
            'inputs' => $inputs,
        ];
    }

    /**
     * @param string  $html
     * @param array   $inputs
     * @param Filters $filters
     *
     * @return array
     */
    protected function getSearchValues($html, array $inputs, Filters $filters)
    {
        $parser = new ParserFormatSAT($html);
        $valuesChange = $parser->getFormValues();
        $temporary = array_merge($inputs, $filters->getPost());
        $temp = array_merge($temporary, $valuesChange);

        return $temp;
    }

    /**
     * @param string $html
     *
     * @return array
     */
    protected function parseInputs($html)
    {
        $htmlForm = new HtmlForm($html, 'form');
        $inputs = $htmlForm->getFormValues();

        return $inputs;
    }

    /**
     * @param string $html
     *
     * @return int
     */
    protected function makeData($html)
    {
        $arrSTRIP = array("{","}");
        $html = str_replace($arrSTRIP, "", $html);
        $dom = HtmlDomParser::str_get_html($html);
        $elems = $dom->find('#DivContenedor div table tr');
        $numberOfElements = count($elems);
        if ($numberOfElements < 500) {
            foreach ($elems as $elem) {
                $temp = [];
                $tds = $elem->children();
				
                if (isset($tds[0]->tag) && strtolower($tds[0]->tag) === 'th') {
                    continue;
                }
				
                $temp['uuid'] = trim($tds[1]->children()[0]->text());
                $temp['rfcEmisor'] = trim($tds[2]->children()[0]->text());
                $temp['nombreEmisor'] = trim($tds[3]->children()[0]->text());
                $temp['rfcReceptor'] = trim($tds[4]->children()[0]->text());
                $temp['nombreReceptor'] = trim($tds[5]->children()[0]->text());
                $temp['fechaEmision'] = trim($tds[6]->children()[0]->text());
                $temp['fechaCertificacion'] = trim($tds[7]->children()[0]->text());
                $temp['pacCertifico'] = trim($tds[8]->children()[0]->text());
                $temp['total'] = trim($tds[9]->children()[0]->text());
                $temp['efectoComprobante'] = trim($tds[10]->children()[0]->text());
                $temp['estadoComprobante'] = trim($tds[11]->children()[0]->text());
                $temp['fechaCancelacion'] = $this->tipoDescarga == 'recibidos' ? trim($tds[12]->children()[0]->text()) : '';
                //$temp['urlXml'] = $temp['estadoComprobante'] == 'Cancelado' ? 'CANCELADO' : str_replace(["return AccionCfdi('", "','Recuperacion');"], [self::SAT_URL_PORTAL_CFDI, ''], $tds[0]->find('.BtnDescarga')[0]->onclick);
                $temp['urlXml'] = str_replace(["return AccionCfdi('", "','Recuperacion');"], [self::SAT_URL_PORTAL_CFDI, ''], $tds[0]->find('.BtnDescarga')[0]->onclick);

                $this->data[$temp['uuid']] = $temp;
            }
        }

        
        return $numberOfElements;
    }

    /**
     * @return CookieJar
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return array
     */
    public function getRequests()
    {
        $requests = [];
        foreach ($this->getData() as $uuid => $data) {
//            if ($data['urlXml'] !== 'CANCELADO') {
                $requests[] = $data['urlXml'];
//            }
        }

        return $requests;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param \Closure $callback
     */
    public function setOnFiveHundred(\Closure $callback)
    {
        $this->onFiveHundred = $callback;
    }

    public function __destruct()
    {
        $this->data = [];
    }
}
