<?php

namespace poldixd\MatomoAIBotTracking\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MatomoAIBotTracking
{
    protected const AI_BOT_USER_AGENT_NEEDLES = [
        'addsearchbot',
        'ai2bot',
        'ai2bot-deepresearcheval',
        'ai2bot-dolma',
        'aihitbot',
        'amazon-kendra',
        'amazonbot',
        'amazonbuyforme',
        'amzn-searchbot',
        'amzn-user',
        'andibot',
        'anomura',
        'anthropic-ai',
        'apifybot',
        'apifywebsitecontentcrawler',
        'applebot',
        'applebot-extended',
        'aranet-searchbot',
        'atlassian-bot',
        'awario',
        'azureai-searchbot',
        'bedrockbot',
        'bigsur.ai',
        'bravebot',
        'brightbot-1.0',
        'buddybot',
        'bytespider',
        'ccbot',
        'channel3bot',
        'chatglm-spider',
        'chatgpt-agent',
        'chatgpt-user',
        'claude-searchbot',
        'claude-user',
        'claude-web',
        'claudebot',
        'cloudflare-autorag',
        'cloudvertexbot',
        'cohere-ai',
        'cohere-training-data-crawler',
        'cotoyogi',
        'crawl4ai',
        'crawlspace',
        'datenbank-crawler',
        'deepseekbot',
        'devin',
        'diffbot',
        'duckassistbot',
        'echobot-bot',
        'echoboxbot',
        'exabot',
        'facebookbot',
        'facebookexternalhit',
        'factset_spyderbot',
        'firecrawlagent',
        'friendlycrawler',
        'gemini-deep-research',
        'google-agent',
        'google-cloudvertexbot',
        'google-extended',
        'google-firebase',
        'google-notebooklm',
        'googleagent-mariner',
        'googleother',
        'googleother-image',
        'googleother-video',
        'gptbot',
        'iaskbot',
        'iaskspider',
        'iboubot',
        'icc-crawler',
        'imagesiftbot',
        'imagespider',
        'img2dataset',
        'isscyberriskcrawler',
        'kagi-fetcher',
        'kangaroo-bot',
        'klaviyoaibot',
        'kunatocrawler',
        'laion-huggingface-processor',
        'laiondownloader',
        'lcc',
        'linerbot',
        'linguee-bot',
        'linkupbot',
        'manus-user',
        'meta-externalagent',
        'meta-externalfetcher',
        'meta-webindexer',
        'mistralai-user',
        'mycentralaiscraperbot',
        'nagetbot',
        'netestate-imprint-crawler',
        'newsai',
        'notebooklm',
        'novaact',
        'oai-searchbot',
        'omgili',
        'omgilibot',
        'openai',
        'operator',
        'pangubot',
        'panscient',
        'panscient.com',
        'perplexity-user',
        'perplexitybot',
        'petalbot',
        'phindbot',
        'poggio-citations',
        'poseidon-research-crawler',
        'qualifiedbot',
        'quillbot',
        'quillbot.com',
        'sbintuitionsbot',
        'scrapy',
        'semrushbot-ocob',
        'semrushbot-swa',
        'shapbot',
        'sidetrade-indexer-bot',
        'tavilybot',
        'terracotta',
        'thinkbot',
        'tiktokspider',
        'timpibot',
        'twinagent',
        'velenpublicwebcrawler',
        'wardbot',
        'webzio-extended',
        'wpbot',
        'wrtnbot',
        'yak',
        'yandexadditional',
        'yandexadditionalbot',
        'youbot',
        'zanistabot',
    ];

    public function __construct(
        protected HttpFactory $http,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! config('services.matomo.enabled')) {
            return $response;
        }

        if (! $this->shouldTrack($request)) {
            return $response;
        }

        try {
            $matomoResponse = $this->http
                ->asForm()
                ->timeout(2)
                ->connectTimeout(2)
                ->acceptJson()
                ->post($this->trackingUrl(), $this->payload(
                    request: $request,
                    response: $response,
                    startedAt: $startedAt,
                ));

            if ($matomoResponse->failed()) {
                $this->reportFailedTrackingResponse($request, $response, $matomoResponse);
            }
        } catch (ConnectionException $exception) {
            $this->reportTrackingFailure($request, $response, $exception);
        } catch (Throwable $exception) {
            $this->reportTrackingFailure($request, $response, $exception);
        }

        return $response;
    }

    protected function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $userAgent = $request->userAgent();

        if (! $userAgent) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        if ($request->is('livewire/*', '_debugbar/*', 'telescope*')) {
            return false;
        }

        if (! $this->isAiBot($userAgent)) {
            return false;
        }

        return true;
    }

    protected function payload(Request $request, Response $response, float $startedAt): array
    {
        $fullUrl = $request->fullUrl();
        $userAgent = (string) $request->userAgent();
        $serverTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'idsite' => config('services.matomo.site_id'),
            'rec' => 1,
            'recMode' => 1,
            'url' => $fullUrl,
            'http_status' => $response->getStatusCode(),
            'bw_bytes' => strlen($response->getContent() ?: ''),
            'pf_srv' => max($serverTimeMs, 0),
            'ua' => $userAgent,
            'source' => config('services.matomo.source', 'laravel'),
        ];
    }

    protected function trackingUrl(): string
    {
        return rtrim((string) config('services.matomo.tracking_url'), '/').'/matomo.php';
    }

    protected function reportFailedTrackingResponse(Request $request, Response $response, HttpResponse $matomoResponse): void
    {
        $this->reportTrackingFailure($request, $response, new RequestException($matomoResponse), [
            'matomo_response_status' => $matomoResponse->status(),
            'matomo_response_body' => substr($matomoResponse->body(), 0, 1000),
        ]);
    }

    protected function reportTrackingFailure(Request $request, Response $response, Throwable $exception, array $context = []): void
    {
        report($exception);

        logger()->warning('Matomo AI bot tracking failed.', array_merge([
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'response_status' => $response->getStatusCode(),
            'matomo_tracking_url' => config('services.matomo.tracking_url'),
            'matomo_site_id' => config('services.matomo.site_id'),
        ], $context));
    }

    protected function isAiBot(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        foreach (self::AI_BOT_USER_AGENT_NEEDLES as $needle) {
            if (str_contains($userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }
}
