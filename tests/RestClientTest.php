<?php
namespace Terah\RestClient;

require_once __DIR__ . '/../../../../vendor/autoload.php';



class RestClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var RestClient */
    public $restClient = null;

    public function setUp()
    {
        $this->restClient = new RestClient('http://localhost/api/v1.0/', 'asdfdsaf-asdfasdf-asdfasdf', 'X-MyAuth', 'fredflintstone', 'iluvbetty');
    }

    public function testGenerateGetRequest()
    {

        $request = $this->restClient
            ->verbose(true)
            ->data(['param1' => 'value1'])
            ->accept('json')
            ->contentType('json')
            ->method('get')
            ->getRawRequest('users');
        $expected = "GET /api/v1.0/users?param1=value1 HTTP/1.1\r\nHost: example.com\r\nAuthorization: Basic ZnJlZGZsaW50c3RvbmU6aWx1dmJldHR5\r\nAccept-Encoding: deflate, gzip\r\nX-Api-Version:1.0\r\nAccept:application/json\r\nContent-Type:application/json\r\nX-MyAuth:asdfdsaf-asdfasdf-asdfasdf\r\n\r\n";

        $this->assertEquals($request, $expected);
    }

}
