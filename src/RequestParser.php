<?php

namespace React\Http;

use Evenement\EventEmitter;
use GuzzleHttp\Psr7 as gPsr;

/**
 * @event headers
 * @event error
 */
class RequestParser extends EventEmitter
{
    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var int
     */
    private $maxSize = 4096;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var int
     */
    private $length = 0;

    /**
     * @var int
     */
    private $bytesParsed;

    /**
     * @var \HttpParser
     */
    private $parser;

    public function __construct()
    {
        $this->parser = new \HttpParser();
    }

    /**
     * @param $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;
        $this->bytesParsed = $this->parser->execute($this->buffer, $this->bytesParsed);

        if ($this->parser->hasError()) {
            $this->emit('error', array(new \RuntimeException("Server Error"), $this));
        }

        if ($this->parser->isFinished()) {
            $this->prepareRequest();
            $this->finishParsing();
        }
    }

    /**
     * @return null
     */
    protected function finishParsing()
    {
        $this->emit('headers', array($this->request, $this->request->getBody()));
        $this->removeAllListeners();
        $this->request = null;
    }

    protected function prepareRequest()
    {
        $env = $this->parser->getEnvironment();

        // get query as array
        $queryString = isset($env['QUERY_STRING']) ? $env['QUERY_STRING'] : '';
        $query =[];
        parse_str($queryString, $query);

        $this->request = new Request(
            $env['REQUEST_METHOD'],
            $env['REQUEST_URI'],
            $query,
            $env['HTTP_VERSION'],
            $env,
            $env['REQUEST_BODY']
        );

        return;
    }
}
