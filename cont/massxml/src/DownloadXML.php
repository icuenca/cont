<?php

namespace Blacktrue\Scraping;

use Closure;
use GuzzleHttp\Promise\EachPromise;
use Psr\Http\Message\ResponseInterface;

/**
 * Class DownloadXML.
 */
class DownloadXML
{
    /**
     * @var SATScraper
     */
    protected $satScraper;

    /**
     * @var int
     */
    protected $concurrency;

    /**
     * DownloadXML constructor.
     */
    public function __construct()
    {
        $this->concurrency = 100;
    }

    /**
     * @param Closure $callback
     */
    public function download(Closure $callback)
    {
        $promises = $this->makePromises();

        (new EachPromise($promises, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function (ResponseInterface $response) use ($callback) {
                $callback($response->getBody(), $this->getFileName($response));
            },
        ]))->promise()
            ->wait();
    }

    /**
     * @return \Generator
     */
    protected function makePromises()
    {
        foreach ($this->satScraper->getRequests() as $link) {
            yield $this->satScraper->getClient()->requestAsync('GET', $link, [
                'future' => true,
                'verify' => false,
                'cookies' => $this->satScraper->getCookie(),
            ]);
        }
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     */
    protected function getFileName(ResponseInterface $response)
    {
        $contentDisposition = $response->getHeaderLine('content-disposition');
        $partsOfContentDisposition = explode(';', $contentDisposition);
        $fileName = str_replace('filename=', '', isset($partsOfContentDisposition[1]) ? $partsOfContentDisposition[1] : '');

        return !empty($fileName) ? $fileName : uniqid().'.xml';
    }

    /**
     * @param SATScraper $satScraper
     *
     * @return DownloadXML
     */
    public function setSatScraper(SATScraper $satScraper)
    {
        $this->satScraper = $satScraper;

        return $this;
    }

    /**
     * @param int $concurrency
     *
     * @return DownloadXML
     */
    public function setConcurrency($concurrency)
    {
        $this->concurrency = $concurrency;

        return $this;
    }
}
