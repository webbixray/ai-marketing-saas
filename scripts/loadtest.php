<?php
/**
 * Lightweight concurrent load generator (no external deps).
 *
 * Usage:
 *   php scripts/loadtest.php <base_url> <token> [concurrency=10] [duration_s=30]
 *
 * Mixes read endpoints (GET /api/v1/campaigns, /api/v1/tasks, /api/v1/user,
 * /api/v1/health) and measures p50/p95/p99, throughput, error rate.
 *
 * Note: measures the APPLICATION LAYER via `php artisan serve` (single-process
 * dev server). Production target (php-fpm/octane) will be faster for
 * connection handling; use these numbers as app-layer baselines only.
 */

$base = rtrim($argv[1] ?? '', '/');
$token = $argv[2] ?? '';
$workers = max(1, (int) ($argv[3] ?? 10));
$duration = max(2, (int) ($argv[4] ?? 30));

if ($base === '' || $token === '') {
    fwrite(STDERR, "usage: php loadtest.php <base_url> <bearer_token> [concurrency] [duration_s]\n");
    exit(2);
}

$paths = [
    'GET /api/v1/campaigns',
    'GET /api/v1/tasks',
    'GET /api/v1/clients',
    'GET /api/v1/user',
    'GET /api/v1/health',
];

$end = microtime(true) + $duration;
$samples = [];
$errors = 0;
$noResponse = 0;
$total = 0;

$mh = curl_multi_init();
$starts = [];

function launch(&$mh, string $base, string $token, int &$inflight, int $maxInflight, array &$starts): void
{
    while ($inflight < $maxInflight) {
        $path = '/api/v1/' . ['campaigns', 'tasks', 'clients', 'user', 'health'][random_int(0, 4)];
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $starts[spl_object_id($ch)] = microtime(true);
        curl_multi_add_handle($mh, $ch);
        $inflight++;
    }
}

$inflight = 0;
launch($mh, $base, $token, $inflight, $workers, $starts);

while (true) {
    do {
        $status = curl_multi_exec($mh, $inflight);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    while (($done = curl_multi_info_read($mh)) !== false) {
        $ch = $done['handle'];
        $t0 = $starts[spl_object_id($ch)] ?? microtime(true);
        unset($starts[spl_object_id($ch)]);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        $samples[] = (microtime(true) - $t0) * 1000;
        $total++;
        if ($httpCode === 0) {
            $noResponse++;
            if ($noResponse <= 3) {
                fwrite(STDERR, "DEBUG no-response: err=" . ($curlErr ?: 'none')
                    . " result=" . $done['result'] . " code=0\n");
            }
        } elseif ($httpCode >= 400) {
            $errors++;
            if ($errors <= 3) {
                fwrite(STDERR, "DEBUG http $httpCode\n");
            }
        }
        if ($curlErr !== '' && $noResponse > 3 && rand(0, 500) === 0) {
            fwrite(STDERR, "DEBUG sample curl error: $curlErr\n");
        }
        curl_multi_remove_handle($mh, $ch);
        if (microtime(true) < $end) {
            launch($mh, $base, $token, $inflight, $workers, $starts);
        }
    }

    if ($inflight === 0 && microtime(true) >= $end) {
        break;
    }
    curl_multi_select($mh, 0.05);
}

curl_multi_close($mh);

if ($total === 0) {
    fwrite(STDERR, "no requests completed\n");
    exit(1);
}

usort($samples, fn ($a, $b) => $a <=> $b);
$pct = fn (float $f) => $samples[(int) floor($f * ($total - 1))];
$wall = $duration;

echo "=== Load test: $base ===\n";
echo "concurrency:  $workers\n";
echo "duration:     {$wall}s\n";
echo "requests:     $total\n";
echo "errors(4xx/5xx): $errors (" . round(100 * $errors / $total, 2) . "%)\n";
echo "throughput:   " . round($total / $wall, 1) . " req/s\n";
echo "latency ms:   avg=" . round(array_sum($samples) / $total, 1)
    . " p50=" . round($pct(0.50), 1)
    . " p95=" . round($pct(0.95), 1)
    . " p99=" . round($pct(0.99), 1)
    . " max=" . round(max($samples), 1) . "\n";
echo "PASS threshold (app-layer baseline): p95 < 1000ms, error rate < 1% (single-process dev server)\n";
$pass = ($pct(0.95) < 1000) && ($errors / $total < 0.01);
echo $pass ? "RESULT: PASS (baseline)\n" : "RESULT: REVIEW\n";
exit($pass ? 0 : 3);
