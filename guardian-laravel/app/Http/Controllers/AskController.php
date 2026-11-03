<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $apiKey = config('guardian.ai.api_key');

        if (! \is_string($apiKey) || $apiKey === '') {
            return response()->json([
                'error' => 'Chatbot belum dikonfigurasi. Tambahkan GEMINI_API_KEY di environment server.',
            ], 503);
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->post(
                    rtrim(config('guardian.ai.endpoint'), '/').'/'.rawurlencode(config('guardian.ai.model')).':generateContent',
                    [
                        'system_instruction' => [
                            'parts' => [[
                                'text' => 'Kamu adalah Guardian, asisten edukasi keamanan digital berbahasa Indonesia. Jawab dengan jelas dan ringkas. Jangan pernah meminta OTP, PIN, kata sandi, atau data pribadi. Ingatkan pengguna untuk memverifikasi informasi melalui kanal resmi.',
                            ]],
                        ],
                        'contents' => [[
                            'parts' => [['text' => $data['question']]],
                        ]],
                        'generationConfig' => [
                            'maxOutputTokens' => 400,
                            'temperature' => 0.4,
                        ],
                    ]
                );
        } catch (ConnectionException $exception) {
            Log::warning('Gemini chat request could not connect.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Layanan chatbot sedang tidak dapat dihubungi. Coba lagi sebentar lagi.',
            ], 502);
        }

        if (! $response->successful()) {
            Log::warning('Gemini chat request failed.', [
                'status' => $response->status(),
            ]);

            return response()->json([
                'error' => 'Layanan chatbot gagal menjawab. Coba lagi sebentar lagi.',
            ], 502);
        }

        $answer = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! \is_string($answer) || trim($answer) === '') {
            Log::warning('Gemini chat response did not contain an answer.');

            return response()->json([
                'error' => 'Layanan chatbot memberikan jawaban yang tidak valid. Coba lagi.',
            ], 502);
        }

        return response()->json(['answer' => trim($answer)]);
    }
}
