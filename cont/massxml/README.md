
# SAT-SCRAPER  

Obtiene las facturas emitidias, recibidas, cancelados por medio de web scraping desde la pagina del SAT.

## Ejemplo de descarga por rango de fechas

```php
require "vendor/autoload.php";

use Blacktrue\Scraping\SATScraper;

$satScraper = new SATScraper([
    'rfc' => 'XAXX010101000',
    'ciec' => '123456',
    'tipoDescarga' => 'recibidos',//emitidos
    'cancelados' => true,//false
]);

$satScraper->downloadPeriod(2016,7,1,2016,7,1);
print_r($satScraper->getData());
```

## Ejemplo de descarga por lista de uuids

```php
$satScraper->downloadListUUID([
    '5cc88a1a-8672-11e6-ae22-56b6b6499611',
    '5cc88c4a-8672-11e6-ae22-56b6b6499611',
    '5cc88d4e-8672-11e6-ae22-56b6b6499611'
]);
print_r($satScraper->getData());
```

## Probar credenciales
```php
require "vendor/autoload.php";

use Blacktrue\Scraping\SATScraper;
use Blacktrue\Scraping\Exceptions\SATException;

try{
    $satScraper = new SATScraper([
        'rfc' => 'XAXX010101000',
        'ciec' => '123456',
        'tipoDescarga' => 'recibidos',//emitidos
        'cancelados' => true,//false
    ]);
}catch(Blacktrue\Scraping\Exceptions\SATException $e){
    echo $e->getMessage();
}

```

## Comprobar si existen errores de 500 comprobantes
```php
require "vendor/autoload.php";

use Blacktrue\Scraping\SATScraper;

$satScraper = new SATScraper([
    'rfc' => 'XAXX010101000',
    'ciec' => '123456',
    'tipoDescarga' => 'recibidos',//emitidos
    'cancelados' => true,//false
]);

$satScraper->setOnFiveHundred(function($data){
	print_r($data);
});

$satScraper->downloadPeriod(2016,7,1,2016,7,1);
print_r($satScraper->getData());

```
