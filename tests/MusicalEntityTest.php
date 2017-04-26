<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use tuneefy\MusicalEntity\MusicalEntity;

/**
 * @covers MusicalEntity
 */
final class MusicalEntityTest extends TestCase
{
    private $parsableMusicalStrings = [
        "A State Of Trance (ASOT 810) (About 'This Is A Test')" => [
            "safe_title" => "A State Of Trance",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['ASOT 810', 'About \'This Is A Test\''],
            ],
        ],
        "Ana's Song (Open Fire)" => [
            "safe_title" => "Ana's Song",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Open Fire'],
            ],
        ],
        "Late Night Tales: Belle and Sebastian (Continuous Mix)" => [
            "safe_title" => "Late Night Tales: Belle and Sebastian",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Continuous Mix'],
            ],
        ],
        "Late Night Tales: Belle and Sebastian, Vol. 2 (Sampler)" => [
            "safe_title" => "Late Night Tales: Belle and Sebastian, Vol. 2",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Sampler'],
            ],
        ],
        "Back to the Future — Back to the Future" => [
            "safe_title" => "Back to the Future",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Back to the Future'],
            ],
        ],
        "Piano Sonata No.14 in C Sharp Minor Op.27 No.2 – Moonlight: 1. Adagio sostenuto" => [
            "safe_title" => "Piano Sonata No.14 in C Sharp Minor Op.27 No.2",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Moonlight: 1. Adagio sostenuto'],
            ],
        ],
        "Nocturne Op.9 - No.2" => [
            "safe_title" => "Nocturne Op.9",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['No.2'],
            ],
        ],
        "Call On Me (Ryan Extended Remix)" => [
            "safe_title" => "Call On Me",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Ryan Extended Remix'],
            ],
        ],
        "Light it up (feat. Nyla & Fuse ODG) (Remix)" => [
            "safe_title" => "Light it up",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'featuring' => 'Nyla & Fuse ODG',
                'context' => ['feat. Nyla & Fuse ODG', 'Remix'],
            ],
        ],
        "Shape of You (Major Lazer Remix) [feat. Nyla & Kali]" => [
            "safe_title" => "Shape of You",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'featuring' => 'Nyla & Kali',
                'context' => ['Major Lazer Remix', 'feat. Nyla & Kali'],
            ],
        ],
        "Cheerleader (Felix Jaehn Radio Edit)" => [
            "safe_title" => "Cheerleader",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'edit' => 'Felix Jaehn Radio Edit',
                'acoustic' => false,
                'context' => ['Felix Jaehn Radio Edit'],
            ],
        ],
        "Midnight City (Remix EP)" => [
            "safe_title" => "Midnight City",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => true,
                'acoustic' => false,
                'context' => ['Remix EP'],
            ],
        ],
        "Human (Acoustic)" => [
            "safe_title" => "Human",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => true,
                'context' => ['Acoustic'],
            ],
        ],
        "My World (Edition collector)" => [
            "safe_title" => "My World",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Edition collector'],
            ],
        ],
        "Baba O'Riley (Original Album Version)" => [
            "safe_title" => "Baba O'Riley",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['Original Album Version'],
            ],
        ],
        "When you look at me (Original Version/Radio Edit)" => [
            "safe_title" => "When you look at me",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'edit' => 'Original Version/Radio Edit',
                'acoustic' => false,
                'context' => ['Original Version/Radio Edit'],
            ],
        ],
        "Paradise (Coldplay Acoustic Cover)" => [
            "safe_title" => "Paradise",
            "extra_info" => [
                'is_cover' => true,
                'is_remix' => false,
                'acoustic' => true,
                'context' => ['Coldplay Acoustic Cover'],
            ],
        ],
        "Classic Covers Vol.2" => [
            "safe_title" => "Classic Covers Vol.2",
            "extra_info" => [
                'is_cover' => false, // Would be too difficult to assess
                'is_remix' => false,
                'acoustic' => false,
                'context' => [],
            ],
        ],
    ];

    public function testParse()
    {
        foreach ($this->parsableMusicalStrings as $key => $value) {
            $this->assertEquals(
              $value,
              MusicalEntity::parse($key)
            );
        }
    }
}

