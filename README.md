# Laravel Remote Model

This model provides an eloquent-like base class that can be used to build custom models for remote APIs.

## Installation

Install using composer:

```
$ composer require torann/remote-model
```

## Clients

### Custom request method

To implement a custom API request method in the model, simple extend the `Torann\RemoteModel\Model` class and use that extended model in the app models.

**Example**

```php
<?php

namespace App;

use APIClient;
use Torann\RemoteModel\Model;

class BaseModel extends Model
{
   /**
    * Make request through API.
    *
    * @return mixed
    */
   protected function request($endpoint = null, $method, $params)
   {
       $endpoint = $endpoint ? $endpoint : $this->endpoint;

       $results = APIClient::$method($endpoint, $params);

       return $results ? $this->newInstance($results) : null;
   }
}

```

### Client Wrapper Method

```
$client = new Client();

$client->{ENDPOINT}()->{METHOD}();
```

**ENDPOINT**
The "snake case", plural name of the model class will be used as the endpoint name unless another name is explicitly specified. Using `protected $endpoint = 'users';` at the top of the model, this is similar to the `$table` variable in Laravel models.

**METHOD**
This is the action to take on the endpoint. It can be anything that the wrapper class provides.

#### Example Client Wrapper

```php
<?php

namespace PackageName\Api;

use PackageName\Api\Exception\BadMethodCallException;
use PackageName\Api\Exception\InvalidArgumentException;

class Client
{
    /**
     * The HTTP client instance used to communicate with API.
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Instantiate a new client.
     */
    public function __construct()
    {
        $this->httpClient = new HttpClient;
    }

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     *
     * @return ApiInterface
     */
    public function api($name)
    {
        switch ($name)
        {
            case 'users':
                $api = new Endpoints\Users($this);
                break;

            case 'reviews':
                $api = new Endpoints\Reviews($this);
                break;

            default:
                throw new InvalidArgumentException(sprintf('Undefined api instance called: "%s"', $name));
        }

        return $api;
    }

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     *
     * @return ApiInterface
     */
    public function __call($name, $args)
    {
        try {
            return $this->api($name);
        }
        catch (InvalidArgumentException $e) {
            throw new BadMethodCallException(sprintf('Undefined method called: "%s"', $name));
        }
    }
}
```

#### Example Endpoint for Client Wrapper

This is just to give an example.

```php
<?php

namespace PackageName\Api\Endpoints;

use PackageName\Api\Client;

class Users
{
    /**
     * The client.
     *
     * @var \PackageName\Api\Client
     */
    protected $client;

    /**
     * @param \PackageName\Api\Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Register a user.
     *
     * @param  array $params
     *
     * @return array
     */
    public function add(array $params)
    {
        return $this->client->post('users', $params);
    }

    /*
     * Update user data
     *
     * @param  array $params
     *
     * @return object
     */
    public function update(array $params)
    {
        return $this->client->patch('users/self', $params);
    }

    /**
     * Get extended information about a user by its id.
     *
     * @param  string $user_id
     *
     * @return array
     */
    public function find($user_id)
    {
        return $this->client->get('users/'.rawurlencode($user_id));
    }
}
```

## Client Service Provider

An API client must be set before any data can be retrieved . To set the client use the static `Model::setClient` method.

Below is an example of the service provider way of setting the client.

```php
<?php

namespace App\Providers;

use Torann\RemoteModel\Model;
use PackageName\API\Client;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setClient($this->app['apiclient']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('apiclient', function () {
            return new Client(); // API Client
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'apiclient'
        ];
    }
}
```

## Example model

```php
<?php

namespace App;

use DateTime;
use Torann\RemoteModel\Model as BaseModel;

class User extends BaseModel
{
    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'age' => 'integer'
    ];

    public function save()
    {
        return API::post('/items', $this->attributes);
    }

    public function setBirthdayAttribute($value)
    {
        $this->attributes['birthday'] = strtotime($value);
    }

    public function getBirthdayAttribute($value)
    {
        return new DateTime("@$value");
    }

    public function getAgeAttribute($value)
    {
        return $this->birthday->diff(new DateTime('now'))->y;
    }
}
```

**Using model**

```php
$item = new User([
    'name' => 'john'
]);

$item->password = 'bar';

echo $item; // {"name":"john"}
```

## Change Log

**0.1.0**

- Fix parent ID bug

**0.0.1**

- First release