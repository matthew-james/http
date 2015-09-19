<?php namespace React\Http; 

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

class EnvParser
{
    /**
     * @var array
     */
    private static $studlyCache;

    /**
     * @var string
     */
    private $method;

    /**
     * @var UriInterface
     */
    private $url;

    /**
     * @var array
     */
    private $query;

    /**
     * @var string
     */
    private $protocolVersion;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var string
     */
    private $body = '';

    /**
     * @var array
     */
    private $post;

    public function __construct(array $env)
    {
        $this->parseEnv($env);
    }

    private function parseEnv($env)
    {
        $this->parseMethod($env);
        $this->parseUri($env);
        $this->parseQuery($env);
        $this->parseProtocolVersion($env);
        $this->parseHeaders($env);
        $this->parseBody($env);
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return UriInterface
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param $env
     */
    private function parseMethod($env)
    {
        $this->method = isset($env['REQUEST_METHOD']) ? $env['REQUEST_METHOD'] : 'GET';
    }

    private function parseUri($env)
    {
        $uri = isset($env['REQUEST_URI']) ? $env['REQUEST_URI'] : '/';

        $this->url = new Uri($uri);
    }

    private function parseQuery($env)
    {
        $queryString = isset($env['QUERY_STRING']) ? $env['QUERY_STRING'] : '';
        $query = [];
        parse_str($queryString, $query);
        $this->query = $query;
    }

    private function parseProtocolVersion($env)
    {
        $protocol = isset($env['HTTP_VERSION']) ? $env['HTTP_VERSION'] : '1.1';
        $protocol  = str_replace('HTTP/', '', $protocol); // this isnt very robust
        $this->protocolVersion = $protocol;
    }

    private function parseHeaders($env)
    {
        $env = array_flip($env);

        // strip everything that isn't a header
        $env = array_filter($env, function($k) {
           return strpos($k, 'HTTP') !== false && $k !== 'HTTP_VERSION';
        });

        // strip the HTTP_ part from the beginning
        $env = array_map(function ($k) {
            return str_replace('HTTP_', '', $k);
        }, $env);

        // studly / dasherize
        $env = array_map(function ($k) {
            return $this->studlyDasherized($k);
        }, $env);

        $this->headers = array_flip($env);
    }

    private function parseBody($env)
    {
        $body = isset($env['REQUEST_BODY']) ? $env['REQUEST_BODY'] : '';

        $body = trim($body);

        if (!$this->shouldParseBody($env)) {
            $this->body = $body;
            return;
        }

        $parsed = [];
        parse_str(urldecode($body), $parsed);

        $this->post = $parsed;
    }

    /**
     * Convert a value to studly caps case,
     * but dasherized.
     *
     * @param  string  $value
     * @return string
     */
    private function studlyDasherized($value)
    {
        $key = $value;
        if (isset(self::$studlyCache[$key])) {
            return self::$studlyCache[$key];
        }

        $value = ucwords(strtolower(str_replace(['_', '-'], ' ', $value)));
        return self::$studlyCache[$key] = str_replace(' ', '-', $value);
    }

    private function shouldParseBody($env)
    {
        $contentType = isset($env['HTTP_CONTENT_TYPE']) ? $env['HTTP_CONTENT_TYPE'] : '';

        return $contentType === 'application/x-www-form-urlencoded';
    }
}