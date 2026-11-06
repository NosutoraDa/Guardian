<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirusTotalUrlChecker
{
    private const API_URL = 'https://www.virustotal.com/api/v3';
    private const REQUESTS_PER_MINUTE = 4;
    private const REQUESTS_PER_DAY = 500;

    public function checkText(string $text): array
    {
        $apiKey = config('guardian.virustotal.api_key');

        if (! \is_string($apiKey) || $apiKey === '') {
            return $this->result(
                'unavailable',
                'VirusTotal belum dikonfigurasi. Tambahkan VIRUSTOTAL_API_KEY ke environment server.'
            );
        }

        preg_match_all('~https?://[^\s<>"\']+~i', $text, $matches);
        $urls = array_values(array_unique(array_map(
            static fn (string $url): string => rtrim($url, ".,!?;:)]}'\""),
            $matches[0]
        )));

        if ($urls === []) {
            return $this->result('no_url', 'Tidak ditemukan tautan http:// atau https://.');
        }

        if (\count($urls) !== 1) {
            return $this->result('multiple_urls', 'Periksa satu tautan sekaligus agar tautan lain tidak ikut dibagikan.');
        }

        $url = $urls[0];
        $parts = parse_url($url);

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || ! \is_array($parts)
            || ! \in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return $this->result('invalid_url', 'Tautan bukan URL HTTP atau HTTPS publik yang valid.');
        }

        $urlId = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        if ($this->rateLimitReached()) {
            return $this->rateLimited();
        }

        $client = Http::withHeaders(['x-apikey' => $apiKey])
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(8);

        try {
            $response = $client->get(self::API_URL.'/urls/'.$urlId);

            if ($response->status() === 404) {
                return $this->submitForAnalysis($client->asForm(), $url, $urlId);
            }

            if (! $response->successful()) {
                return $this->upstreamFailure($response->status());
            }

            return $this->report(data_get($response->json(), 'data.attributes.last_analysis_stats'), $urlId);
        } catch (ConnectionException $exception) {
            return $this->connectionFailure($exception);
        }
    }

    public function checkAnalysis(string $analysisId, string $urlId): array
    {
        $apiKey = config('guardian.virustotal.api_key');

        if (! \is_string($apiKey) || $apiKey === '') {
            return $this->result(
                'unavailable',
                'VirusTotal is not configured. Add VIRUSTOTAL_API_KEY to the server environment.'
            );
        }

        if ($this->rateLimitReached()) {
            return $this->rateLimited();
        }

        try {
            $response = Http::withHeaders(['x-apikey' => $apiKey])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->get(self::API_URL.'/analyses/'.rawurlencode($analysisId));

            if (! $response->successful()) {
                return $this->upstreamFailure($response->status());
            }

            $attributes = data_get($response->json(), 'data.attributes');

            if (! \is_array($attributes)) {
                Log::warning('VirusTotal analysis response did not contain analysis details.');

                return $this->result('unavailable', 'VirusTotal returned an invalid analysis response.');
            }

            if (($attributes['status'] ?? null) !== 'completed') {
                return [
                    'status' => 'pending',
                    'verdict' => null,
                    'message' => 'VirusTotal masih memindai tautan ini. Periksa kembali sebentar lagi.',
                    'analysis_id' => $analysisId,
                    'url_id' => $urlId,
                    'report_url' => 'https://www.virustotal.com/gui/url/'.$urlId,
                ];
            }

            return $this->report($attributes['stats'] ?? null, $urlId);
        } catch (ConnectionException $exception) {
            return $this->connectionFailure($exception);
        }
    }

    public function getUrlReport(string $urlId): array
    {
        $apiKey = config('guardian.virustotal.api_key');
        if (! \is_string($apiKey) || $apiKey === '') {
            return ['error' => 'VirusTotal belum dikonfigurasi pada server.'];
        }
        if ($this->rateLimitReached()) {
            return ['error' => 'Batas permintaan VirusTotal tercapai. Coba lagi nanti.'];
        }

        try {
            $response = Http::withHeaders(['x-apikey' => $apiKey])
                ->acceptJson()->connectTimeout(3)->timeout(8)
                ->get(self::API_URL.'/urls/'.rawurlencode($urlId));
            if (! $response->successful()) {
                Log::warning('VirusTotal URL report page request failed.', ['status' => $response->status()]);
                return ['error' => $response->status() === 404
                    ? 'VirusTotal belum memiliki laporan untuk tautan ini.'
                    : 'Laporan VirusTotal tidak dapat dimuat. Coba lagi nanti.'];
            }

            $attributes = data_get($response->json(), 'data.attributes');
            if (! \is_array($attributes)) {
                return ['error' => 'VirusTotal mengembalikan laporan dengan format yang tidak dikenali.'];
            }
            $results = is_array($attributes['last_analysis_results'] ?? null) ? $attributes['last_analysis_results'] : [];
            $vendors = [];
            foreach ($results as $vendor) {
                if (! is_array($vendor)) continue;
                $vendors[] = [
                    'name' => (string) ($vendor['engine_name'] ?? 'Vendor keamanan'),
                    'category' => (string) ($vendor['category'] ?? 'undetected'),
                    'result' => (string) ($vendor['result'] ?? 'Tidak ada deteksi'),
                ];
            }
            usort($vendors, static fn (array $a, array $b): int => strcmp($a['category'], $b['category']));
            $stats = $attributes['last_analysis_stats'] ?? [];
            $stats = is_array($stats) ? array_intersect_key($stats, array_flip(['malicious', 'suspicious', 'harmless', 'undetected', 'timeout', 'failure', 'type-unsupported', 'confirmed-timeout'])) : [];

            return [
                'url' => (string) ($attributes['url'] ?? ''),
                'stats' => $stats,
                'vendors' => $vendors,
                'categories' => is_array($attributes['categories'] ?? null) ? $attributes['categories'] : [],
                'reputation' => is_numeric($attributes['reputation'] ?? null) ? (int) $attributes['reputation'] : null,
                'last_analysis_date' => is_numeric($attributes['last_analysis_date'] ?? null) ? (int) $attributes['last_analysis_date'] : null,
                'verdict' => ((int) ($stats['malicious'] ?? 0) > 0) ? 'malicious' : (((int) ($stats['suspicious'] ?? 0) > 0) ? 'suspicious' : 'no_detections'),
            ];
        } catch (ConnectionException $exception) {
            Log::warning('VirusTotal URL report page could not connect.', ['message' => $exception->getMessage()]);
            return ['error' => 'VirusTotal tidak dapat dihubungi. Coba lagi nanti.'];
        }
    }

    private function submitForAnalysis($client, string $url, string $urlId): array
    {
        if ($this->rateLimitReached()) {
            return $this->rateLimited();
        }

        $response = $client->post(self::API_URL.'/urls', ['url' => $url]);

        if (! $response->successful()) {
            return $this->upstreamFailure($response->status());
        }

        $analysisId = data_get($response->json(), 'data.id');

        if (! \is_string($analysisId) || preg_match('/\A[A-Za-z0-9_-]+\z/', $analysisId) !== 1) {
            Log::warning('VirusTotal scan submission did not return a valid analysis ID.');

            return $this->result('unavailable', 'VirusTotal accepted the scan but did not return a usable analysis ID.');
        }

        return [
            'status' => 'pending',
            'verdict' => null,
            'message' => 'Tautan ini belum ada di VirusTotal. Pemindaian dimulai; periksa kembali sebentar lagi.',
            'analysis_id' => $analysisId,
            'url_id' => $urlId,
            'report_url' => 'https://www.virustotal.com/gui/url/'.$urlId,
        ];
    }

    private function report(mixed $stats, string $urlId): array
    {
        if (! \is_array($stats)) {
            Log::warning('VirusTotal URL report did not include analysis statistics.');

            return $this->result('unavailable', 'VirusTotal returned a report without analysis results.');
        }

        $stats = array_intersect_key($stats, array_flip([
            'malicious',
            'suspicious',
            'harmless',
            'undetected',
        ]));

        foreach ($stats as $name => $count) {
            $stats[$name] = \is_int($count) ? $count : 0;
        }

        if (($stats['malicious'] ?? 0) > 0) {
            $verdict = 'malicious';
            $message = 'Satu atau lebih vendor keamanan menandai tautan ini. Jangan membukanya.';
        } elseif (($stats['suspicious'] ?? 0) > 0) {
            $verdict = 'suspicious';
            $message = 'Satu atau lebih vendor keamanan menandai tautan ini sebagai mencurigakan. Verifikasi secara mandiri.';
        } else {
            $verdict = 'no_detections';
            $message = 'Belum ada vendor yang menandai tautan ini. Ini bukan bukti bahwa tautan aman.';
        }

        return [
            'status' => 'report',
            'verdict' => $verdict,
            'message' => $message,
            'stats' => $stats,
            'url_id' => $urlId,
            'report_url' => 'https://www.virustotal.com/gui/url/'.$urlId,
        ];
    }

    private function connectionFailure(ConnectionException $exception): array
    {
        Log::warning('VirusTotal URL request could not connect.', [
            'message' => $exception->getMessage(),
        ]);

        return $this->result('unavailable', 'VirusTotal tidak dapat dihubungi. Coba lagi nanti.');
    }

    private function upstreamFailure(int $status): array
    {
        if ($status === 429) {
            return $this->result('rate_limited', 'Batas permintaan VirusTotal tercapai. Tunggu sebelum memeriksa tautan lain.');
        }

        Log::warning('VirusTotal URL request failed.', ['status' => $status]);

        return $this->result(
            'unavailable',
            $status === 401 || $status === 403
                ? 'VirusTotal menolak API key atau akun ini tidak memiliki izin.'
                : 'VirusTotal tidak dapat memberikan hasil. Coba lagi nanti.'
        );
    }

    private function rateLimitReached(): bool
    {
        return RateLimiter::increment('guardian:virustotal:minute', 60) > self::REQUESTS_PER_MINUTE
            || RateLimiter::increment('guardian:virustotal:day', 86400) > self::REQUESTS_PER_DAY;
    }

    private function rateLimited(): array
    {
        return $this->result('rate_limited', 'Batas VirusTotal aplikasi tercapai. Tunggu sebelum memeriksa tautan lain.');
    }

    private function result(string $status, string $message): array
    {
        return [
            'status' => $status,
            'verdict' => null,
            'message' => $message,
        ];
    }
}
