<?php

namespace React\Tests\Http;

use React\Http\ServerRequest;

class ServerRequestTest extends TestCase
{
    /** @test */
    public function expectsContinueShouldBeFalseByDefault()
    {
        $headers = array();
        $request = new ServerRequest('GET', '/', $headers);

        $this->assertFalse($request->expectsContinue());
    }

    /** @test */
    public function expectsContinueShouldBeTrueIfContinueExpected()
    {
        $headers = array('Expect' => array('100-continue'));
        $request = new ServerRequest('GET', '/', $headers);

        $this->assertTrue($request->expectsContinue());
    }
}
