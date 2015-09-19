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
    private $nRead = 0;

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
        $this->nRead = $this->parser->execute($this->buffer, $this->nRead);

        if ($this->parser->hasError()) {
            $this->emit('error', array(new \OverflowException('Maximum header size of 4096 exceeded.'), $this));
        }

        if ($this->parser->isFinished()) {

            if ($this->bodySent()) {
                $this->prepareRequest();
                $this->finishParsing();
            }

            $this->resetParser();
        }
    }

    /**
     * @return null
     */
    protected function finishParsing()
    {
        $this->emit('headers', array($this->request, $this->request->getBody()));
        $this->removeAllListeners();
        $this->resetParser();
    }

    protected function prepareRequest()
    {
        $env = $this->parser->getEnvironment();
        $envParser = new EnvParser($env);

        $this->request = new Request(
            $envParser->getMethod(),
            $envParser->getUrl(),
            $envParser->getQuery(),
            $envParser->getProtocolVersion(),
            $envParser->getHeaders(),
            $envParser->getBody()
        );

        $this->request->setPost($envParser->getPost());
    }

    private function bodySent()
    {
        $env = $this->parser->getEnvironment();

        if (!isset($env['HTTP_CONTENT_LENGTH'])) {
            return true;
        }

        if (strlen($env['REQUEST_BODY']) >= $env['HTTP_CONTENT_LENGTH']) {
            return true;
        }

        return false;
    }

    protected function resetParser()
    {
        $this->nRead = 0;
        $this->parser = new \HttpParser();
    }
}
