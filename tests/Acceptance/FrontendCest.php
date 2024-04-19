<?php

namespace Tests\Acceptance;

use Codeception\Util\HttpCode;
use Tests\Support\AcceptanceTester;

class FrontendCest
{
    public function testHome(AcceptanceTester $I)
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Partager de la musique. Facilement.');
    }

    public function testAbout(AcceptanceTester $I)
    {
        $I->amOnPage('/about');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see("la Vie, tuneefy, l'Univers et le Reste");
    }

    public function testTrends(AcceptanceTester $I)
    {
        $I->amOnPage('/trends');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->see('Les sites de musique que vous utilisez');
    }
}
