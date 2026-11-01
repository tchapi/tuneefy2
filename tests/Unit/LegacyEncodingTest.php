<?php

namespace Tests\Unit;

use App\Dataclass\MusicalEntity\Entities\Album;
use App\Dataclass\MusicalEntity\Entities\Track;
use App\Entity\Item;
use Tests\Support\UnitTester;

final class LegacyEncodingTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    public function testLegacyLatin1ItemIsRepairedOnLoad(): void
    {
        $track = new Track(new Album('La Galería', 'La Mambanegra', 'https://example.org/pic.jpg'), 'Canción');

        // Re-encode every string of the serialized object in ISO-8859-1, as old items were stored
        $legacyBlob = preg_replace_callback('/s:(\d+):"(.*?)";/s', function ($m) {
            $latin1 = iconv('UTF-8', 'ISO-8859-1', $m[2]);

            return 's:'.\strlen($latin1).':"'.$latin1.'";';
        }, serialize($track));

        $this->assertStringNotContainsString("\xC3\xAD", $legacyBlob, 'the fixture must not be valid UTF-8');

        $entity = (new Item())->setObject($legacyBlob)->getMusicalEntity();

        $this->assertInstanceOf(Track::class, $entity);
        $this->assertSame('Canción', $entity->getTitle());
        $this->assertSame('La Galería', $entity->getAlbumTitle());
        $this->assertSame('La Mambanegra', $entity->getArtist());
        $this->assertSame('https://example.org/pic.jpg', $entity->getAlbumPicture());
    }

    public function testValidUtf8ItemIsLeftUntouched(): void
    {
        $track = new Track(new Album('Pablo Honey', 'Radiohead', ''), 'Thinking About You');
        $entity = (new Item())->setMusicalEntity($track)->getMusicalEntity();

        $this->assertEquals($track, $entity);
    }
}
