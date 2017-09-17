var request = require('request');
 
var key = 'administrator';
var secret = 'password';

var tokenEndpoint = 'https://data.tuneefy.com/v2/auth/token';
var searchEndpoint = 'https://data.tuneefy.com/v2/search/track/spotify?q=amon+tobin&limit=1';

// 1. Request token
request.post({
  url: tokenEndpoint,
  form: {
    'grant_type': 'client_credentials',
    'client_id': key,
    'client_secret': secret
  }
}, function(err, httpResponse, body) {
  var json = JSON.parse(body);

  if (json.token_type && json.token_type === 'Bearer') {
      // 2. Use token for search on Spotify 
      request({
        url: searchEndpoint,
        'auth': {
          'bearer': json.access_token
        },
        method: 'GET'
      }, function(err, httpResponse, body) {
        var json = JSON.parse(body);

        // 3. Tada ! 
        console.log("🎉");
        console.log(JSON.stringify(json, null, 4));
      });
  }

});
