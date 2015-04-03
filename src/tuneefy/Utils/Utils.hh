<?hh // strict

namespace tuneefy\Utils;

class Utils 
{
  
  /*
    Sanitizes a string 
    TODO : add doc here
  */
  public function sanitize(string $string) : string
  {
    return strtolower(preg_replace('/[^\w\-]+/u', '-', $string));
  }

  /*
    Returns an ellipsed version of $text
  */
  public function ellipsis(string $text, int $max = 100, string $append = "…" ) : string
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

}
