# Tiny PHP REST Framework
## INTRODUCTION
This is a framework I wrote to avoid the repetitive work that comes each time I wanted to build a simple REST API with PHP.

The aim of this framework is to allow people to build robust APIs without having to go through a heavy framework (hello, Laravel users!), because most of your logic will be handled in the client side anyways.

It contains the bare minimum required to get you going: a router, a Json Web Token implementation, classes that deal with the request and the response, and some helper classes. No sessions or cookies though, because REST APIs are supposed to be stateless, and no views because you're supposed to return json.

This framework borrows various concepts from other PHP and JS frameworks.

## USAGE
Your code is supposed to go into the `app/Controller` and `app/Model` directories.

All the routing logic goes into the `router.php` file.

### Config.php
```php
<?php
const DB_HOST = 'localhost',
    DB_NAME = 'test',
    DB_USR = 'root',
    DB_PWD = '',
    BASE_URL = '/api', // important! You must specify the uri to the index.php file, because all the routes' calculations will be based off this.
    JWT_KEY = '*fp%r2-f2s+^k8nsz^ck%hxcri#ln005w2@+gi=x59y#8aut6p'; // Please replace this with your own characters, and remember that whoever knows this can bypass your security easily.
```


### router.php
Contains a list of your routes.

Each route is declared by calling the appropriate method name from the Route class.

Syntax: `Route::HTTP_METHOD('UNIQUE_ROUTE_NAME', 'PATH', [REGEX])`

Where `HTTP_METHOD` is one of `get`, `post`, `put`, `del` or `all`.

`UNIQUE_ROUTE_NAME` might contain optional characters (surrounded by `()`) and named parameters (surrounded by `<>`).

The controller and action to use with this route must be specified as a named parameter.

If a named parameter isn't present in the path, you can specify its default value with the `defaults()` method.
```php
<?php
->defaults([
        'controller' => 'CONTROLLER_NAME',
        'directory' => 'DIRECTORY/NAME', // add this if your controller is in `app/Controller/DIRECTORY/NAME/CONTROLLER_NAME.php`
        'action' => 'ACTION_NAME'
    ])
```
If a token is required to access the route you need to add: `->protect()`.

Note that `protect()` only checks if a valid token is present in the HTTP request and doesn't check the requester's privileges.

example:
```php
<?php
Route::post('login', 'login')
    ->defaults([
        'controller' => 'user',
        'action' => 'login'
    ]);

Route::del('delUser', 'user(s)/<id>', ['id' => '\d+']) // will match /user/2 and /users/2 but not /user
    ->defaults([
        'controller' => 'user',
        'action' => 'delete'
    ])
    ->protect();
```
### Controllers
#### Convention
- All your controllers must be in the `app/Controller` folder (or its subfolders)
- If they are in a subfolder you must specify it in the `directory` route parameter
- The class name must map to the filename ( with / replaced with _ ) and each word is capitalized
- All your controllers must inherit from the `Controller` class
- Actions are public functions with an `action_` prefix. They will receive two parameters: `$request` and `$response`
#### Example
```php
<?php
class Controller_Admin extends Controller
{
    public function action_signup($req, $res)
    {
        //...
        $res->body(['message' => 'success']);
    }
}
```
#### $request
The first parameter of an action is a `Request` instance, it gives you access to most of the info that exist in the HTTP request.
##### Quick overview
- `$req->uri()`: returns the current uri.
- `$req->method()`: returns the HTTP method of the current request in uppercase.
- `$req->param([$parameter_name])`: returns the route parameter named `$parameter_name` (alias for `$req->params`).
- `$req->query([$parameter_path])`: Uses Arr::path to return the specified `$_GET` element.
- `$req->post([$parameter_path])`: Uses Arr::path to return the specified `$_POST` element.
- `$req->body([$parameter_path])`: If the request's `content-type` is either `application/json` or `application/x-www-form-urlencoded` it uses Arr::path to return the specified request body element. if it isn't, then it will return the whole body as a string
- `$req->header([$h])`: returns the specified HTTP header
- `$req->jwt` if the route is `->protect()`ed, this will be an array containing the info that you previously coded into your token using `JWT::encode()`

#### $response
The second parameter of an action is a `Response` instance.
##### Quick overview
- `$res->header($key, $value)`:
  + if `$key` is null, it returns an array of all the headers that will be sent (except CORS headers because they'll be set just before sending the response)
  + if `$key` is an array, it merges it with the existing headers
  + if `$value` is null, it returns the header named `$key`
  + if both are specified, it sets the header `$key` to `$value`
- `$res->error($code, $message)`: sets the HTTP code for the response to `$code` and sets the body to `$message`
- `$res->body($b)`: sets the HTTP body to `$b`

##### !!IMPORTANT!! if the HTTP code is anything other than `200`, the body of the response will be set to `{"error": {"code": xxx, "message": "body"}}`. If it's `200`, the body will be the json encoded value of what you passed to `->body()`

### Models
They have the same naming rules as the controllers, and they extend `Model`.

You can use them from inside your controllers
example:
```php
<?php
public function action_list_users($req, $res)
{
    //...
    try {
        $users = Model::factory('users')->all($sort, $order, $limit, $offset, $filters, $orORand);
        $res->body($users[0]);
        $res->header("X-Total-Count", $users[1]);
    } catch (Exception $e) {
        $res->error(500, $e->getMessage());
    }
}
```
you can access the `db` singleton from the model, which is just a PDO object that you'll use to query your database.

example:
```php
<?php
public function login($username, $password)
{
    $stmt = self::$db->prepare("SELECT * FROM users WHERE login = :username");
    //...
}
```

## TODO
- Think of a name (. _ .)
- GENERATORS!! :D (yeoman?)
- Logging
- Caching
- A Query builder
- Better error management and a redirect implementation
- Nested routes
- Matching specific methods (exple: get and post but not put)
- An example app
- Comments... and a proper doc
