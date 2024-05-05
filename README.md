# tuneefy _2_

A new version of [tuneefy](http://tuneefy.com) built for **PHP 8** and **Node 18+**, from the ground up, using Symfony and a few helper libraries.

### Installing

This project uses [composer 2](https://getcomposer.org/). Just run :

    composer install

### Creating tables

Tuneefy needs a variety of tables to work properly; you can populate your database with the following :

    bin/console doctrine:migrations:migrate

### Building assets & API doc

To build the assets and the API documentation, I use **yarn** and some modules.

    yarn install

    yarn run build
    yarn run api-documentation

### Tests

The tests are under the `./tests` folder and I use **Codeception** to run them.

Just run :

    vendor/bin/codecept run --steps

Beforehand, do not forget to launch a development web server so that the functional tests have an endpoint to test:

    symfony server:start --port 9999

There should be 40 tests containing 697 assertions.

> Sometimes a platform fails to respond correctly due to network latencies or such. Re-run the tests in this case, it should pass fine the second time.

### API

The API endpoints require an OAuth access token. The token is necessary to authenticate **all** requests to the API.

The tuneefy API currently supports the [OAuth 2 draft](https://oauth.net/2/) specification. All OAuth2 requests MUST use the SSL endpoint available at https://data.tuneefy.com/.

OAuth 2.0 is a simple and secure authentication mechanism. It allows applications to acquire an access token for tuneefy via a POST request to a token endpoint. Authentication with OAuth can be accomplished in the following steps:

  1. Register for an API key by sending a mail to api@tuneefy.com
  2. Exchange your customer id and secret for an access token
  3. Make requests by passing the token in the Authorization header
  4. When your token expires, you can get a new one 

#### Apply for an API key

You can get an API key and associated secret by sending an email to api@tuneefy.com.

#### Web Service Rate Limits

Limits are placed on the number of API requests you may make using your API key. Rate limits may vary by service, but the defaults are 100 requests per hour.

#### Full documentation

The full documentation is available at https://data.tuneefy.com. An API blueprint is also available [here](https://github.com/tchapi/tuneefy2/blob/master/app/templates/api/main.apib) — use your preferred renderer to build it. We use Aglio.


- - -

> If you want to participate/contribute, feel free to create pull requests or issues so we can make Tuneefy better and more efficient !
