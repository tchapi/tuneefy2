<?php

namespace Tests\Unit;

use App\Services\Platforms\YoutubePlatform;
use Tests\Support\UnitTester;

final class YoutubeVideoTitleParsingTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    private $strings = [
        [
            'source' => 'ARTIST - TITLE [Official Video]',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (Official)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (Officiel)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (CLIP OFFICIEL)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (CLIP)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (video officielle)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (vidéo officielle)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (Official Audio)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (Lyrics)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE Official Video',
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE [Official Audio] (HD)',
            'result' => 'TITLE',
        ],
        [
            'source' => 'Lana Del Rey - Video Games',
            'result' => 'Video Games',
        ],
        [
            'source' => "ARTIST \u{2013} TITLE (Official Video)",
            'result' => 'TITLE',
        ],
        [
            'source' => 'ARTIST - TITLE (Live at Glastonbury)',
            'result' => 'TITLE (Live at Glastonbury)',
        ],
        [
            'source' => 'JUST A TITLE WITHOUT ARTIST',
            'result' => null,
        ],
    ];

    public function testTitleParsing()
    {
        $platform = new YoutubePlatform('test', 'test');

        foreach ($this->strings as $str) {
            $this->assertEquals(
                $str['result'],
                $platform->parseYoutubeMusicVideoTitle($str['source'])[0]
            );
        }
    }
}
