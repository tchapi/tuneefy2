<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use tuneefy\PlatformEngine;

/**
 * @covers \Backend
 */
final class BackendTest extends TestCase
{
    protected $app;

    public function setUp()
    {
        $this->app = new tuneefy\Application();
        $this->app->configure();
    }

    private function get(string $path)
    {
        $user = current($this->app->get('params')['admin_users']);
        $pass =  $this->app->get('params')['admin_users'][$user];

        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin'.$path,
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => $pass,
            ]);
        $req = Request::createFromEnvironment($env);
        $req = $req->withHeader('Accept', 'application/json');
        $this->app->set('request', $req);
        $this->app->set('response', new Response());

        return $this->app->run(true);
    }

    public function testDashboard()
    {
        $response = $this->get('/dashboard');
        $this->assertSame($response->getStatusCode(), 200);
    }

    public function testClients()
    {
        $response = $this->get('/api/clients');
        $this->assertSame($response->getStatusCode(), 200);
    }

    public function testNewClient()
    {
        $response = $this->get('/api/clients/new');
        $this->assertSame($response->getStatusCode(), 200);
    }

}
