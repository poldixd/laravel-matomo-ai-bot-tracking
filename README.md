# Laravel Matomo AI Bot Tracking

Track AI bot requests from a Laravel app in Matomo.

This package adds a middleware for Laravel. It uses
[`matomo/device-detector`](https://github.com/matomo-org/device-detector) to detect
AI bots and sends these requests to Matomo with `recMode=1`.

## What It Does

The middleware tracks AI bot page requests on the server. It only tracks a request
when all of these things are true:

- The request is a `GET` request.
- The request has a `User-Agent` header.
- Matomo Device Detector detects the user agent as an AI bot.
- The request is not asking for JSON.
- The path is not for Livewire, Debugbar, or Telescope.

The request to Matomo contains these fields:

- `idsite`
- `rec=1`
- `recMode=1`
- `url`
- `http_status`
- `bw_bytes`
- `pf_srv`
- `ua`
- `source`

The middleware does not send `cip` or `cdt`. This means it does not override the
visitor IP or request time in Matomo.

## Requirements

- PHP 8.2 or newer
- Laravel 11, 12, or 13
- Matomo with bot tracking support

Matomo bot tracking needs support for the `recMode` tracking parameter.

## Installation

Install the package with Composer:

```bash
composer require poldixd/laravel-matomo-ai-bot-tracking
```

Laravel will auto-discover the service provider.

## Configuration

The package reads its settings from Laravel's `services` config. Add this to
`config/services.php`:

```php
'matomo' => [
    'enabled' => env('MATOMO_AI_BOT_TRACKING_ENABLED', false),
    'tracking_url' => env('MATOMO_TRACKING_URL'),
    'site_id' => env('MATOMO_SITE_ID'),
    'source' => env('MATOMO_AI_BOT_TRACKING_SOURCE', 'laravel'),
],
```

Then add the values to your `.env` file:

```dotenv
MATOMO_AI_BOT_TRACKING_ENABLED=true
MATOMO_TRACKING_URL=https://matomo.example.com
MATOMO_SITE_ID=1
MATOMO_AI_BOT_TRACKING_SOURCE=laravel
```

`MATOMO_TRACKING_URL` is the base URL of your Matomo installation. Do not add
`/matomo.php` here. The middleware adds it for you.

## Middleware Usage

Register the middleware on the routes where you want to track AI bots.

For a Laravel 11+ application, add it to the middleware configuration in
`bootstrap/app.php`:

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use poldixd\MatomoAIBotTracking\Middleware\MatomoAIBotTracking;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            MatomoAIBotTracking::class,
        ]);
    })
    ->create();
```

For older Laravel applications that still use `app/Http/Kernel.php`, add it to
the `web` middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \poldixd\MatomoAIBotTracking\Middleware\MatomoAIBotTracking::class,
    ],
];
```

## Behavior

When an AI bot request matches the rules above, the middleware waits until Laravel
has created the response. Then it sends a form POST request to:

```text
{MATOMO_TRACKING_URL}/matomo.php
```

If Matomo tracking fails, the user response is still returned as normal. The
error is reported through Laravel and logged with useful request details.

## Testing

Run the tests with:

```bash
composer test
```

Run the code style check with:

```bash
vendor/bin/pint --test
```

## License

MIT
