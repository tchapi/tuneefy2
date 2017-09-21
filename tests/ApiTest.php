<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use tuneefy\PlatformEngine;

/**
 * @covers \Api
 */
final class ApiTest extends TestCase
{
    protected $app;

    const TRACK_QUERY = 'sufjan+stevens+should';
    const TRACK_QUERY_ERROR = 'xzqwqsxws';
    const ALBUM_QUERY = 'radiohead+computer';
    const ALBUM_QUERY_ERROR = 'xzqwqsxws';
    const TRACK_AGGREGATE_QUERY = 'bruno+mars+24K';
    const TRACK_AGGREGATE_QUERY_ERROR = 'ZERTHJYUKIKHUazzfdegrh';

    const PERMALINKS = [
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
            'https://open.spotify.com/track/5jhJur5n4fasblLSCOcrTp' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Test Transmission',
                    'album' => [
                        'title' => 'Kasabian',
                        'artist' => 'Kasabian',
                        'picture' => 'https://i.scdn.co/image/a9e6fab74c9840ae4194b2cd94f13a4731adbf72',
                        'safe_title' => 'Kasabian',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'spotify' => [
                            'https://open.spotify.com/track/5jhJur5n4fasblLSCOcrTp',
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
                    'picture' => 'https://i.scdn.co/image/f9a45d06203f6415eeba7f27ed2387eea1f3f9dd',
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
        'groove' => [
            'http://music.xbox.com/Album/C954F807-0100-11DB-89CA-0019B92A3933' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Give Me Strength: The ‘74/’75 Studio Recordings',
                    'artist' => 'Eric Clapton',
                    'picture' => 'https://musicimage.xboxlive.com/content/music.c954f807-0100-11db-89ca-0019b92a3933/image?locale=fr-FR',
                    'links' => [
                        'groove' => [
                            'https://music.microsoft.com/album/eric-clapton/give-me-strength-the-‘74-75-studio-recordings/c954f807-0100-11db-89ca-0019b92a3933?partnerID=AppId:00000000441C7CAD',
                        ],
                    ],
                    'safe_title' => 'Give Me Strength: The ‘74/’75 Studio Recordings',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Eric Clapton',
                    'Give Me Strength: The ‘74/’75 Studio Recordings',
                ],
            ],
            'http://music.xbox.com/Track/87CF3706-0100-11DB-89CA-0019B92A3933' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Test',
                    'album' => [
                        'title' => 'Little Dragon',
                        'artist' => 'Little Dragon',
                        'picture' => 'https://musicimage.xboxlive.com/content/music.03732806-0100-11db-89ca-0019b92a3933/image?locale=fr-FR',
                        'safe_title' => 'Little Dragon',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'groove' => [
                            'https://music.microsoft.com/track/little-dragon/little-dragon/test/87cf3706-0100-11db-89ca-0019b92a3933?partnerID=AppId:00000000441C7CAD',
                        ],
                    ],
                    'safe_title' => 'Test',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Little Dragon',
                    'Test',
                ],
            ],
        ],
        'qobuz' => [
            'http://open.qobuz.com/track/23860968' => [
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
                      'http://open.qobuz.com/track/23860968',
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
                            'http://open.qobuz.com/track/1065478',
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
            'http://play.qobuz.com/album/0724384260958' => [
                'musical_entity' => [
                  'type' => 'album',
                  'title' => 'Homework',
                  'artist' => 'Daft Punk',
                  'picture' => 'https://static.qobuz.com/images/covers/58/09/0724384260958_230.jpg',
                  'links' => [
                    'qobuz' => [
                      'http://open.qobuz.com/album/0724384260958',
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
            'http://play.qobuz.com/artist/36819' => [
                'query_words' => [
                    'Daft Punk',
                  ],
            ],
            'http://www.qobuz.com/fr-fr/album/mon-premier-ep-salut-cest-cool/0060254728859' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Mon premier EP',
                    'artist' => "Salut c'est cool",
                    'picture' => 'https://static.qobuz.com/images/covers/59/88/0060254728859_230.jpg',
                    'links' => [
                        'qobuz' => [
                            'http://open.qobuz.com/album/0060254728859',
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
            'http://www.tidal.com/track/40358305' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Jay',
                    'album' => [
                        'title' => 'It Follows',
                        'artist' => 'Disasterpeace',
                        'picture' => 'http://resources.wimpmusic.com/images/fb4f3bf4/76be/4afa/bf1c/79681d92598e/320x320.jpg',
                        'safe_title' => 'It Follows',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
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
            'http://www.tidal.com/album/571179' => [
                'musical_entity' => [
                  'type' => 'album',
                  'title' => 'JAY Z: MTV Unplugged',
                  'artist' => 'JAY-Z',
                  'picture' => 'http://resources.wimpmusic.com/images/f1494811/b30e/45eb/8fe0/40137f1c0e58/320x320.jpg',
                  'links' => [
                    'tidal' => [
                      'http://www.tidal.com/album/571179',
                    ],
                  ],
                  'safe_title' => 'JAY Z: MTV Unplugged',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    'JAY-Z',
                    'JAY Z: MTV Unplugged',
                  ],
            ],
            'http://www.tidal.com/artist/3528326' => [
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
        'googleplay' => [
            'https://play.google.com/store/music/album/James_McAlister_Planetarium?id=Bew3avws2eysvwcmkxwgu5s3rhm' => [
                'musical_entity' => [
                  'type' => 'album',
                  'title' => 'Planetarium',
                  'artist' => 'Sufjan Stevens, Nico Muhly, Bryce Dessner, James McAlister',
                  'picture' => 'http://lh3.googleusercontent.com/YTbUe1gyfP64zYebOO14Py38XziO-IRkt6UcYsk4AzT3Zymyxr-kFfkzws_uiLjBuLFp-s6FkmQ',
                  'links' => [
                    'googleplay' => [
                      'https://play.google.com/store/music/album?id=Bew3avws2eysvwcmkxwgu5s3rhm',
                    ],
                  ],
                  'safe_title' => 'Planetarium',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    'Sufjan Stevens, Nico Muhly, Bryce Dessner, James McAlister',
                    'Planetarium',
                  ],
            ],
            'https://play.google.com/store/music/album?id=Bbebqssprhgc27hq6xlqzrm45g4&tid=song-Ttbq3os2bblfjndnztz43sf2c2i' => [
                'musical_entity' => [
                  'type' => 'track',
                  'title' => 'Break On Through',
                  'album' => [
                    'title' => 'The Very Best Of The Doors',
                    'artist' => 'The Doors',
                    'picture' => 'http://lh6.ggpht.com/9Rb8TpazGgeXcueeNzr6fZCAFMECT8MyEAoPGUa3mpTYYOdXxh5BIKlKC13zRks-HRHb7PtS',
                    'safe_title' => 'The Very Best Of The Doors',
                    'extra_info' => [
                      'is_cover' => false,
                      'is_remix' => false,
                      'acoustic' => false,
                      'context' => [
                      ],
                    ],
                  ],
                  'links' => [
                    'googleplay' => [
                      'https://play.google.com/store/music/album?id=Bbebqssprhgc27hq6xlqzrm45g4&tid=song-Ttbq3os2bblfjndnztz43sf2c2i',
                    ],
                  ],
                  'safe_title' => 'Break On Through',
                  'extra_info' => [
                    'is_cover' => false,
                    'is_remix' => false,
                    'acoustic' => false,
                    'context' => [
                    ],
                  ],
                ],
                'query_words' => [
                    'The Doors',
                    'Break On Through',
                  ],
            ],
            'https://play.google.com/store/music/artist/James_McAlister?id=Anop7xijqkhvkjc4q7mo6drwyu4' => [
                'query_words' => [
                    'James McAlister',
                  ],
            ],
        ],
        'soundcloud' => [
            'https://soundcloud.com/robbabicz/pink-trees-out-now-on-bedrock' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Robert Babicz - Pink Trees (bedrock)',
                    'album' => [
                        'title' => '',
                        'artist' => 'Robert Babicz',
                        'picture' => 'https://i1.sndcdn.com/artworks-000003464995-x7smo2-large.jpg',
                        'safe_title' => '',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [],
                        ],
                    ],
                    'links' => [
                        'soundcloud' => [
                            'https://soundcloud.com/robbabicz/pink-trees-out-now-on-bedrock',
                        ],
                    ],
                    'safe_title' => 'Robert Babicz',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [
                            'Pink Trees',
                            'bedrock',
                        ],
                    ],
                ],
                'query_words' => [
                    'Robert Babicz',
                    'Robert Babicz',
                ],
            ],
        ],
        'mixcloud' => [
            'https://www.mixcloud.com/aphex-twin/' => [
                'query_words' => [
                    'Aphex Twin',
                ],
            ],
            'https://www.mixcloud.com/LeFtOoO/709-season-finale-w-niveau4-lor-du-commun-darrell-cole-new-spaven-mura-masa-budgie/' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => "#709 - Season  Finale w/ #Niveau4 | L'Or Du Commun | Darrell Cole | New Spaven | Mura Masa | Budgie",
                    'album' => [
                        'title' => '',
                        'artist' => 'LeFtO',
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
                    'LeFtO',
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
                        'picture' => 'https://lastfm-img2.akamaized.net/i/u/174s/7d8eeb8f69e84736ab2cd659c03a1581.png',
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
                    'picture' => 'https://lastfm-img2.akamaized.net/i/u/174s/7d8eeb8f69e84736ab2cd659c03a1581.png',
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
            'https://itunes.apple.com/us/album/weezer/id1136784464' => [
                 'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Weezer',
                    'artist' => 'Weezer',
                    'picture' => 'http://is1.mzstatic.com/image/thumb/Music60/v4/f8/52/ef/f852efd1-3221-6ce7-d5aa-e320a9d8879e/source/100x100bb.jpg',
                    'links' => [
                        'itunes' => [
                            'https://itunes.apple.com/us/album/weezer/id1136784464?uo=4',
                        ],
                    ],
                    'safe_title' => 'Weezer',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Weezer',
                    'Weezer',
                ],
            ],
        ],
        'amazon' => [
            'http://www.amazon.com/gp/product/B00GLQQ07E/whatever' => [
                'musical_entity' => [
                    'type' => 'album',
                    'title' => 'Frozen (Deluxe Edition)',
                    'artist' => 'Various artists',
                    'picture' => 'https://images-na.ssl-images-amazon.com/images/I/61gYerL61JL._SS160_.jpg',
                    'links' => [
                        'amazon' => [
                            'http://www.amazon.com/gp/product/B00GLQQ07E',
                        ],
                    ],
                    'safe_title' => 'Frozen',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [
                            'Deluxe Edition',
                        ],
                    ],
                ],
                'query_words' => [
                    'Various artists',
                    'Frozen',
                ],
            ],
            'http://www.amazon.com/dp/B00GLQQ0JW/ref=dm_ws_tlw_trk1' => [
                'musical_entity' => [
                    'type' => 'track',
                    'title' => 'Frozen Heart',
                    'album' => [
                        'title' => 'Frozen (Deluxe Edition)',
                        'artist' => 'Cast - Frozen',
                        'picture' => 'https://images-na.ssl-images-amazon.com/images/I/61gYerL61JL._SS160_.jpg',
                        'safe_title' => 'Frozen',
                        'extra_info' => [
                            'is_cover' => false,
                            'is_remix' => false,
                            'acoustic' => false,
                            'context' => [
                                'Deluxe Edition',
                            ],
                        ],
                    ],
                    'links' => [
                        'amazon' => [
                            'http://www.amazon.com/gp/product/B00GLQQ0JW',
                        ],
                    ],
                    'safe_title' => 'Frozen Heart',
                    'extra_info' => [
                        'is_cover' => false,
                        'is_remix' => false,
                        'acoustic' => false,
                        'context' => [],
                    ],
                ],
                'query_words' => [
                    'Cast - Frozen',
                    'Frozen Heart',
                ],
            ],
        ],
    ];

    public function setUp()
    {
        $this->app = new tuneefy\Application();
        $this->app->configure();
    }

    private function get(string $endpoint)
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api'.$endpoint,
            ]);
        $req = Request::createFromEnvironment($env);
        $req = $req->withHeader('Accept', 'application/json');
        $this->app->set('request', $req);
        $this->app->set('response', new Response());

        return $this->app->run(true);
    }

    public function testDocumentation()
    {
        $response = $this->get(''); // "/api"
        $this->assertSame($response->getStatusCode(), 200);
    }

    public function testListPlatforms()
    {
        $response = $this->get('/platforms');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        $this->assertArrayHasKey('platforms', $result);

        $result = $result['platforms'];

        // 14 platforms
        $this->assertEquals(count($result), 13);

        foreach ($result as $key => $platform) {
            $this->assertCount(7, $platform);
            $this->assertNotEquals('', $platform['tag']);
            $this->assertNotEquals('', $platform['type']);
            $this->assertNotEquals('', $platform['name']);
            $this->assertNotEquals('', $platform['homepage']);
            $this->assertNotEquals('', $platform['mainColorAccent']);
            $this->assertCount(2, $platform['enabled']);
            $this->assertCount(3, $platform['capabilities']);
        }
    }

    public function testListPlatformsWithBadType()
    {
        $response = $this->get('/platforms?type=coucou');
        $this->assertSame($response->getStatusCode(), 400);

        $result = json_decode($response->getBody()->__toString(), true);

        $this->assertArrayHasKey('errors', $result);
    }

    public function testListPlatformsWithType()
    {
        $response = $this->get('/platforms?type=streaming');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        $this->assertArrayHasKey('platforms', $result);

        $result = $result['platforms'];

        // 14 platforms
        $this->assertEquals(count($result), 10);

        foreach ($result as $key => $platform) {
            $this->assertCount(7, $platform);
            $this->assertEquals('streaming', $platform['type']);
        }
    }

    public function testLookupPermalink()
    {
        $engine = new PlatformEngine();

        foreach (self::PERMALINKS as $platformTag => $permalinks) {
            $platform = $engine->getPlatformByTag($platformTag);
            if (!$platform->isCapableOfLookingUp()) {
                continue;
            }

            foreach ($permalinks as $permalink => $expectedResult) {
                $response = $this->get('/lookup?q='.urlencode($permalink));
                $this->assertSame($response->getStatusCode(), 200);

                $result = json_decode($response->getBody()->__toString(), true);

                $this->assertArrayHasKey('result', $result);
                $this->assertArrayHasKey('metadata', $result['result']);
                $this->assertArrayHasKey('query_words', $result['result']['metadata']);
                $this->assertEquals(
                    $result['result']['metadata']['query_words'],
                    $expectedResult['query_words']
                );
                if (isset($expectedResult['musical_entity'])) {
                    $this->assertEquals(
                        $result['result']['musical_entity'],
                        $expectedResult['musical_entity']
                    );
                    $this->assertArrayHasKey('platform', $result['result']['metadata']);
                    $this->assertEquals(
                        $result['result']['metadata']['platform'],
                        $platform->getName()
                    );
                } else {
                    $this->assertArrayHasKey('errors', $result);
                }
                //error_log($permalink.' ✅');
            }
        }
    }

    public function testLookupWithNoPermalink()
    {
        $response = $this->get('/lookup');
        $this->assertSame($response->getStatusCode(), 400);

        $result = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testSearchTrack()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            if (!$platform->isCapableOfSearchingTracks()) {
                continue;
            }
            $response = $this->get('/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY.'&limit=1');
            $this->assertSame($response->getStatusCode(), 200);

            $result = json_decode($response->getBody()->__toString(), true);
            if (!isset($result['results'])) {
                $this->assertArrayHasKey('errors', $result);
                $this->markAsRisky('No results for track search on platform '.$platform->getName());
            } else {
                $this->assertArrayHasKey('results', $result);
                $this->assertCount(1, $result['results']);
            }
        }
    }

    public function testSearchTrackModeOk()
    {
        $engine = new PlatformEngine();
        $platform = $engine->getPlatformByTag('spotify');

        $response = $this->get('/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY.'&limit=1&mode=eager');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
    }

    public function testSearchTrackWithNoQuery()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $response = $this->get('/search/track/'.$platform->getTag());
            $this->assertSame($response->getStatusCode(), 400);

            $result = json_decode($response->getBody()->__toString(), true);
            $this->assertArrayHasKey('errors', $result);
        }
    }

    public function testSearchTrackWithNoMatch()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $response = $this->get('/search/track/'.$platform->getTag().'?q='.self::TRACK_QUERY_ERROR.'&limit=2');
            $this->assertSame($response->getStatusCode(), 200);

            $result = json_decode($response->getBody()->__toString(), true);
            if ($platform->getTag() === 'napster') {
                // Napster always returns something
                $this->assertArrayHasKey('results', $result);
            } else {
                $this->assertArrayHasKey('errors', $result);
            }
        }
    }

    public function testSearchAlbum()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            if (!$platform->isCapableOfSearchingAlbums()) {
                continue;
            }
            $response = $this->get('/search/album/'.$platform->getTag().'?q='.self::ALBUM_QUERY.'&limit=1');
            $this->assertSame($response->getStatusCode(), 200);

            $result = json_decode($response->getBody()->__toString(), true);
            if (!isset($result['results'])) {
                $this->assertArrayHasKey('errors', $result);
                $this->markAsRisky('No results for album search on platform '.$platform->getName());
            } else {
                $this->assertArrayHasKey('results', $result);
                $this->assertCount(1, $result['results']);
            }
        }
    }

    public function testSearchAlbumWithNoQuery()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $response = $this->get('/search/album/'.$platform->getTag());
            $this->assertSame($response->getStatusCode(), 400);

            $result = json_decode($response->getBody()->__toString(), true);
            $this->assertArrayHasKey('errors', $result);
        }
    }

    public function testSearchAlbumWithNoMatch()
    {
        $engine = new PlatformEngine();
        $platforms = $engine->getAllPlatforms();

        foreach ($platforms as $platform) {
            $response = $this->get('/search/album/'.$platform->getTag().'?q='.self::ALBUM_QUERY_ERROR.'&limit=2');
            $this->assertSame($response->getStatusCode(), 200);

            $result = json_decode($response->getBody()->__toString(), true);
            if ($platform->getTag() === 'napster') {
                // Napster always returns something
                $this->assertArrayHasKey('results', $result);
            } else {
                $this->assertArrayHasKey('errors', $result);
            }
        }
    }

    public function testAggregateTrack()
    {
        $engine = new PlatformEngine();

        $response = $this->get('/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);

        $this->assertArrayHasKey('musical_entity', $result['results'][0]);
        $this->assertGreaterThan(7, $result['results'][0]['musical_entity']['links']);
        $this->assertGreaterThan(7, $result['results'][0]['metadata']['merges']);
    }

    public function testAggregateAggressive()
    {
        $engine = new PlatformEngine();

        $response = $this->get('/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1&aggressive=true&include=deezer,spotify');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);

        $this->assertArrayHasKey('musical_entity', $result['results'][0]);
        $this->assertCount(2, $result['results'][0]['musical_entity']['links']);
        $this->assertGreaterThan(0, $result['results'][0]['metadata']['merges']);

        $this->assertArrayHasKey('deezer', $result['results'][0]['musical_entity']['links']);
        $this->assertArrayHasKey('spotify', $result['results'][0]['musical_entity']['links']);
        $this->assertGreaterThan(0, $result['results'][0]['musical_entity']['links']['spotify']);
    }

    public function testAggregateInclude()
    {
        $engine = new PlatformEngine();

        $response = $this->get('/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY.'&limit=1&include=deezer,spotify');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);

        $this->assertArrayHasKey('musical_entity', $result['results'][0]);
        $this->assertCount(2, $result['results'][0]['musical_entity']['links']);
        $this->assertEquals(1, $result['results'][0]['metadata']['merges']);

        $this->assertArrayHasKey('deezer', $result['results'][0]['musical_entity']['links']);
        $this->assertArrayHasKey('spotify', $result['results'][0]['musical_entity']['links']);
    }

    public function testAggregateTrackBadQuery()
    {
        $engine = new PlatformEngine();

        $response = $this->get('/aggregate/track?q='.self::TRACK_AGGREGATE_QUERY_ERROR.'&limit=1');
        $this->assertSame($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);

        $this->assertArrayHasKey('musical_entity', $result['results'][0]);
        $this->assertCount(1, $result['results'][0]['musical_entity']['links']);
        // Only the Napster platform returns a result
        $this->assertArrayHasKey('napster', $result['results'][0]['musical_entity']['links']);
    }
}
