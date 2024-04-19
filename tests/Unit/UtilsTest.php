<?php

namespace Tests\Unit;

use App\Utils\Utils;
use Tests\Support\UnitTester;

final class UtilsTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    public function testToUId()
    {
        $utils = new Utils(11111);

        $result = base_convert((string) (1000 * 11111), 10, 36);
        $this->assertEquals(
            $utils->toUid(1000),
            $result
        );

        $this->assertEquals(
            $utils->fromUid($result),
            1000
        );
    }

    public function testSanitize()
    {
        $string = "Je suis une chaîne prête pour la sanitization n'est-ce pas ?";

        $this->assertEquals(
            Utils::sanitize($string),
            'je-suis-une-chaine-prete-pour-la-sanitization-n-est-ce-pas-'
        );
    }

    public function testEllipsis()
    {
        $string = "Je suis une chaîne prête pour l'ellipse n'est-ce pas ?";

        $this->assertEquals(
            Utils::ellipsis($string),
            "Je suis une chaîne prête pour l'ellipse n'est-ce pas ?"
        );

        $this->assertEquals(
            Utils::ellipsis($string, 12, '...'),
            'Je suis ...'
        );

        $this->assertEquals(
            Utils::ellipsis($string, 14, '...'),
            'Je suis une ...'
        );
    }

    public function testFlattenMetaXMLNodes()
    {
        $xml = '<meta rel="artist">2Chainz</meta>';
        $this->assertEquals(
            Utils::flattenMetaXMLNodes($xml),
            '<meta rel="artist">2Chainz</meta>'
        );

        $xml = '<meta rel="namespace/artist">2Chainz</meta>';
        $this->assertEquals(
            Utils::flattenMetaXMLNodes($xml),
            '<artist>2Chainz</artist>'
        );
    }

    public function testIndexScore()
    {
        $this->assertEquals(
            Utils::indexScore(0),
            1
        );

        $this->assertEquals(
            Utils::indexScore(10),
            0.5
        );

        $this->assertEquals(
            Utils::indexScore(100),
            0.09
        );
    }

    public function testFlatten()
    {
        $tokens = ['token1', 'token2', 'other Token'];

        $this->assertEquals(
            Utils::flatten($tokens),
            'token1token2othertoken'
        );
    }
}
