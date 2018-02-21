<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use tuneefy\Platform\Platforms\YoutubePlatform;

final class YoutubeVideoTitleParsingTest extends TestCase
{
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
    ];

    public function testTitleParsing()
    {
        $platform = YoutubePlatform::getInstance();

        foreach ($this->strings as $str) {
            $this->assertEquals(
                $str['result'],
                $platform->parseYoutubeMusicVideoTitle($str['source'])[0]
            );
        }
    }
}
