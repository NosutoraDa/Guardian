<?php

namespace App\Http\Controllers;

use App\Services\VirusTotalFileChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FileScanController extends Controller
{
    public function store(Request $request, VirusTotalFileChecker $checker): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'share_file_with_virustotal' => ['required', 'accepted'],
        ]);

        $key = 'guardian:files:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'error' => 'Batas pemindaian file aplikasi tercapai. Coba lagi dalam satu menit.',
            ], 429);
        }

        RateLimiter::hit($key, 60);
        $result = $checker->scan($data['file']);

        if (isset($result['analysis_id'], $result['file_hash'])) {
            $request->session()->put('guardian_file_scan', [
                'analysis_id' => $result['analysis_id'],
                'file_hash' => $result['file_hash'],
                'name' => $data['file']->getClientOriginalName(),
            ]);
            unset($result['analysis_id']);
        } else {
            $request->session()->forget('guardian_file_scan');
        }

        return response()->json($result);
    }

    public function analysis(Request $request, VirusTotalFileChecker $checker): JsonResponse
    {
        $scan = $request->session()->get('guardian_file_scan');

        if (! is_array($scan)
            || ! is_string($scan['analysis_id'] ?? null)
            || strlen($scan['analysis_id']) > 500
            || preg_match('/\A[A-Za-z0-9_+\/=:-]+\z/', $scan['analysis_id']) !== 1
            || ! is_string($scan['file_hash'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/i', $scan['file_hash']) !== 1) {
            return response()->json([
                'error' => 'Tidak ada pemindaian file tertunda untuk sesi ini.',
            ], 404);
        }

        $key = 'guardian:file-poll:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            return response()->json([
                'error' => 'Terlalu banyak permintaan status. Tunggu sebelum mencoba lagi.',
            ], 429);
        }

        RateLimiter::hit($key, 60);
        $result = $checker->checkAnalysis($scan['analysis_id']);
        $result['file_hash'] = $scan['file_hash'];
        $result['file_name'] = $scan['name'] ?? 'File yang dipindai';

        if (! in_array($result['status'], ['pending', 'rate_limited'], true)) {
            $request->session()->forget('guardian_file_scan');
        }

        return response()->json($result);
    }
}
