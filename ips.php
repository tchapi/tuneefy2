<?php

$hosts = [
  'listen.tidal.com',
  'www.amazon.com',
  'api.deezer.com',
  'www.googleapis.com',
  'itunes.apple.com',
  'ws.audioscrobbler.com',
  'api.mixcloud.com',
  'api.napster.com',
  'www.qobuz.com',
  'api.soundcloud.com',
];

echo "[\n";
foreach ($hosts as $id => $value) {
    $ips = gethostbynamel($value);
    echo "  '".$value.':443'.':'.$ips[0]."',\n";
}
echo "];\n";
