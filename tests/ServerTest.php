<?php

namespace React\Tests\Http;

use React\Http\Server;

class ServerTest extends TestCase
{
    public function testRequestEventIsEmitted()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', $this->expectCallableOnce());

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $io = new ServerStub();

        $i = 0;

        $server = new Server($io);
        $server->on('request', function ($request, $response) use (&$i) {
            $i++;

            $this->assertInstanceOf('React\Http\Request', $request);
            $this->assertSame('/', $request->getPath());
            $this->assertSame('GET', $request->getMethod());
            $this->assertSame('127.0.0.1', $request->getRemoteAddress());

            $this->assertInstanceOf('React\Http\Response', $response);
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertSame(1, $i);
    }

    public function testResponseContainsPoweredByHeader()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', function ($request, $response) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $conn->getData());
    }

    public function testServerUsesProvidedRequestParserFactory()
    {
        $io = new ServerStub();

        /** @var \React\Http\RequestParser|\PHPUnit_Framework_MockObject_MockObject $parser */
        $parser = $this->getMockBuilder('React\Http\RequestParser')->getMock();
        $parser->expects($this->once())->method('on');

        /** @var \React\Http\RequestParserFactory | \PHPUnit_Framework_MockObject_MockObject $parserFactory */
        $parserFactory = $this->getMockBuilder('React\Http\RequestParserFactory')->getMock();
        $parserFactory->expects($this->once())->method('create')->willReturn($parser);

        new Server($io, $parserFactory);

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }
}
