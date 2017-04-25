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
            "title" => "A State Of Trance",
            "extra_info" => [
                'is_cover' => false,
                'is_remix' => false,
                'acoustic' => false,
                'context' => ['ASOT 810', 'About \'This Is A Test\''],
            ],
        ],
        "Faces of Freedom (HeadFuck  ) - NOzone Mix" => [],
        "A random song tiltle" => [],
        "My love (Deluxe Edition)" => [],
        "What do you want me for ? - test remix" => [],
        "Where is my mind - radio edit" => [],
        "Clementine is here - radio edit (acoustique)" => [], 
        "Let's mix !" => [],
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

