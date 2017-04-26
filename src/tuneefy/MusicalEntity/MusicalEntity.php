<?php

namespace tuneefy\MusicalEntity;

abstract class MusicalEntity implements MusicalEntityInterface
{
    const TYPE = 'musical_entity';

    protected $links;

    // Introspection
    protected $introspected;
    protected $extra_info;

    public function __construct()
    {
        $this->links = [];
        $this->introspected = false;
        $this->extra_info = [];
    }

    public function toArray(): array
    {
        return [
          'type' => self::TYPE,
        ];
    }

    /*
    Links getter and setter
    */
    public function addLink(string $platform, string $link): MusicalEntity
    {
        $this->links[] = ['platform' => $platform, 'link' => $link];

        return $this;
    }

    public function addLinks(array $links): MusicalEntity
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function countLinks(): int
    {
        return count($this->links);
    }

    public function isIntrospected(): bool
    {
        return $this->introspected;
    }

    public function setExtraInfo(array $extra_info = null): MusicalEntity
    {
        $this->introspected = true;
        $this->extra_info = $extra_info;

        return $this;
    }

    public function getExtraInfo(): array
    {
        return $this->extra_info;
    }

    public function isCover(): bool
    {
        return isset($this->extra_info['is_cover']) && $this->extra_info['is_cover'];
    }

    public function isAcoustic(): bool
    {
        return isset($this->extra_info['acoustic']) && $this->extra_info['acoustic'];
    }

    public function isRemix(): bool
    {
        return isset($this->extra_info['is_remix']) && $this->extra_info['is_remix'];
    }

    public function isEdit(): bool
    {
        return isset($this->extra_info['edit']) && $this->extra_info['edit'] !== "";
    }

    public function getExtraInfoHash(): string
    {
        return (0+$this->isCover()).(0+$this->isAcoustic()).(0+$this->isRemix()).(0+$this->isEdit());
    }

    /*
      Parse a musical entity title
    */
    public static function parse(string $str): array
    {
        $extra_info = [];
        $matches = [];
        $matches_feat = [];
        $matches_edit = [];

        // NON-destructive tests first
        // ---------------------------

        // 1. Is this a cover / tribute or karaoke version ?
        // the strlen part prevents from matching a track named "cover" or "karaoke" only
        // We don't want to remove this from the title since we don't want to mix cover results from normal ones.
        $extra_info['is_cover'] = (
            preg_match('/[\-\—\–\(\[].*(originally\sperformed|cover|tribute|karaoke)/iu', $str) === 1 &&
            strlen($str) > 8
        );

        // 2. It's a special remix ?
        // (we don't remove that from the title neither)
        $extra_info['is_remix'] = true && preg_match("/.*[\-\—\–\(\[].*(mix|remix)/iu", $str);

        // 3. It's acoustic ?
        // (we don't remove that from the title neither)
        $extra_info['acoustic'] = true && preg_match("/.*[\-\—\–\(\[].*(acoustic|acoustique)/iu", $str);

        // 4. Any featuring ?
        if (preg_match("/.*f(?:ea)?t(?:uring)?\.?\s?(?P<artist>[^\(\)\[\]\-]*)/iu", $str, $matches_feat)) {
            $extra_info['featuring'] = trim($matches_feat['artist']);
        }

        // 5. It's a special edit ? NOT a special edition, mind you !
        // We add the space at the end of the search string to avoid missing a string ending with 'edit'
        // (we don't remove that from the title neither)
        if (preg_match("/.*[\-\—\–\(\[]\s?(?P<edit>.*edit)[^i]/iu", $str.' ', $matches_edit)) {
            $extra_info['edit'] = trim($matches_edit['edit']);
        }

        // NOW we modify the string perhaps
        // --------------------------------

        // 6. Extract added context info
        preg_match_all("/(?P<removables>[\(\{\[\-\—\–](?P<meta>[^\]\}\)\(\[\{)]*)[\)\}\]]?)/iu", $str, $matches);

        if (array_key_exists('meta', $matches)) {
            $extra_info['context'] = array_map(function ($e) { return trim($e); }, $matches['meta']);

            // Remove the context strings from the string
            foreach ($matches['removables'] as $key => $value) {
                $str = str_replace($value, ' ', $str);
            }

            // Remove n-uple spaces
            $str = preg_replace("/\s+/i", ' ', $str);
        }

        return [
            'safe_title' => trim($str),
            'extra_info' => $extra_info,
        ];
    }
}
