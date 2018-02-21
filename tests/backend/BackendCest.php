<?php

declare(strict_types=1);

use Codeception\Util\HttpCode;

final class BackendCest
{
    public function testDashboard(ApiTester $I)
    {
        $I->sendGET('/dashboard');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testClients(ApiTester $I)
    {
        $I->sendGET('/api/clients');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testNewClient(ApiTester $I)
    {
        $I->sendGET('/api/clients/new');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
