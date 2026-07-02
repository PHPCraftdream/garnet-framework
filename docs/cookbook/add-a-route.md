# Add a route

Routes are PHP arrays declared in the app's main class
(`<App>::runWebApp`). There's no auto-discovery — every URL maps
explicitly to a `[Controller::class, 'middlewares', '/path']` triple.

## One route, one controller method

Say you want `GET /about` to render an About page.

### 1. Create the controller

`Apps/MyApp/Foreground/Controllers/AboutController.php`:

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\Foreground\Controllers;

use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
use Psr\Http\Message\ResponseInterface;

class AboutController extends FrameworkController
{
    public function get__index(
        GlobalReqParams $g,
        RouterUriParams $u,
    ): ResponseInterface {
        return $this->renderTwig('Foreground/about.twig', [
            'title' => 'About',
        ]);
    }
}
```

The method name encodes the HTTP verb: `get__name` → `GET …/~name`,
`post__name` → `POST …/~name`. A method named `get__index` (or
`post__index`) is the route's default — it answers the bare path.

### 2. Add the Twig template

`Apps/MyApp/Foreground/TwigTemplates/Foreground/about.twig`:

```twig
{% extends 'Layout/HtmlLayout.twig' %}

{% block content %}
  <h1>{{ title }}</h1>
  <p>About this app.</p>
{% endblock %}
```

### 3. Register the route

In `Apps/MyApp/MyApp.php` → `runWebApp()`:

```php
$router->add('/about', [AboutController::class, [], '']);
```

The triple is `[ControllerClass, [middlewares], '/sub-route']`. Pass
`[]` for no middleware. `''` means the route accepts the default
method (`get__index`).

## Dynamic params

URL `/user~123` is parsed into `routeVal = '/{user}'` and
`params = ['user' => '123']`. Register it as:

```php
$router->add('/user~{user}', [UserController::class, [], '']);
```

In the controller:

```php
public function get__index(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
{
    $userId = (int) $u->get('user');
    // …
}
```

## Multiple methods per controller

The same controller can answer multiple paths via `~method` suffixes:

```php
$router->add('/user~{user}',          [UserController::class, [], '']);
$router->add('/user~{user}/~profile', [UserController::class, [], '~profile']);
$router->add('/user~{user}/~delete',  [UserController::class, [], '~delete']);
```

Methods: `get__index`, `get__profile`, `post__delete`.

## Middleware

A middleware is a `[Class::class, 'staticMethod']` callable that runs
before the controller. They can short-circuit (return a response) or
mutate request state.

```php
$router->add('/admin/dashboard', [
    DashboardController::class,
    [[AdminOnlyMiddleware::class, 'process']],
    '',
]);
```

## Related

- [Add a bundle](add-a-bundle.md) — bundles are where reusable routes live.
- [`../architecture.md`](../architecture.md) — request lifecycle, O(1) dispatch.
- [`../bundle.md`](../bundle.md) — middleware patterns.

---

↑ Back to [Cookbook](README.md)
