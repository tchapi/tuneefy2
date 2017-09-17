<?php

$key = 'administrator';
$secret = 'password';

$tokenEndpoint = 'https://data.tuneefy.com/v2/auth/token';
$searchEndpoint = 'https://data.tuneefy.com/v2/search/track/spotify?q=amon+tobin&limit=1';

// 1. Request token
$tk = curl_init($tokenEndpoint);
curl_setopt($tk, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
]);
curl_setopt($tk, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&client_id='.$key.'&client_secret='.$secret);
curl_setopt($tk, CURLOPT_RETURNTRANSFER, true);
$token = json_decode(curl_exec($tk));
curl_close($tk);

// 2. Use token for search on Spotify
if (isset($token->token_type) && $token->token_type === 'Bearer') {
    $br = curl_init($searchEndpoint);
    curl_setopt($br, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token->access_token,
        'Accept: application/json',
    ]);
    curl_setopt($br, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($br);
    curl_close($br);
  
    // 3. Tada ! 
    echo "🎉\n";
    var_dump($data);
} else {
    echo "Wrong key/secret pair";
}
