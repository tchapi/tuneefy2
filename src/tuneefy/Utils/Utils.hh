<?hh // strict

namespace tuneefy\Utils;

class Utils 
{
  
  /*
    Sanitizes a string 
    TODO : add doc here
  */
  public static function sanitize(string $string) : string
  {
    return strtolower(preg_replace('/[^\w\-]+/u', '-', $string));
  }

  /*
    Returns an ellipsed version of $text
  */
  public static function ellipsis(string $text, int $max = 100, string $append = "…" ) : string
  {
    if (strlen($text) <= $max) {
      return $text;
    }

    // Cut the string
    $out = substr($text,0,$max);

    if (strpos($text,' ') === FALSE) {
      // If it's a single word, just return with the suffix
      return $out.$append;
    } else {
      // Else, we replace the last word with the suffix
      // TODO : Fixme because \w doesn't work with accented characters
      return preg_replace('/\w+$/',$append,$out);
    }
  }

  /*
    Removes a bom from a string
  */
  public static function removeBOM(string $string): string
  {
    $bom = pack('H*','EFBBBF');
    return preg_replace("/^$bom/", '', $string);
  }

  /*
    When a platform does not return a "score" in a search session,
    we create a fake one using this function
  */
  public static function indexScore(int $index): float
  {
    return round(1/($index/10 +1), 2);
  }

  /*
    Flattens tokens (a Vector of strings) to return a single "alphanumeric" string
  */
  public static function flatten(Vector<string> $tokens): string
  {
    return preg_replace("/[^a-z0-9]+/", "", strtolower(implode('',$tokens)));
  }

}