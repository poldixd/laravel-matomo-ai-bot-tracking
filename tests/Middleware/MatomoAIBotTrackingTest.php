<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use poldixd\MatomoAIBotTracking\Middleware\MatomoAIBotTracking;
use Symfony\Component\HttpFoundation\Response;

it('does not track requests without user agent', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/test', 'GET');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('does not track non-GET requests', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/test', 'POST');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('does not track HEAD requests', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/test', 'HEAD');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('does not track non-AI bots', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('tracks AI bots like GPTBot', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);
    Config::set('services.matomo.tracking_url', 'https://example.com');
    Config::set('services.matomo.site_id', 1);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/matomo.php' &&
               $request['ua'] === 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)';
    });
});

it('tracks AI bots like ClaudeBot', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);
    Config::set('services.matomo.tracking_url', 'https://example.com');
    Config::set('services.matomo.site_id', 1);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'ClaudeBot/1.0');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'matomo.php') &&
               $request['ua'] === 'ClaudeBot/1.0';
    });
});

it('normalizes the matomo tracking url', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);
    Config::set('services.matomo.tracking_url', 'https://example.com/');
    Config::set('services.matomo.site_id', 1);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'ClaudeBot/1.0');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/matomo.php';
    });
});

it('reports failed matomo responses with context', function () {
    Http::fake([
        'https://example.com/matomo.php' => Http::response('Invalid site id', 400),
    ]);
    Log::spy();
    Config::set('services.matomo.enabled', true);
    Config::set('services.matomo.tracking_url', 'https://example.com');
    Config::set('services.matomo.site_id', 1);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'ClaudeBot/1.0');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Log::shouldHaveReceived('warning')->once()->with(
        'Matomo AI bot tracking failed.',
        Mockery::on(fn (array $context) => $context['matomo_response_status'] === 400
            && $context['matomo_response_body'] === 'Invalid site id'
            && $context['request_method'] === 'GET'
            && $context['response_status'] === 200)
    );
});

it('does not track when matomo is disabled', function () {
    Http::fake();
    Config::set('services.matomo.enabled', false);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('does not track JSON requests', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $request->headers->set('Accept', 'application/json');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});

it('does not track livewire requests', function () {
    Http::fake();
    Config::set('services.matomo.enabled', true);

    $request = Request::create('/livewire/test', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)');
    $middleware = new MatomoAIBotTracking(app(Factory::class));

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    Http::assertNothingSent();
});
