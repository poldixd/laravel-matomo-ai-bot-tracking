<?php

declare(strict_types=1);

$sourceUrl = getenv('AI_BOT_SOURCE_URL') ?: 'https://raw.githubusercontent.com/ai-robots-txt/ai.robots.txt/main/robots.json';
$targetFile = getenv('AI_BOT_TARGET_FILE') ?: __DIR__.'/../src/Middleware/MatomoAIBotTracking.php';

$json = @file_get_contents($sourceUrl, false, stream_context_create([
    'http' => [
        'header' => "User-Agent: poldixd/laravel-matomo-ai-bot-tracking\r\nAccept: application/json\r\n",
        'timeout' => 20,
    ],
]));

if ($json === false) {
    fwrite(STDERR, "Failed to fetch AI bot source: {$sourceUrl}\n");
    exit(1);
}

$payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
$needles = extractAiBotNeedles($payload);

if ($needles === []) {
    fwrite(STDERR, "No AI bot user-agent needles found in source: {$sourceUrl}\n");
    exit(1);
}

$source = file_get_contents($targetFile);

if ($source === false) {
    fwrite(STDERR, "Failed to read target file: {$targetFile}\n");
    exit(1);
}

$replacement = "protected const AI_BOT_USER_AGENT_NEEDLES = [\n"
    .implode('', array_map(
        static fn (string $needle): string => '        '.var_export($needle, true).",\n",
        $needles,
    ))
    .'    ];';

$updated = preg_replace(
    '/protected const AI_BOT_USER_AGENT_NEEDLES = \[\n.*?^    \];/ms',
    $replacement,
    $source,
    count: $replacements,
);

if ($updated === null || $replacements !== 1) {
    fwrite(STDERR, "Failed to replace AI_BOT_USER_AGENT_NEEDLES in {$targetFile}\n");
    exit(1);
}

if ($updated === $source) {
    echo "AI bot user-agent list is already up to date ({$sourceUrl}).\n";
    exit(0);
}

file_put_contents($targetFile, $updated);

echo 'Updated AI bot user-agent list with '.count($needles)." needles from {$sourceUrl}.\n";

/**
 * @param  array<mixed>  $payload
 * @return list<string>
 */
function extractAiBotNeedles(array $payload): array
{
    $candidates = [];

    if (isset($payload['bots']) && is_array($payload['bots'])) {
        foreach ($payload['bots'] as $bot) {
            if (is_array($bot)) {
                $candidates = array_merge($candidates, extractBotCandidates($bot));
            }
        }
    } else {
        $candidates = array_keys($payload);
    }

    $needles = [];

    foreach ($candidates as $candidate) {
        if (! is_string($candidate)) {
            continue;
        }

        $needle = normalizeNeedle($candidate);

        if ($needle !== null) {
            $needles[$needle] = true;
        }
    }

    $needles = array_keys($needles);
    sort($needles, SORT_STRING);

    return array_values($needles);
}

/**
 * @param  array<string, mixed>  $bot
 * @return list<string>
 */
function extractBotCandidates(array $bot): array
{
    $candidates = [];

    foreach (['name', 'user_agent', 'userAgent', 'user_agent_string', 'userAgentString', 'pattern'] as $key) {
        if (isset($bot[$key]) && is_string($bot[$key])) {
            $candidates[] = $bot[$key];
        }
    }

    foreach (['user_agents', 'userAgents', 'patterns'] as $key) {
        if (! isset($bot[$key]) || ! is_array($bot[$key])) {
            continue;
        }

        foreach ($bot[$key] as $value) {
            if (is_string($value)) {
                $candidates[] = $value;
            }
        }
    }

    return $candidates;
}

function normalizeNeedle(string $candidate): ?string
{
    $candidate = trim($candidate);
    $candidate = preg_replace('/^user-agent:\s*/i', '', $candidate) ?? $candidate;
    $candidate = trim($candidate, " \t\n\r\0\x0B`'\"");

    if (preg_match('/compatible;\s*([^;)\s]+)/i', $candidate, $matches) === 1) {
        $candidate = $matches[1];
    }

    $candidate = preg_replace('/\/[0-9][^;\s)]*/', '', $candidate) ?? $candidate;
    $candidate = strtolower(trim($candidate));

    if ($candidate === '' || strlen($candidate) < 3) {
        return null;
    }

    if (in_array($candidate, ['bot', 'crawler', 'scraper', 'spider'], true)) {
        return null;
    }

    if (! preg_match('/^[a-z0-9._ -]+$/', $candidate)) {
        return null;
    }

    return str_replace(' ', '-', $candidate);
}
