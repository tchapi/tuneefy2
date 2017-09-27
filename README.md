# tuneefy _2_

A new version of [tuneefy](http://tuneefy.com) built for **PHP 7**, from the ground up, using the minimal [Slim](https://www.slimframework.com/) framework and a few helper libraries.

### Installing

This project uses [composer](https://getcomposer.org/). Just run :

    composer install

### Creating tables

Tuneefy needs a variety of tables to work properly; you can populate your database with the following :

    mysql -u user -p database_name < ./structure.sql

### Building assets & API doc

To build the assets and the API documentation, I use **npm** and some modules.

    npm install

    npm run build-assets
    npm run api-documentation

### Composer packages used

  - [Composer](https://getcomposer.org/), providing a nice package manager *and* a practical PSR-4 autoloader
  - [Symfony\Yaml](http://symfony.com/doc/current/components/yaml/introduction.html) to parse the configuration files
  - [Slim](http://www.slimframework.com/), a lightweight RESTful framework
  - [Twig](http://twig.sensiolabs.org/), a template engine
  - [XmlToJsonConverter](https://github.com/markwilson/xml-to-json) to convert Amazon XML to correct JSON
  - [RKA Content-Type renderer](https://github.com/akrabat/rka-content-type-renderer) to output JSON / XML / HTML for the API
  - [Slim basic auth](https://github.com/tuupola/slim-basic-auth) for admin access

#### Dev packages

  - [PHP CS Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) to lint the PHP code
  - [PHPUnit](https://phpunit.de/) for unit tests
  - [Deployer](https://deployer.org) to deploy the site

### NPM (dev) packages used

  - [Gulp](http://gulpjs.com/) , including `gulp-sass`, `gulp-uglify` and `pump` for building assets
  - [Aglio](https://github.com/danielgtaylor/aglio) for generating the API docs

### On the frontend side of things

I'm using [JQuery](http://jquery.com) to cover the DOM manipulation tasks and related stuff.

### Code structure

This project is a very basic composer project with a PSR-4 autoloader.
The source is in `src/tuneefy` and is organised as such :

  * **MusicalEntity** includes the model for a musical entity (_album or track_)
  * **Controller** includes the controllers for the various routes (api and frontend)
  * **Platform** includes all the platform-related code, especially the specific methods for each remote API call
  * **Utils** includes various utilities such as custom Slim error handlers
  * and two top-level classes : **Application** and **PlatformEngine** that deal with the application itself and how it interacts with the platforms

### Tests

The tests are under the `./tests` folder and I use **PHPUnit 6.1** to run them.
Just run :

    vendor/bin/phpunit -v

There should be 36 tests containing 673 assertions.

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
