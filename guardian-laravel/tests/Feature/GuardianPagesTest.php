<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GuardianPagesTest extends TestCase
{
    public function test_home_page_redirects_to_links(): void
    {
        $this->get('/')
            ->assertRedirect('/links');
    }

    public function test_only_links_files_and_chatbot_pages_are_available(): void
    {
        $this->get('/links')->assertOk()->assertSee('Periksa tautan');
        $this->get('/files')->assertOk()->assertSee('Periksa file');
        $this->get('/tanya')->assertOk()->assertSee('Chatbot keamanan digital');
        $this->get('/belajar')->assertNotFound();
        $this->get('/bertindak')->assertNotFound();
        $this->get('/cek')->assertRedirect('/links');
    }

    public function test_header_omits_duplicate_tool_navigation_while_radial_menu_keeps_tools(): void
    {
        $this->get('/links')
            ->assertOk()
            ->assertDontSee('aria-label="Menu utama"', false)
            ->assertSee('class="arc-item radial-chat', false)
            ->assertSee('class="arc-item radial-files', false)
            ->assertSee('class="arc-item radial-links', false);
    }

    public function test_hud_design_renders_radial_tool_links_quick_tips_and_mascot(): void
    {
        $this->get('/links')
            ->assertOk()
            ->assertSee('id="canvas-container"', false)
            ->assertSee('id="toggle-radial-btn"', false)
            ->assertSee('id="quick-tips-panel"', false)
            ->assertSee('id="radial-dial-container"', false)
            ->assertSee('id="mascot-toggle-menu"', false)
            ->assertSee(route('tanya'), false)
            ->assertSee(route('files'), false)
            ->assertSee(route('links'), false)
            ->assertSee('VirusTotal', false)
            ->assertSee('VirusTotal', false);
    }

    public function test_link_scan_requires_explicit_consent(): void
    {
        Http::fake();

        $this->postJson('/api/links', [
            'text' => 'https://example.com',
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_virustotal_check_returns_an_existing_report(): void
    {
        config(['guardian.virustotal.api_key' => 'test-key']);
        Http::fake([
            'www.virustotal.com/api/v3/urls/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'last_analysis_stats' => [
                            'malicious' => 2,
                            'suspicious' => 1,
                            'harmless' => 5,
                            'undetected' => 2,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/links', [
            'text' => 'https://example.com/login',
            'share_url_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('virustotal.status', 'report')
            ->assertJsonPath('virustotal.verdict', 'malicious')
            ->assertJsonPath('virustotal.stats.malicious', 2);

        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->method() === 'GET'
            && str_contains($request->url(), '/api/v3/urls/')
            && $request->hasHeader('x-apikey', 'test-key')
        );
    }

    public function test_new_virustotal_link_is_submitted_and_reported_as_pending(): void
    {
        config(['guardian.virustotal.api_key' => 'test-key']);
        Http::fakeSequence()
            ->push(['error' => 'Not found'], 404)
            ->push(['data' => ['id' => 'analysis-id']], 200);

        $this->postJson('/api/links', [
            'text' => 'https://new-example.test/path',
            'share_url_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('virustotal.status', 'pending')
            ->assertJsonPath('virustotal.analysis_id', 'analysis-id');

        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v3/urls')
            && $request->data() === ['url' => 'https://new-example.test/path']
            && $request->hasHeader('x-apikey', 'test-key')
        );
    }

    public function test_virustotal_analysis_can_be_refreshed(): void
    {
        config(['guardian.virustotal.api_key' => 'test-key']);
        Http::fake([
            'www.virustotal.com/api/v3/analyses/analysis-id' => Http::response([
                'data' => [
                    'attributes' => [
                        'status' => 'completed',
                        'stats' => [
                            'malicious' => 0,
                            'suspicious' => 1,
                            'harmless' => 3,
                            'undetected' => 4,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/links/analysis', [
            'analysis_id' => 'analysis-id',
            'url_id' => 'aHR0cHM6Ly9leGFtcGxlLmNvbQ',
        ])->assertOk()
            ->assertJsonPath('status', 'report')
            ->assertJsonPath('verdict', 'suspicious')
            ->assertJsonPath('stats.suspicious', 1);
    }

    public function test_virustotal_rejects_multiple_urls_and_embedded_credentials(): void
    {
        config(['guardian.virustotal.api_key' => 'test-key']);
        Http::fake();

        $this->postJson('/api/links', [
            'text' => 'https://first.example https://second.example',
            'share_url_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('virustotal.status', 'multiple_urls');

        $this->postJson('/api/links', [
            'text' => 'https://user:password@example.com',
            'share_url_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('virustotal.status', 'invalid_url');

        Http::assertNothingSent();
    }

    public function test_virustotal_missing_key_returns_a_clear_unavailable_status(): void
    {
        config(['guardian.virustotal.api_key' => null]);
        Http::fake();

        $this->postJson('/api/links', [
            'text' => 'https://example.com',
            'share_url_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('virustotal.status', 'unavailable');

        Http::assertNothingSent();
    }

    public function test_virustotal_checks_respect_the_public_api_limit(): void
    {
        config(['guardian.virustotal.api_key' => 'test-key']);
        RateLimiter::clear('guardian:virustotal:minute');
        RateLimiter::clear('guardian:virustotal:day');
        Http::fake([
            'www.virustotal.com/api/v3/urls/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'last_analysis_stats' => ['malicious' => 0],
                    ],
                ],
            ]),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postJson('/api/links', [
                'text' => 'https://example.com',
                'share_url_with_virustotal' => true,
            ]);

            $response->assertJsonPath(
                'virustotal.status',
                $attempt < 5 ? 'report' : 'rate_limited'
            );
        }

        Http::assertSentCount(4);
    }

    public function test_file_upload_requires_explicit_consent(): void
    {
        Http::fake();

        $this->postJson('/api/files', [
            'file' => UploadedFile::fake()->createWithContent('sample.txt', 'test file'),
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_file_upload_is_sent_to_virustotal_after_explicit_consent(): void
    {
        config(['guardian.virustotal.api_key' => 'virustotal-test-key']);
        $multipartBody = null;
        Http::fake([
            'www.virustotal.com/api/v3/files' => function (ClientRequest $request) use (&$multipartBody) {
                $multipartBody = $request->body();

                return Http::response([
                    'data' => ['id' => 'analysis-id-123'],
                ]);
            },
        ]);

        $this->postJson('/api/files', [
            'file' => UploadedFile::fake()->createWithContent('sample.txt', 'file contents'),
            'share_file_with_virustotal' => true,
        ])->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonMissingPath('analysis_id');

        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v3/files')
            && $request->hasHeader('x-apikey', 'virustotal-test-key')
            && str_contains($request->header('Content-Type')[0] ?? '', 'multipart/form-data')
        );
        $this->assertStringContainsString('name="file"; filename="sample.txt"', $multipartBody);
        $this->assertStringContainsString('file contents', $multipartBody);
    }

    public function test_file_scan_report_refresh_is_bound_to_the_session(): void
    {
        config(['guardian.virustotal.api_key' => 'virustotal-test-key']);
        Http::fakeSequence()
            ->push([
                'data' => ['id' => 'analysis-id-456:1475048277=='],
            ])
            ->push([
                'data' => ['attributes' => [
                    'status' => 'completed',
                    'stats' => ['malicious' => 2, 'suspicious' => 0, 'harmless' => 20, 'undetected' => 8],
                ]],
            ]);

        $this->postJson('/api/files', [
            'file' => UploadedFile::fake()->createWithContent('sample.txt', 'file contents'),
            'share_file_with_virustotal' => true,
        ])->assertJsonPath('status', 'pending');

        $this->postJson('/api/files/analysis')
            ->assertOk()
            ->assertJsonPath('status', 'complete')
            ->assertJsonPath('verdict', 'detected')
            ->assertJsonPath('detections', 2);

        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->method() === 'GET'
            && str_ends_with($request->url(), '/api/v3/analyses/analysis-id-456%3A1475048277%3D%3D')
        );
    }

    public function test_file_scan_rejects_oversized_files_and_unbound_report_ids(): void
    {
        config(['guardian.virustotal.api_key' => 'virustotal-test-key']);
        Http::fake();

        $this->postJson('/api/files', [
            'file' => UploadedFile::fake()->create('too-large.bin', 10241),
            'share_file_with_virustotal' => true,
        ])->assertUnprocessable();

        $this->postJson('/api/files/analysis')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_chatbot_uses_gemini_and_returns_its_answer(): void
    {
        config([
            'guardian.ai.api_key' => 'gemini-test-key',
            'guardian.ai.model' => 'gemini-test-model',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'Jangan pernah membagikan OTP.']],
                    ],
                ]],
            ]),
        ]);

        $this->postJson('/api/tanya', [
            'question' => 'Apa yang harus saya lakukan dengan OTP?',
        ])->assertOk()
            ->assertJsonPath('answer', 'Jangan pernah membagikan OTP.');

        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1beta/models/gemini-test-model:generateContent')
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && data_get($request->data(), 'contents.0.parts.0.text') === 'Apa yang harus saya lakukan dengan OTP?'
            && data_get($request->data(), 'system_instruction.parts.0.text') !== null
        );
    }

    public function test_chatbot_reports_missing_api_key_without_local_fallback(): void
    {
        config(['guardian.ai.api_key' => null]);
        Http::fake();

        $this->postJson('/api/tanya', [
            'question' => 'Apa itu phishing?',
        ])->assertStatus(503)
            ->assertJsonPath('error', 'Chatbot belum dikonfigurasi. Tambahkan GEMINI_API_KEY di environment server.');

        Http::assertNothingSent();
    }
}
