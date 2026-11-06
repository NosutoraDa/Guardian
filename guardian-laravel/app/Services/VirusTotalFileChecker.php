<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirusTotalFileChecker
{
    private const API_URL = 'https://www.virustotal.com/api/v3';

    public function scan(UploadedFile $file): array
    {
        $apiKey = config('guardian.virustotal.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return $this->unavailable('VirusTotal belum dikonfigurasi. Tambahkan VIRUSTOTAL_API_KEY ke environment server.');
        }
        $path = $file->getRealPath();
        $stream = $path ? fopen($path, 'rb') : false;
        if ($stream === false) {
            return $this->unavailable('File tidak dapat dibaca untuk diperiksa.');
        }
        $fileHash = hash_file('sha256', $path);
        if (! is_string($fileHash)) {
            fclose($stream);
            return $this->unavailable('Hash file tidak dapat dibuat untuk menautkan hasil analisis.');
        }

        $filename = basename($file->getClientOriginalName()) ?: 'uploaded-file';
        try {
            $response = Http::withHeaders(['x-apikey' => $apiKey])
                ->connectTimeout(5)->timeout(60)
                ->attach('file', $stream, $filename)
                ->post(self::API_URL.'/files');
        } catch (ConnectionException $exception) {
            Log::warning('VirusTotal file upload could not connect.', ['message' => $exception->getMessage()]);
            return $this->unavailable('VirusTotal tidak dapat dihubungi. Coba lagi nanti.');
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            $providerCode = data_get($response->json(), 'error.code');
            if ($response->status() === 409 && $providerCode === 'AlreadySubmittedError') {
                $existingReport = $this->getFileReport($fileHash);
                if (! isset($existingReport['error'])) {
                    return [
                        'status' => 'complete',
                        'verdict' => $existingReport['verdict'],
                        'message' => 'File ini sudah pernah dikirim. Berikut laporan VirusTotal yang tersedia. '.$existingReport['message'],
                        'detections' => $existingReport['detections'],
                        'engines' => $existingReport['engines'],
                        'stats' => $existingReport['stats'],
                        'file_hash' => $fileHash,
                    ];
                }

                return $this->unavailable(
                    $existingReport['error'] === 'VirusTotal belum memiliki laporan untuk file ini.'
                        ? 'VirusTotal sudah menerima file ini sebelumnya, tetapi laporannya belum tersedia. Tunggu sebentar lalu coba lagi.'
                        : $existingReport['error'],
                    str_contains($existingReport['error'], 'Batas VirusTotal') ? 'rate_limited' : 'unavailable'
                );
            }

            return $this->upstreamFailure($response->status(), $providerCode);
        }

        $analysisId = data_get($response->json(), 'data.id');
        // VirusTotal file analysis IDs are commonly base64-like and include ':' and '='.
        if (! is_string($analysisId) || strlen($analysisId) > 500 || preg_match('/\A[A-Za-z0-9_+\/=:-]+\z/', $analysisId) !== 1) {
            Log::warning('VirusTotal file upload did not return a valid analysis ID.');
            return $this->unavailable('VirusTotal tidak mengembalikan ID analisis yang valid.');
        }

        return [
            'status' => 'pending',
            'verdict' => null,
            'message' => 'File dikirim ke VirusTotal dan sedang dianalisis. File yang dikirim melalui API publik dapat dibagikan kepada komunitas VirusTotal.',
            'analysis_id' => $analysisId,
            'file_hash' => $fileHash,
        ];
    }

    public function checkAnalysis(string $analysisId): array
    {
        $apiKey = config('guardian.virustotal.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return $this->unavailable('VirusTotal belum dikonfigurasi. Tambahkan VIRUSTOTAL_API_KEY ke environment server.');
        }
        try {
            $response = Http::withHeaders(['x-apikey' => $apiKey])->acceptJson()
                ->connectTimeout(5)->timeout(15)
                ->get(self::API_URL.'/analyses/'.rawurlencode($analysisId));
        } catch (ConnectionException $exception) {
            Log::warning('VirusTotal file analysis could not connect.', ['message' => $exception->getMessage()]);
            return $this->unavailable('VirusTotal tidak dapat dihubungi. Coba lagi nanti.');
        }

        if (! $response->successful()) {
            return $this->upstreamFailure($response->status(), data_get($response->json(), 'error.code'));
        }

        $attributes = data_get($response->json(), 'data.attributes');
        if (! is_array($attributes)) {
            Log::warning('VirusTotal file analysis response did not contain analysis details.');
            return $this->unavailable('VirusTotal mengembalikan respons analisis yang tidak valid.');
        }

        if (($attributes['status'] ?? null) !== 'completed') {
            return ['status' => 'pending', 'verdict' => null, 'message' => 'Pemindaian file masih berlangsung. Perbarui status sebentar lagi.'];
        }

        return $this->summary($attributes['stats'] ?? [], 'Analisis VirusTotal selesai.');
    }

    public function getFileReport(string $fileHash): array
    {
        $apiKey = config('guardian.virustotal.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return ['error' => 'VirusTotal belum dikonfigurasi pada server.'];
        }
        try {
            $response = Http::withHeaders(['x-apikey' => $apiKey])->acceptJson()
                ->connectTimeout(5)->timeout(15)
                ->get(self::API_URL.'/files/'.rawurlencode($fileHash));
        } catch (ConnectionException $exception) {
            Log::warning('VirusTotal file report could not connect.', ['message' => $exception->getMessage()]);
            return ['error' => 'VirusTotal tidak dapat dihubungi. Coba lagi nanti.'];
        }

        if (! $response->successful()) {
            Log::warning('VirusTotal file report request failed.', ['status' => $response->status()]);
            return ['error' => $response->status() === 404
                ? 'VirusTotal belum memiliki laporan untuk file ini.'
                : ($response->status() === 429
                    ? 'Batas VirusTotal tercapai. Coba lagi setelah jeda.'
                    : 'Laporan VirusTotal tidak dapat dimuat. Coba lagi nanti.')];
        }

        $attributes = data_get($response->json(), 'data.attributes');
        if (! is_array($attributes)) {
            return ['error' => 'VirusTotal mengembalikan laporan dengan format yang tidak dikenali.'];
        }

        $summary = $this->summary($attributes['last_analysis_stats'] ?? [], 'Laporan analisis VirusTotal.');
        $vendors = [];
        foreach (is_array($attributes['last_analysis_results'] ?? null) ? $attributes['last_analysis_results'] : [] as $vendor) {
            if (! is_array($vendor)) continue;
            $vendors[] = [
                'name' => (string) ($vendor['engine_name'] ?? 'Vendor keamanan'),
                'category' => (string) ($vendor['category'] ?? 'undetected'),
                'result' => (string) ($vendor['result'] ?? 'Tidak ada deteksi'),
            ];
        }
        usort($vendors, static fn (array $a, array $b): int => strcmp($a['category'], $b['category']));

        return array_merge($summary, [
            'name' => (string) ($attributes['meaningful_name'] ?? $attributes['names'][0] ?? ''),
            'type' => (string) ($attributes['type_description'] ?? 'Tidak diketahui'),
            'size' => is_numeric($attributes['size'] ?? null) ? (int) $attributes['size'] : null,
            'reputation' => is_numeric($attributes['reputation'] ?? null) ? (int) $attributes['reputation'] : null,
            'last_analysis_date' => is_numeric($attributes['last_analysis_date'] ?? null) ? (int) $attributes['last_analysis_date'] : null,
            'vendors' => $vendors,
        ]);
    }

    private function summary(mixed $stats, string $message): array
    {
        $allowed = ['malicious', 'suspicious', 'harmless', 'undetected', 'timeout', 'failure', 'type-unsupported', 'confirmed-timeout'];
        $stats = is_array($stats) ? array_intersect_key($stats, array_flip($allowed)) : [];
        foreach ($stats as $key => $value) $stats[$key] = is_numeric($value) ? (int) $value : 0;

        $detections = ($stats['malicious'] ?? 0) + ($stats['suspicious'] ?? 0);
        $verdict = ($stats['malicious'] ?? 0) > 0 ? 'detected' : (($stats['suspicious'] ?? 0) > 0 ? 'suspicious' : 'no_detections');
        return [
            'status' => 'complete',
            'verdict' => $verdict,
            'message' => $message.' Deteksi: '.$detections.' (berbahaya: '.($stats['malicious'] ?? 0).', mencurigakan: '.($stats['suspicious'] ?? 0).'). Hasil tanpa deteksi bukan jaminan file aman.',
            'detections' => $detections,
            'engines' => array_sum($stats),
            'stats' => $stats,
        ];
    }

    private function upstreamFailure(int $status, mixed $providerCode): array
    {
        Log::warning('VirusTotal file request failed.', ['status' => $status, 'provider_code' => is_string($providerCode) || is_int($providerCode) ? (string) $providerCode : null]);
        return $this->unavailable(match (true) {
            $status === 401 || $status === 403 => 'VirusTotal menolak API key atau akun ini tidak memiliki izin.',
            $status === 413 => 'Ukuran file melebihi batas upload VirusTotal.',
            $status === 429 => 'Batas permintaan VirusTotal tercapai. Tunggu sebelum memindai file lain.',
            default => 'VirusTotal menolak atau tidak dapat memproses file ini. Coba lagi nanti.',
        }, $status === 429 ? 'rate_limited' : 'unavailable');
    }

    private function unavailable(string $message, string $status = 'unavailable'): array
    {
        return ['status' => $status, 'verdict' => null, 'message' => $message];
    }

}
