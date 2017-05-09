# coding=utf8
import requests, json

key = 'administrator'
secret = 'password'

tokenEndpoint = 'https://api.tuneefy.com/auth/token'
searchEndpoint = 'https://api.tuneefy.com/search/track/spotify?q=amon+tobin&limit=1'

# 1. Request token
payload = {'grant_type': 'client_credentials', 'client_id': key, 'client_secret': secret}
req = requests.post(tokenEndpoint,data=payload)

token = req.json()

# 2. Use token for search on Spotify
if token['token_type'] and token['token_type'] == 'Bearer':
    headers = {'Authorization': 'Bearer '+token['access_token'], 'Accept': 'application/json'}
    req = requests.get(searchEndpoint, headers=headers)

    # 3. Tada ! 
    print "🎉"
    print json.dumps(req.json(), indent=4, separators=(',', ': '))
else:
    print "Wrong key/secret pair"
