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
     * @param $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        // @ todo prevent header overflow

        $parser = http_parser_init();

        $result = [];
        if (http_parser_execute($parser, $this->buffer, $result)) {

            var_dump($result);

            if ($this->bodySent($result)) {
                $this->prepareRequest($result);
                $this->finishParsing();
            }
        }
    }

    /**
     * @return null
     */
    protected function finishParsing()
    {
        $this->emit('headers', array($this->request, $this->request->getBody()));
        $this->removeAllListeners();
        $this->buffer = '';
    }

    protected function prepareRequest(array $env)
    {
        $headers = $this->getKey($env, 'headers', []);
        $body = $this->getKey($headers, 'body', '');
        $contentType = $this->getKey($headers, 'Content-Type');

        if (isset($headers['body'])) {
            unset($headers['body']);
        }

        if ($this->shouldParseBody($contentType)) {
            $post = $this->parsePost($body);
            $body = '';
        } else {
            $post = [];
        }

        $this->request = new Request(
            $this->getKey($env, 'REQUEST_METHOD', 'GET'),
            new gPsr\Uri($this->getKey($env, 'QUERY_STRING', '/')), // @ todo just manually build the URI
            $this->parseQuery($this->getKey($env, 'query')),
            '1.1',
            $headers,
            $body
        );

        if (strpos($contentType, 'multipart/') === 0) {
            //TODO :: parse the content while it is streaming
            preg_match("/boundary=\"?(.*)\"?$/", $headers['Content-Type'], $matches);
            $boundary = $matches[1];
            $parser = new MultipartParser($body, $boundary);
            $parser->parse();
            $post = $parser->getPost();
            $this->request->setFiles($parser->getFiles());
        }

        $this->request->setPost($post);
    }

    protected function headerSizeExceeded()
    {
        $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
    }

    private function bodySent($env)
    {
        $headers = $this->getKey($env, 'headers', []);
        $body = $this->getKey($headers, 'body', '');
        $contentLength = $this->getKey($headers, 'Content-Length');

        if (strlen($body) >= (int) $contentLength) {
            return true;
        }

        return false;
    }

    /**
     * @param array $src
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    protected function getKey(array $src, $key, $default = null)
    {
        if (isset($src[$key])) {
            return $src[$key];
        }

        return $default;
    }

    private function parseQuery($queryString)
    {
        if (is_null($queryString)) {
            return [];
        }

        $parsed = [];
        parse_str($queryString, $parsed);
        return $parsed;
    }

    private function parsePost($body)
    {
        $body = trim($body);
        $parsed = [];
        parse_str(urldecode($body), $parsed);
        return $parsed;
    }

    private function shouldParseBody($contentType)
    {
        return $contentType === 'application/x-www-form-urlencoded';
    }
}