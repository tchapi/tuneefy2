<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;

/**
 * @covers \MusicalEntity
 */
final class MusicalEntityTest extends TestCase
{
    private $parsableMusicalStrings = [
        "A State Of Trance (ASOT 810) (About 'This Is A Test')" => [
            'safe_title' => 'A State Of Trance',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['ASOT 810', 'About \'This Is A Test\''],
            ],
        ],
        "Ana's Song (Open Fire)" => [
            'safe_title' => "Ana's Song",
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Open Fire'],
            ],
        ],
        'Late Night Tales: Belle and Sebastian (Continuous Mix)' => [
            'safe_title' => 'Late Night Tales: Belle and Sebastian',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Continuous Mix'],
            ],
        ],
        'Late Night Tales: Belle and Sebastian, Vol. 2 (Sampler)' => [
            'safe_title' => 'Late Night Tales: Belle and Sebastian, Vol. 2',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Sampler'],
            ],
        ],
        'Back to the Future — Back to the Future' => [
            'safe_title' => 'Back to the Future',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Back to the Future'],
            ],
        ],
        'Piano Sonata No.14 in C Sharp Minor Op.27 No.2 – Moonlight: 1. Adagio sostenuto' => [
            'safe_title' => 'Piano Sonata No.14 in C Sharp Minor Op.27 No.2',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Moonlight: 1. Adagio sostenuto'],
            ],
        ],
        'Nocturne Op.9 - No.2' => [
            'safe_title' => 'Nocturne Op.9',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['No.2'],
            ],
        ],
        'Call On Me (Ryan Extended Remix)' => [
            'safe_title' => 'Call On Me',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Ryan Extended Remix'],
            ],
        ],
        'Light it up (feat. Nyla & Fuse ODG) (Remix)' => [
            'safe_title' => 'Light it up',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'featuring' => 'Nyla & Fuse ODG',
                'context' => ['feat. Nyla & Fuse ODG', 'Remix'],
            ],
        ],
        'Shape of You (Major Lazer Remix) [feat. Nyla & Kali]' => [
            'safe_title' => 'Shape of You',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'featuring' => 'Nyla & Kali',
                'context' => ['Major Lazer Remix', 'feat. Nyla & Kali'],
            ],
        ],
        'Cheerleader (Felix Jaehn Radio Edit)' => [
            'safe_title' => 'Cheerleader',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'edit' => 'Felix Jaehn Radio Edit',
                'acoustic' => false,
                'context' => ['Felix Jaehn Radio Edit'],
            ],
        ],
        'Midnight City (Remix EP)' => [
            'safe_title' => 'Midnight City',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Remix EP'],
            ],
        ],
        'Human (Acoustic)' => [
            'safe_title' => 'Human',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => true,
                'context' => ['Acoustic'],
            ],
        ],
        'My World (Edition collector)' => [
            'safe_title' => 'My World',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Edition collector'],
            ],
        ],
        "Baba O'Riley (Original Album Version)" => [
            'safe_title' => "Baba O'Riley",
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Original Album Version'],
            ],
        ],
        'When you look at me (Original Version/Radio Edit)' => [
            'safe_title' => 'When you look at me',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'edit' => 'Original Version/Radio Edit',
                'acoustic' => false,
                'context' => ['Original Version/Radio Edit'],
            ],
        ],
        'Paradise (Coldplay Acoustic Cover)' => [
            'safe_title' => 'Paradise',
            'extra_info' => [
                'is_cover' => true,
                'is_remix' => false,
                'acoustic' => true,
                'context' => ['Coldplay Acoustic Cover'],
            ],
        ],
        'Classic Covers Vol.2' => [
            'safe_title' => 'Classic Covers Vol.2',
            'extra_info' => [
                'is_cover' => false, // Would be too difficult to assess
                'is_remix' => false,
                'acoustic' => false,
                'context' => [],
            ],
        ],
        'Josephine - Radio Edit (Acoustique)' => [
            'safe_title' => 'Josephine',
            'extra_info' => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => true,
                'edit' => 'Radio Edit',
                'context' => ['Radio Edit', 'Acoustique'],
            ],
        ],
    ];

    public function testParseTracks()
    {
        foreach ($this->parsableMusicalStrings as $key => $value) {
            $this->assertEquals(
              $value,
              TrackEntity::parse($key)
            );
        }
    }

    public function testSetIntrospectedTrack()
    {
        $album_entity = new AlbumEntity('test album (extra album)');
        $entity = new TrackEntity('test title (extra)', $album_entity);

        $this->assertEquals(
            [
                'type' => 'track',
                'title' => 'test title (extra)',
                'album' => [
                    'title' => 'test album (extra album)',
                    'artist' => '',
                    'picture' => '',
                    'safe_title' => 'test album',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => ['extra album'],
                    ],
                ],
                'safe_title' => 'test title',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $entity->toArray()
        );

        $this->assertTrue(
            $entity->isIntrospected()
        );

        $entity->introspect(['context' => ['test']]);

        // Should not change since it's already introspected
        $this->assertEquals(
            [
                'type' => 'track',
                'title' => 'test title (extra)',
                'album' => [
                    'title' => 'test album (extra album)',
                    'artist' => '',
                    'picture' => '',
                    'safe_title' => 'test album',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => ['extra album'],
                    ],
                ],
                'safe_title' => 'test title',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $entity->toArray()
        );

        $this->assertEquals(
            [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['extra'],
            ],
            $entity->getExtraInfo()
        );

        $this->assertFalse(
            $entity->isCover()
        );

        $this->assertFalse(
            $entity->isAcoustic()
        );

        $this->assertFalse(
            $entity->isRemix()
        );

        $this->assertFalse(
            $entity->isEdit()
        );
    }

    public function testSetIntrospectedAlbum()
    {
        $entity = new AlbumEntity('test title (extra)');

        $this->assertEquals(
            [
                'type' => 'album',
                'title' => 'test title (extra)',
                'artist' => '',
                'picture' => '',
                'safe_title' => 'test title',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $entity->toArray()
        );

        $this->assertTrue(
            $entity->isIntrospected()
        );

        $entity->introspect(['context' => ['test']]);

        // Should not change since it's already introspected
        $this->assertEquals(
            [
                'type' => 'album',
                'title' => 'test title (extra)',
                'artist' => '',
                'picture' => '',
                'safe_title' => 'test title',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $entity->toArray()
        );

        $this->assertEquals(
            [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['extra'],
            ],
            $entity->getExtraInfo()
        );

        $this->assertFalse(
            $entity->isCover()
        );

        $this->assertFalse(
            $entity->isAcoustic()
        );

        $this->assertFalse(
            $entity->isRemix()
        );

        $this->assertFalse(
            $entity->isEdit()
        );
    }

    public function testLinks()
    {
        $entity = new AlbumEntity('test title (extra)');

        $entity->addLink('platform', 'link');

        $this->assertEquals(
            ['platform' => ['link']],
            $entity->getLinks()
        );

        $this->assertEquals(
            1,
            $entity->countLinkedPlatforms()
        );

        $entity->addLink('platform', 'link2');
        $entity->addLink('platform2', 'link');

        $this->assertEquals(
            [
                'platform' => ['link', 'link2'],
                'platform2' => ['link'],
            ],
            $entity->getLinks()
        );

        $this->assertEquals(
            2,
            $entity->countLinkedPlatforms()
        );
    }

    public function testMergeAlbum()
    {
        $a = new AlbumEntity('test title a (extra)');
        $b = new AlbumEntity('test title b (extra)');

        $c = AlbumEntity::merge($a, $b);

        $this->assertEquals(
            [
                'type' => 'album',
                'title' => 'test title a (extra)',
                'artist' => '',
                'picture' => '',
                'safe_title' => 'test title a',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $c->toArray()
        );
    }

    public function testMergeTrack()
    {
        $a_album = new AlbumEntity('test title a album (extra)');
        $a = new TrackEntity('test title a (extra)', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (extra)', $b_album);

        $c = TrackEntity::merge($a, $b);

        $this->assertEquals(
            [
                'type' => 'track',
                'title' => 'test title a (extra)',
                'album' => [
                    'title' => 'test title a album (extra)',
                    'artist' => '',
                    'picture' => '',
                    'safe_title' => 'test title a album',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => ['extra'],
                    ],
                ],
                'safe_title' => 'test title a',
                'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra'],
                ],
            ],
            $c->toArray()
        );
    }

    public function testMergeTrackNotForced1()
    {
        $a_album = new AlbumEntity('test title a album (extra)');
        $a = new TrackEntity('test title a (acoustic)', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (extra)', $b_album);

        $this->expectException(tuneefy\MusicalEntity\MusicalEntityMergeException::class);
        $c = TrackEntity::merge($a, $b); 
    }

    public function testMergeTrackNotForced2()
    {
        $a_album = new AlbumEntity('test title a album (extra)');
        $a = new TrackEntity('test title a', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (cover)', $b_album);

        $this->expectException(tuneefy\MusicalEntity\MusicalEntityMergeException::class);
        $c = TrackEntity::merge($a, $b);
    }

    public function testMergeTrackNotForced3()
    {
        $a_album = new AlbumEntity('test title a album (cover)');
        $a = new TrackEntity('test title a (acoustic)', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (extra)', $b_album);

        $this->expectException(tuneefy\MusicalEntity\MusicalEntityMergeException::class);
        $c = TrackEntity::merge($a, $b);
    }

    public function testMergeTrackNotForced4()
    {
        $a_album = new AlbumEntity('test title a album (remix)');
        $a = new TrackEntity('test title a (extra)', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (remix)', $b_album);

        $this->expectException(tuneefy\MusicalEntity\MusicalEntityMergeException::class);
        $c = TrackEntity::merge($a, $b);
    }

    public function testMergeTrackForced()
    {
        $a_album = new AlbumEntity('test title a album (remix)');
        $a = new TrackEntity('test title a (extra)', $a_album);
        $b_album = new AlbumEntity('test title b album (extra)');
        $b = new TrackEntity('test title b (cover)', $b_album);

        $c = TrackEntity::merge($a, $b, true);

        $this->assertEquals(
            [
                'type' => 'track',
                'title' => 'test title a (extra)',
                'album' => [
                    'title' => 'test title a album (remix)',
                    'artist' => '',
                    'picture' => '',
                    'safe_title' => 'test title a album',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => true,
                        'acoustic' => false,
                        'context' => ['remix', 'extra'],
                    ],
                ],
                'safe_title' => 'test title a',
                'extra_info' => [
                    'is_cover' => true,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => ['extra', 'cover'],
                ],
            ],
            $c->toArray()
        );
    }

    public function testMatchingHash()
    {
        $a_album = new AlbumEntity(' The Test Album');
        $a = new TrackEntity('My song (Explicit)', $a_album);
        $b_album = new AlbumEntity('the test album');
        $b = new TrackEntity('my song', $b_album);

        $this->assertEquals(
            $a->getHash(),
            $b->getHash()
        );
    }

    public function testNonMatchingHash()
    {
        $a_album = new AlbumEntity(' The Test Album');
        $a = new TrackEntity('My song (Explicit)', $a_album);
        $b_album = new AlbumEntity('my song');
        $b = new TrackEntity('the test album', $b_album);

        $this->assertNotEquals(
            $a->getHash(),
            $b->getHash()
        );
    }
}
