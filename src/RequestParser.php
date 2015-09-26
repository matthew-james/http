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
    private $buffer = '';
    private $maxSize = 4096;

    /**
     * @var ServerRequest
     */
    private $request;
    private $length = 0;

    public function feed($data)
    {
        $this->buffer .= $data;

        if (!$this->request && false !== strpos($this->buffer, "\r\n\r\n")) {

            // Extract the header from the buffer
            // in case the content isn't complete
            list($headers, $this->buffer) = explode("\r\n\r\n", $this->buffer, 2);

            // Fail before parsing if the
            if (strlen($headers) > $this->maxSize) {
                $this->headerSizeExceeded();
                return;
            }

            $this->request = $this->parseHeaders($headers . "\r\n\r\n");
        }

        // if there is a request (meaning the headers are parsed) and
        // we have the right content size, we can finish the parsing
        if ($this->request && $this->isRequestComplete()) {
            $content = substr($this->buffer, 0, $this->length);
            $content = $content ?: '';
            $this->parseBody($content);
            $this->finishParsing();
            return;
        }

        // fail if the header hasn't finished but it is already too large
        if (!$this->request && strlen($this->buffer) > $this->maxSize) {
            $this->headerSizeExceeded();
            return;
        }
    }

    protected function isRequestComplete()
    {
        $contentLength = $this->request->getHeaderLine('Content-Length');

        // if there is no content length, there should
        // be no content so we can say it's done
        if (!$contentLength) {
            return true;
        }

        // if the content is present and has the
        // right length, we're good to go
        if ($contentLength && strlen($this->buffer) >= $contentLength) {

            // store the expected content length
            $this->length = $contentLength;

            return true;
        }

        return false;
    }

    protected function finishParsing()
    {
        $this->emit('headers', array($this->request, $this->request->getBody()));
        $this->removeAllListeners();
        $this->request = null;
    }

    protected function headerSizeExceeded()
    {
        $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
    }

    public function parseHeaders($data)
    {
        $psrRequest = gPsr\parse_request($data);

        $parsedQuery = [];
        $queryString = $psrRequest->getUri()->getQuery();
        if ($queryString) {
            parse_str($queryString, $parsedQuery);
        }

        $request = new ServerRequest(
            $psrRequest->getMethod(),
            $psrRequest->getUri(),
            $psrRequest->getHeaders(),
            null
        );

        return $request->withQueryParams($parsedQuery);
    }

    public function parseBody($content)
    {
        $contentType = $this->request->getHeaderLine('Content-Type');

        if ($contentType) {
            if (strpos($contentType, 'multipart/') === 0) {
                //TODO :: parse the content while it is streaming
                preg_match("/boundary=\"?(.*)\"?$/", $contentType, $matches);
                $boundary = $matches[1];

                $parser = new MultipartParser($content, $boundary);
                $parser->parse();

                $this->request = $this->request->withParsedBody($parser->getPost());
                $this->request = $this->request->withUploadedFiles($parser->getFiles());
                return;
            }

            if (strtolower($contentType) == 'application/x-www-form-urlencoded') {
                parse_str(urldecode($content), $result);
                $this->request = $this->request->withParsedBody($result);
                return;
            }
        }

        $stream = gPsr\stream_for($content);
        $this->request = $this->request->withBody($stream);
    }
}
