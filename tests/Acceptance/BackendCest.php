<?php

namespace Tests\Acceptance;

use Codeception\Util\HttpCode;
use Tests\Support\AcceptanceTester;

class BackendCest
{
    public function testUnlogged(AcceptanceTester $I)
    {
        $I->stopFollowingRedirects();

        $I->amOnPage('/admin/dashboard');
        $I->seeResponseCodeIsRedirection();
        $I->haveHttpHeader('Location', '/admin/login');

        $I->amOnPage('/admin/api/clients');
        $I->seeResponseCodeIsRedirection();
        $I->haveHttpHeader('Location', '/admin/login');

        $I->amOnPage('/admin/api/clients/new');
        $I->seeResponseCodeIsRedirection();
        $I->haveHttpHeader('Location', '/admin/login');

        $I->amOnPage('/admin/login');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testLogin(AcceptanceTester $I)
    {
        $I->amOnPage('/admin/login');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->submitForm('#loginForm', [
            '_username' => 'admin',
            '_password' => 'test',
        ], 'submitButton');

        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSee('Log-in');
        $I->see('Tracks & Albums');

        $I->amOnPage('/admin/api/clients');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Create new client');

        $I->amOnPage('/admin/api/clients/new');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Create a new client');
    }
}
