<?php

declare(strict_types=1);

use Codeception\Util\HttpCode;
use tuneefy\Application;
use tuneefy\PlatformEngine;

final class ApiCest
{
    use \Helper\OutputAwareTrait;

    public const TRACK_QUERY = 'sufjan+stevens+should';
    public const TRACK_QUERY_ERROR = 'xzqwqsxws';
    public const ALBUM_QUERY = 'radiohead+computer';
    public const ALBUM_QUERY_ERROR = 'xzqwqsxws';
    public const TRACK_AGGREGATE_QUERY = 'bruno+mars+24K';
    public const TRACK_AGGREGATE_QUERY_ERROR = 'ZERTHJYUKIKHUazzfdegrh';

    public const PERMALINKS = [
        'deezer' => [
            'http://www.deezer.com/track/10444623' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Wupp Dek',
                    'album' => [
                        'title' => 'Thora Vukk',
                        'artist' => 'Robag Wruhme',
                        'picture' => 'https://api.deezer.com/album/955330/image',
                        'safe_title' => 'Thora Vukk',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'deezer' => [
                            'http://www.deezer.com/track/10444623',
                        ],
                    ],
                    'safe_title' => 'Wupp Dek',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Robag Wruhme',
                    'Wupp Dek',
                ],
            ],
            'http://www.deezer.com/fr/album/955330' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Thora Vukk',
                    'artist' => 'Robag Wruhme',
                    'picture' => 'https://api.deezer.com/album/955330/image',
                    'links' => [
                        'deezer' => [
                            'http://www.deezer.com/fr/album/955330',
                        ],
                    ],
                    'safe_title' => 'Thora Vukk',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Robag Wruhme',
                    'Thora Vukk',
                ],
            ],
            'http://www.deezer.com/fr/artist/16948' => [
                'query_words' => [
                    'Robag Wruhme',
                ],
            ],
        ],
        'spotify' => [
            'https://open.spotify.com/track/39ekB6MRScT8azfz3dx804' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Test Transmission',
                    'album' => [
                        'title' => 'Kasabian/Empire',
                        'artist' => 'Kasabian',
                        'picture' => 'https://i.scdn.co/image/ab67616d00001e02ec71b6ad37827ed4ff4c04d1',
                        'safe_title' => 'Kasabian/Empire',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'spotify' => [
                            'https://open.spotify.com/track/39ekB6MRScT8azfz3dx804',
                        ],
                    ],
                    'safe_title' => 'Test Transmission',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Kasabian',
                    'Test Transmission',
                ],
            ],
            'https://open.spotify.com/album/2bRcCP8NYDgO7gtRbkcqdk' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Inni',
                    'artist' => 'Sigur Rós',
                    'picture' => 'https://i.scdn.co/image/ab67616d00001e024eafa5dbb5cd7170d7e0191d',
                    'links' => [
                        'spotify' => [
                            'https://open.spotify.com/album/2bRcCP8NYDgO7gtRbkcqdk',
                        ],
                    ],
                    'safe_title' => 'Inni',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Sigur Rós',
                    'Inni',
                ],
            ],
            'https://open.spotify.com/artist/2m62cc253Xvd9qYQ8d2X3d' => [
                'query_words' => [
                    'The Alan Parsons Project',
                ],
            ],
        ],
        'qobuz' => [
            'https://open.qobuz.com/track/23860968' => [
                'musical_entity' => [
                  'type' => 'track',
                  'title' => 'Techno toujours pareil',
                  'album' => [
                    'title' => 'Mon premier EP',
                    'artist' => "Salut c'est cool",
                    'picture' => 'https://static.qobuz.com/images/covers/59/88/0060254728859_230.jpg',
                    'safe_title' => 'Mon premier EP',
                    'extra_info' => [
                      'is_cover' => false,
                      'is_remix' => false,
                      'acoustic' => false,
                      'context' => [
                      ],
                    ],
                  ],
                  'links' => [
                    'qobuz' => [
                      'https://open.qobuz.com/track/23860968',
                    ],
                  ],
                  'safe_title' => 'Techno toujours pareil',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    "Salut c'est cool",
                    'Techno toujours pareil',
                ],
            ],
            'http://play.qobuz.com/album/0724384260958?track=1065478' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Revolution 909',
                    'album' => [
                        'title' => 'Homework',
                        'artist' => 'Daft Punk',
                        'picture' => 'https://static.qobuz.com/images/covers/58/09/0724384260958_230.jpg',
                        'safe_title' => 'Homework',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'qobuz' => [
                            'https://open.qobuz.com/track/1065478',
                        ],
                    ],
                    'safe_title' => 'Revolution 909',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Daft Punk',
                    'Revolution 909',
                ],
            ],
            'https://play.qobuz.com/album/0724384260958' => [
                'musical_entity' => [
                  'type' => 'album',
                  'title' => 'Homework',
                  'artist' => 'Daft Punk',
                  'picture' => 'https://static.qobuz.com/images/covers/58/09/0724384260958_230.jpg',
                  'links' => [
                    'qobuz' => [
                      'https://open.qobuz.com/album/0724384260958',
                    ],
                  ],
                  'safe_title' => 'Homework',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    'Daft Punk',
                    'Homework',
                  ],
            ],
            'https://play.qobuz.com/artist/36819' => [
                'query_words' => [
                    'Daft Punk',
                  ],
            ],
            'https://www.qobuz.com/fr-fr/album/mon-premier-ep-salut-cest-cool/0060254728859' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Mon premier EP',
                    'artist' => "Salut c'est cool",
                    'picture' => 'https://static.qobuz.com/images/covers/59/88/0060254728859_230.jpg',
                    'links' => [
                        'qobuz' => [
                            'https://open.qobuz.com/album/0060254728859',
                        ],
                    ],
                    'safe_title' => 'Mon premier EP',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    "Salut c'est cool",
                    'Mon premier EP',
                ],
            ],
        ],
        'tidal' => [
            'https://www.tidal.com/track/40358305' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Jay',
                    'album' => [
                        'title' => 'It Follows (Original Motion Picture Soundtrack)',
                        'artist' => 'Disasterpeace',
                        'picture' => 'https://resources.wimpmusic.com/images/fb4f3bf4/76be/4afa/bf1c/79681d92598e/320x320.jpg',
                        'safe_title' => 'It Follows',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [
                              'Original Motion Picture Soundtrack',
                            ],
                        ],
                    ],
                    'links' => [
                        'tidal' => [
                            'http://www.tidal.com/track/40358305',
                        ],
                    ],
                    'safe_title' => 'Jay',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Disasterpeace',
                    'Jay',
                ],
            ],
            'https://tidal.com/browse/album/65425616' => [
                'musical_entity' => [
                  'type' => 'album',
                  'title' => 'A Seat at the Table',
                  'artist' => 'Solange',
                  'picture' => 'https://resources.wimpmusic.com/images/5853a02c/d8e1/4672/8bdd/16d3d2551d00/320x320.jpg',
                  'links' => [
                    'tidal' => [
                      'http://www.tidal.com/album/65425616',
                    ],
                  ],
                  'safe_title' => 'A Seat at the Table',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    'Solange',
                    'A Seat at the Table',
                  ],
            ],
            'https://www.tidal.com/artist/3528326' => [
                'query_words' => [
                    'JAY',
                  ],
            ],
        ],
        'youtube' => [
            'https://www.youtube.com/watch?v=FNdC_3LR2AI' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Stranded',
                    'album' => [
                        'title' => '',
                        'artist' => 'Gojira',
                        'picture' => 'https://i.ytimg.com/vi/FNdC_3LR2AI/mqdefault.jpg',
                        'safe_title' => '',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'youtube' => [
                            'https://www.youtube.com/watch?v=FNdC_3LR2AI',
                        ],
                    ],
                    'safe_title' => 'Stranded',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Stranded',
                    'Gojira',
                ],
            ],
        ],
        'napster' => [
            'http://fr.napster.com/artist/ed-sheeran/album/shape-of-you/track/shape-of-you' => [
                'query_words' => [
                    'shape of you',
                    'ed sheeran',
                  ],
            ],
            'http://fr.napster.com/artist/ed-sheeran/album/shape-of-you' => [
                'query_words' => [
                    'ed sheeran',
                    'shape of you',
                  ],
            ],
            'http://fr.napster.com/artist/ed-sheeran' => [
                'query_words' => [
                    'ed sheeran',
                  ],
            ],
        ],
        // 'soundcloud' => [
        //     'https://soundcloud.com/robbabicz/pink-trees-out-now-on-bedrock' => [
        //         'musical_entity' => [
        //             'type' => 'track',
        //             'title' => 'Robert Babicz - Pink Trees (bedrock)',
        //             'album' => [
        //                 'title' => '',
        //                 'artist' => 'Robert Babicz',
        //                 'picture' => 'https://i1.sndcdn.com/artworks-000003464995-x7smo2-large.jpg',
        //                 'safe_title' => '',
        //                 'extra_info' => [
        //                     'is_cover' => false,
        //                     'is_remix' => false,
        //                     'acoustic' => false,
        //                     'context' => [],
        //                 ],
        //             ],
        //             'links' => [
        //                 'soundcloud' => [
        //                     'https://soundcloud.com/robbabicz/pink-trees-out-now-on-bedrock',
        //                 ],
        //             ],
        //             'safe_title' => 'Robert Babicz',
        //             'extra_info' => [
        //                 'is_cover' => false,
        //                 'is_remix' => false,
        //                 'acoustic' => false,
        //                 'context' => [
        //                     'Pink Trees',
        //                     'bedrock',
        //                 ],
        //             ],
        //         ],
        //         'query_words' => [
        //             'Robert Babicz',
        //             'Robert Babicz',
        //         ],
        //     ],
        // ],
        'mixcloud' => [
            'https://www.mixcloud.com/aphex-twin/' => [
                'query_words' => [
                    'MP 7',
                ],
            ],
            'https://www.mixcloud.com/LeFtOoO/709-season-finale-w-niveau4-lor-du-commun-darrell-cole-new-spaven-mura-masa-budgie/' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => "#709 - Season  Finale w/ #Niveau4 | L'Or Du Commun | Darrell Cole | New Spaven | Mura Masa | Budgie",
                    'album' => [
                        'title' => '',
                        'artist' => 'Lefto Early Bird',
                        'picture' => '',
                        'safe_title' => '',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'mixcloud' => [
                            'https://www.mixcloud.com/LeFtOoO/709-season-finale-w-niveau4-lor-du-commun-darrell-cole-new-spaven-mura-masa-budgie/',
                        ],
                    ],
                    'safe_title' => '#709',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [
                            'Season  Finale w/ #Niveau4 | L\'Or Du Commun | Darrell Cole | New Spaven | Mura Masa | Budgie',
                        ],
                    ],
                ],
                'query_words' => [
                    'Lefto Early Bird',
                    '#709',
                ],
            ],
        ],

        // Blogs / Scrobbling
        'lastfm' => [
            'http://www.lastfm.fr/music/The Clash/London Calling/London Calling' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'London Calling',
                    'album' => [
                        'title' => 'The Singles',
                        'artist' => 'The Clash',
                        'picture' => 'https://lastfm.freetls.fastly.net/i/u/174s/680af088e127e474fc536a5cfad36f3e.png',
                        'safe_title' => 'The Singles',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'lastfm' => [
                            'https://www.last.fm/music/The+Clash/_/London+Calling',
                        ],
                    ],
                    'safe_title' => 'London Calling',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'The Clash',
                    'London Calling',
                ],
            ],
            'http://www.lastfm.fr/music/The Clash/London Calling' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'London Calling',
                    'artist' => 'The Clash',
                    'picture' => 'https://lastfm.freetls.fastly.net/i/u/174s/680af088e127e474fc536a5cfad36f3e.jpg',
                    'safe_title' => 'The Singles',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                    'links' => [
                        'lastfm' => [
                            'https://www.last.fm/music/The+Clash/London+Calling',
                        ],
                    ],
                    'safe_title' => 'London Calling',
                ],
                'query_words' => [
                        'The Clash',
                        'London Calling',
                    ],
            ],
            'http://www.lastfm.fr/music/Sex Pistols' => [
                'query_words' => [
                    'Sex Pistols',
                ],
            ],
        ],

        // Stores
        'itunes' => [
            'https://itunes.apple.com/us/artist/jack-johnson/id909253' => [
                'query_words' => [
                    'Jack Johnson',
                ],
            ],
            'https://music.apple.com/fr/album/in-between-dreams-bonus-track-version/1440857781' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'In Between Dreams (Bonus Track Version)',
                    'artist' => 'Jack Johnson',
                    'picture' => 'https://is1-ssl.mzstatic.com/image/thumb/Music115/v4/44/06/fd/4406fdc0-aab5-e300-82ba-3e5fe81a68a7/00602537868858.rgb.jpg/100x100bb.jpg',
                    'links' => [
                        'itunes' => [
                            'https://music.apple.com/fr/album/in-between-dreams-bonus-track-version/1440857781?uo=4',
                        ],
                    ],
                    'safe_title' => 'In Between Dreams',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [
                            'Bonus Track Version',
                        ],
                    ],
                ],
                'query_words' => [
                    'Jack Johnson',
                    'In Between Dreams',
                ],
            ],
            'https://itunes.apple.com/fr/album/in-between-dreams-bonus-track-version/id1440857781' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'In Between Dreams (Bonus Track Version)',
                    'artist' => 'Jack Johnson',
                    'picture' => 'https://is1-ssl.mzstatic.com/image/thumb/Music115/v4/44/06/fd/4406fdc0-aab5-e300-82ba-3e5fe81a68a7/00602537868858.rgb.jpg/100x100bb.jpg',
                    'links' => [
                        'itunes' => [
                            'https://music.apple.com/fr/album/in-between-dreams-bonus-track-version/1440857781?uo=4',
                        ],
                    ],
                    'safe_title' => 'In Between Dreams',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [
                            'Bonus Track Version',
                        ],
                    ],
                ],
                'query_words' => [
                    'Jack Johnson',
                    'In Between Dreams',
                ],
            ],
        ],
        // 'amazon' => [
        //     'http://www.amazon.com/gp/product/B00GLQQ07E/whatever' => [
        //         'musical_entity' => [
        //             'type' => 'album',
        //             'title' => 'Frozen Heart (From "Frozen"/Soundtrack Version)',
        //             'artist' => 'Cast - Frozen',
        //             'picture' => 'https://images-na.ssl-images-amazon.com/images/I/61FFGp0yGVL._SS160_.jpg',
        //             'links' => [
        //                 'amazon' => [
        //                     'https://www.amazon.com/gp/product/B00GLQQ07E',
        //                 ],
        //             ],
        //             'safe_title' => 'Frozen Heart',
        //             'extra_info' => [
        //                 'is_cover' => false,
        //                 'is_remix' => false,
        //                 'acoustic' => false,
        //                 'context' => [
        //                     'From "Frozen"/Soundtrack Version',
        //                 ],
        //             ],
        //         ],
        //         'query_words' => [
        //             'Cast - Frozen',
        //             'Frozen Heart',
        //         ],
        //     ],
        //     'http://www.amazon.com/dp/B00GLQQ0JW/ref=dm_ws_tlw_trk1' => [
        //         'musical_entity' => [
        //             'type' => 'track',
        //             'title' => 'Frozen Heart (From "Frozen"/Soundtrack Version)',
        //             'album' => [
        //                 'title' => 'Frozen (Deluxe Edition)',
        //                 'artist' => 'Cast - Frozen',
        //                 'picture' => 'https://images-na.ssl-images-amazon.com/images/I/61FFGp0yGVL._SS160_.jpg',
        //                 'safe_title' => 'Frozen',
        //                 'extra_info' => [
        //                     'is_cover' => false,
        //                     'is_remix' => false,
        //                     'acoustic' => false,
        //                     'context' => [
        //                         'Deluxe Edition',
        //                     ],
        //                 ],
        //             ],
        //             'links' => [
        //                 'amazon' => [
        //                     'https://www.amazon.com/gp/product/B00GLQQ0JW',
        //                 ],
        //             ],
        //             'safe_title' => 'Frozen Heart',
        //             'extra_info' => [
        //                 'is_cover' => false,
        //                 'is_remix' => false,
        //                 'acoustic' => false,
        //                 'context' => [
        //                     'From "Frozen"/Soundtrack Version',
        //                 ],
        //             ],
        //         ],
        //         'query_words' => [
        //             'Cast - Frozen',
        //             'Frozen Heart',
        //         ],
        //     ],
        // ],
    ];

    // Just a wrapper around sendGET with a log for debug
    private function sendGET(ApiTester $I, string $url): ApiTester
    {
        $I->sendGET($url);

        $this->write('');
        $this->write('🔗 Testing: '.'http://'.$I->grabHttpHeader('Host').'/v2'.$url);

        return $I;
    }

    public function testDocumentationRedirect(ApiTester $I)
    {
        $I->stopFollowingRedirects();
        $this->sendGET($I, '/');
        $I->seeResponseCodeIs(301);
    }

    public function testListPlatforms(ApiTester $I)
    {
        $this->sendGET($I, '/platforms');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.platforms');

        $platforms = $I->grabDataFromResponseByJsonPath('$.platforms.*');

        $I->assertCount(11, $platforms);

        foreach ($platforms as $key => $platform) {
            $I->assertCount(7, $platform);
            $I->assertNotEquals('', $platform['tag']);
            $I->assertNotEquals('', $platform['type']);
            $I->assertNotEquals('', $platform['name']);
            $I->assertNotEquals('', $platform['homepage']);
            $I->assertNotEquals('', $platform['mainColorAccent']);
            $I->assertCount(2, $platform['enabled']);
            $I->assertCount(3, $platform['capabilities']);
        }
    }

    public function testListPlatformsWithBadType(ApiTester $I)
    {
        $this->sendGET($I, '/platforms?type=coucou');
        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }

    public function testListPlatformsWithType(ApiTester $I)
    {
        $this->sendGET($I, '/platforms?type=streaming');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseJsonMatchesJsonPath('$.platforms');

        $platforms = $I->grabDataFromResponseByJsonPath('$.platforms.*');

        $I->assertCount(8, $platforms);

        foreach ($platforms as $key => $platform) {
            $I->assertCount(7, $platform);
            $I->assertEquals('streaming', $platform['type']);
        }
    }

    public function testLookupPermalink(ApiTester $I)
    {
        $app = new Application();
        $app->configure();
        $engine = $app->getEngine();

        $this->write('');
        foreach (self::PERMALINKS as $platformTag => $permalinks) {
            $platform = $engine->getPlatformByTag($platformTag);
            if (!$platform->isCapableOfLookingUp()) {
                continue;
            }

            foreach ($permalinks as $permalink => $expectedResult) {
                $this->sendGET($I, '/lookup?q='.urlencode($permalink));
                $I->seeResponseCodeIs(HttpCode::OK);

                $result = $I->grabDataFromResponseByJsonPath('.')[0];

                $I->assertArrayHasKey('result', $result);
                $I->assertArrayHasKey('metadata', $result['result']);
                $I->assertArrayHasKey('query_words', $result['result']['metadata']);
                $I->assertEquals(
                    $result['result']['metadata']['query_words'],
                    $expectedResult['query_words']
                );
                if (isset($expectedResult['musical_entity'])) {
                    $I->assertEquals(
                        $result['result']['musical_entity'],
                        $expectedResult['musical_entity']
                    );
                    $I->assertArrayHasKey('platform', $result['result']['metadata']);
                    $I->assertEquals(
                        $result['result']['metadata']['platform'],
                        $platform->getName()
                    );
                } else {
                    $I->assertArrayHasKey('errors', $result);
                }
                $this->write(' →  ✅ '.$platform->getName().': '.$permalink);
            }
        }
    }

    public function testLookupWithNoPermalink(ApiTester $I)
    {
        $this->sendGET($I, '/lookup');
        $I->seeResponseCodeIs(400);
        $I->seeResponseJsonMatchesJsonPath('$.errors');
    }

    public function testSearchTrack(ApiTester $I)
    {
        $app = new Application();
        $app->configure();
        $engine = $app->getEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            if (!$platform->isCapableOfSearchingTracks()) {
                continue;
            }
            $this->sendGET($I, '/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY.'&limit=1');
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            $result = $I->grabDataFromResponseByJsonPath('.')[0];

            if (!isset($result['results'])) {
                $I->assertArrayHasKey('errors', $result);
            // $I->markAsRisky('No results for track search on platform '.$platform->getName());
            } else {
                $I->assertArrayHasKey('results', $result);
                $I->assertCount(1, $result['results']);
            }
        }
    }

    public function testSearchTrackModeOk(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platform = $engine->getPlatformByTag('spotify');

        $this->sendGET($I, '/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY.'&limit=1&mode=eager');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.results');

        $result = $I->grabDataFromResponseByJsonPath('.results');
        $I->assertCount(1, $result);
    }

    public function testSearchTrackWithNoQuery(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $this->sendGET($I, '/search/track/'.$platform->getTag());
            $I->seeResponseCodeIs(400);
            $I->seeResponseIsJson();
            $I->seeResponseJsonMatchesJsonPath('$.errors');
        }
    }

    public function testSearchTrackWithNoMatch(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $this->sendGET($I, '/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY_ERROR.'&limit=2');
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            $result = $I->grabDataFromResponseByJsonPath('.')[0];
            $I->assertArrayHasKey('errors', $result);
        }
    }

    public function testSearchAlbum(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            if (!$platform->isCapableOfSearchingAlbums()) {
                continue;
            }
            $this->sendGET($I, '/search/album/'.$platform->getTag().'?q='.self::ALBUM_QUERY.'&limit=1');
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            $result = $I->grabDataFromResponseByJsonPath('.')[0];

            if (!isset($result['results'])) {
                $I->assertArrayHasKey('errors', $result);
            // $I->markAsRisky('No results for album search on platform '.$platform->getName());
            } else {
                $I->assertArrayHasKey('results', $result);
                $I->assertCount(1, $result['results']);
            }
        }
    }

    public function testSearchAlbumWithNoQuery(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $this->sendGET($I, '/search/album/'.$platform->getTag());
            $I->seeResponseCodeIs(400);
            $I->seeResponseIsJson();
            $I->seeResponseJsonMatchesJsonPath('$.errors');
        }
    }

    public function testSearchAlbumWithNoMatch(ApiTester $I)
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $this->sendGET($I, '/search/album/'.$platform->getTag().'?q='.self::ALBUM_QUERY_ERROR.'&limit=2');
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            $result = $I->grabDataFromResponseByJsonPath('.')[0];
            $I->assertArrayHasKey('errors', $result);
        }
    }

    public function testAggregateTrack(ApiTester $I)
    {
        $engine = new PlatformEngine();

        $this->sendGET($I, '/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.results');
        $result = $I->grabDataFromResponseByJsonPath('$.results.*');

        $I->assertCount(1, $result);

        $I->assertArrayHasKey('musical_entity', $result[0]);
        $I->assertGreaterThan(7, $result[0]['musical_entity']['links']);
        $I->assertGreaterThan(7, $result[0]['metadata']['merges']);
    }

    public function testAggregateAggressive(ApiTester $I)
    {
        $engine = new PlatformEngine();

        $this->sendGET($I, '/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1&aggressive=true&include=deezer,spotify');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.results');
        $result = $I->grabDataFromResponseByJsonPath('$.results.*');

        $I->assertCount(1, $result);

        $I->assertArrayHasKey('musical_entity', $result[0]);
        $I->assertCount(2, $result[0]['musical_entity']['links']);
        $I->assertGreaterThan(0, $result[0]['metadata']['merges']);

        $I->assertArrayHasKey('deezer', $result[0]['musical_entity']['links']);
        $I->assertArrayHasKey('spotify', $result[0]['musical_entity']['links']);
        $I->assertGreaterThan(0, $result[0]['musical_entity']['links']['spotify']);
    }

    public function testAggregateInclude(ApiTester $I)
    {
        $engine = new PlatformEngine();

        $this->sendGET($I, '/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1&include=deezer,spotify');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->seeResponseJsonMatchesJsonPath('$.results');
        $result = $I->grabDataFromResponseByJsonPath('$.results.*');

        $I->assertCount(1, $result);

        $I->assertArrayHasKey('musical_entity', $result[0]);
        $I->assertCount(2, $result[0]['musical_entity']['links']);
        $I->assertEquals(1, $result[0]['metadata']['merges']);

        $I->assertArrayHasKey('deezer', $result[0]['musical_entity']['links']);
        $I->assertArrayHasKey('spotify', $result[0]['musical_entity']['links']);
    }

    public function testAggregateTrackBadQuery(ApiTester $I)
    {
        $engine = new PlatformEngine();

        $this->sendGET($I, '/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY_ERROR.'&limit=1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $result = $I->grabDataFromResponseByJsonPath('.')[0];
        $I->assertArrayHasKey('errors', $result);
    }
}
