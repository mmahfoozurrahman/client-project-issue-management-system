<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Lesson;
use App\Models\LessonNarration;
use App\Models\User;
use App\Models\Vault;
use App\Services\LessonNarrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LessonNarrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_lesson_narration_directly(): void
    {
        Storage::fake('public');

        $dummyPcm = str_repeat("\x00\x00", 24000);
        $base64Pcm = base64_encode($dummyPcm);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/pcm;rate=24000',
                                        'data' => $base64Pcm,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 30,
                    'candidatesTokenCount' => 120,
                    'totalTokenCount' => 150,
                ],
            ], 200),
        ]);

        config([
            'lesson_narration.gemini.api_key' => 'fake-gemini-key',
            'lesson_narration.storage_disk' => 'public',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $vault = Vault::create([
            'title' => 'Test Vault',
            'slug' => 'test-vault',
            'created_by' => $admin->id,
            'is_published' => true,
        ]);
        $folder = Folder::create([
            'vault_id' => $vault->id,
            'title' => 'Test Folder',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'vault_id' => $vault->id,
            'folder_id' => $folder->id,
            'title' => 'লারাভেল পরিচিতি',
            'is_published' => true,
            'is_free' => true,
            'sort_order' => 1,
            'content' => '<p>লারাভেল একটি চমৎকার পিএইচপি ফ্রেমওয়ার্ক।</p>',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.vaults.folders.lessons.narration.generate', [$vault, $folder, $lesson]), [
                'force' => false,
            ]);

        $response->assertRedirect(route('admin.vaults.folders.lessons', [$vault, $folder]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_narrations', [
            'lesson_id' => $lesson->id,
            'provider' => 'gemini',
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('ai_usage_logs', [
            'lesson_id' => $lesson->id,
            'feature' => 'lesson_narration',
            'status' => 'success',
        ]);
    }

    public function test_gemini_narration_service_generates_and_stores_wav_audio(): void
    {
        Storage::fake('public');

        // 1 second of dummy PCM data (24000 samples * 2 bytes = 48000 bytes)
        $dummyPcm = str_repeat("\x00\x00", 24000);
        $base64Pcm = base64_encode($dummyPcm);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/pcm;rate=24000',
                                        'data' => $base64Pcm,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 30,
                    'candidatesTokenCount' => 120,
                    'totalTokenCount' => 150,
                ],
            ], 200),
        ]);

        config([
            'lesson_narration.gemini.api_key' => 'fake-gemini-key',
            'lesson_narration.storage_disk' => 'public',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $vault = Vault::create([
            'title' => 'Test Vault',
            'slug' => 'test-vault',
            'created_by' => $admin->id,
            'is_published' => true,
        ]);
        $folder = Folder::create([
            'vault_id' => $vault->id,
            'title' => 'Test Folder',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'vault_id' => $vault->id,
            'folder_id' => $folder->id,
            'title' => 'লারাভেল পরিচিতি',
            'is_published' => true,
            'is_free' => true,
            'sort_order' => 1,
            'content' => '<p>লারাভেল আধুনিক ওয়েবের জন্য একটি চমৎকার ইকোসিস্টেম।</p>',
        ]);

        $service = app(LessonNarrationService::class);
        $record = $service->generate($lesson);

        $this->assertEquals('ready', $record->status);
        $this->assertEquals('gemini', $record->provider);
        $this->assertNotEmpty($record->audio_paths);
        $this->assertStringEndsWith('.wav', $record->audio_paths[0]);

        Storage::disk('public')->assertExists($record->audio_paths[0]);

        $state = $service->currentState($lesson);
        $this->assertTrue($state['is_available']);
        $this->assertEquals('ready', $state['status']);
        $this->assertCount(1, $state['audio_urls']);
    }

    public function test_authorized_users_can_stream_private_narration_audio_only_for_recorded_tracks(): void
    {
        Storage::fake('private');
        config(['lesson_narration.storage_disk' => 'private']);

        $admin = User::factory()->create(['role' => 'admin']);
        $vault = Vault::create([
            'title' => 'Test Vault',
            'slug' => 'test-vault',
            'created_by' => $admin->id,
            'is_published' => true,
        ]);
        $folder = Folder::create([
            'vault_id' => $vault->id,
            'title' => 'Test Folder',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'vault_id' => $vault->id,
            'folder_id' => $folder->id,
            'title' => 'Test Lesson',
            'is_published' => true,
            'is_free' => true,
            'sort_order' => 1,
            'content' => '<p>Test content.</p>',
        ]);
        $path = "lesson-narrations/{$lesson->id}/part-001.wav";

        Storage::disk('private')->put($path, 'RIFF-test-audio');
        LessonNarration::create([
            'lesson_id' => $lesson->id,
            'provider' => 'gemini',
            'locale' => 'bn-IN',
            'source_hash' => 'test-source-hash',
            'status' => 'ready',
            'audio_paths' => [$path],
        ]);

        $this->get(route('lessons.narration.audio', [$vault, $lesson, 0]))
            ->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->get(route('lessons.narration.audio', [$vault, $lesson, 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');

        $this->actingAs($admin)
            ->get(route('lessons.narration.audio', [$vault, $lesson, 1]))
            ->assertNotFound();
    }

    public function test_gemini_narration_records_ai_usage_log_when_rate_limited(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Resource has been exhausted (e.g. check quota).',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429, ['Retry-After' => '45']),
        ]);

        config([
            'lesson_narration.gemini.api_key' => 'fake-gemini-key',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $vault = Vault::create([
            'title' => 'Test Vault',
            'slug' => 'test-vault',
            'created_by' => $admin->id,
            'is_published' => true,
        ]);
        $folder = Folder::create([
            'vault_id' => $vault->id,
            'title' => 'Test Folder',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'vault_id' => $vault->id,
            'folder_id' => $folder->id,
            'title' => 'লারাভেল পরিচিতি',
            'is_published' => true,
            'is_free' => true,
            'sort_order' => 1,
            'content' => '<p>লারাভেল টেস্ট কন্টেন্ট।</p>',
        ]);

        $service = app(LessonNarrationService::class);

        try {
            $service->generate($lesson);
            $this->fail('Expected RuntimeException on rate limit not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('রেট লিমিট', $e->getMessage());
        }

        $this->assertDatabaseHas('ai_usage_logs', [
            'lesson_id' => $lesson->id,
            'feature' => 'lesson_narration',
            'status' => 'rate_limited',
        ]);

        $this->assertDatabaseHas('lesson_narrations', [
            'lesson_id' => $lesson->id,
            'status' => 'failed',
        ]);
    }

    public function test_code_blocks_and_flow_diagrams_are_converted_to_fluent_narration(): void
    {
        $service = app(LessonNarrationService::class);

        $flowCode = "HTML + CSS\n↓\nPHP Programming\n↓\nOOP\n↓\nSQL + Database Design\n↓\nLaravel MVC";
        $flowNarration = $service->convertCodeBlockToNarration($flowCode);

        $this->assertStringContainsString('ধারাবাহিক ফ্লো অনুযায়ী', $flowNarration);
        $this->assertStringContainsString('প্রথমে HTML এবং CSS', $flowNarration);
        $this->assertStringContainsString('এরপর PHP Programming', $flowNarration);
        $this->assertStringContainsString('এরপর OOP', $flowNarration);
        $this->assertStringContainsString('Laravel MVC', $flowNarration);

        $cmdCode = "php artisan make:model Post -m";
        $cmdNarration = $service->convertCodeBlockToNarration($cmdCode);
        $this->assertStringContainsString('টার্মিনাল কমান্ড', $cmdNarration);
        $this->assertStringContainsString('php artisan make:model Post -m', $cmdNarration);

        $admin = User::factory()->create(['role' => 'admin']);
        $vault = Vault::create(['title' => 'V', 'slug' => 'v', 'created_by' => $admin->id]);
        $folder = Folder::create(['vault_id' => $vault->id, 'title' => 'F', 'sort_order' => 1]);
        $lesson = Lesson::create([
            'vault_id' => $vault->id,
            'folder_id' => $folder->id,
            'title' => 'লারাভেল স্কিল ম্যাপ',
            'sort_order' => 1,
            'content' => "<p>উদাহরণ হিসেবে:</p><pre><code>HTML + CSS\n↓\nPHP Programming\n↓\nLaravel MVC</code></pre><p>এই ফ্লো গুরুত্বপূর্ণ।</p>",
        ]);

        $segments = $service->buildNarrationSegments($lesson);
        $this->assertNotEmpty($segments);
        $fullText = implode(' ', $segments);

        $this->assertStringContainsString('উদাহরণ হিসেবে:', $fullText);
        $this->assertStringContainsString('ধারাবাহিক ফ্লো অনুযায়ী', $fullText);
        $this->assertStringContainsString('এই ফ্লো গুরুত্বপূর্ণ।', $fullText);
    }

    public function test_heading_heavy_lesson_content_uses_fewer_safe_gemini_chunks(): void
    {
        $service = app(LessonNarrationService::class);
        $lesson = new Lesson([
            'title' => 'লারাভেল ফুল-স্ট্যাক ডেভেলপার ব্লুপ্রিন্ট',
            'content' => '<p>এই Blueprint-কে আমরা আটটি বড় Layer-এ ভাগ করব।</p><p>প্রতিটি Layer একটি আলাদা Skill Area represent করবে, কিন্তু এগুলো একে অপরের থেকে সেপারেট/আলাদা কিছু নয়। বরং একটি Layer-এর ওপর পরের Layer দাঁড়ানো।</p><h3>আটটি প্রধান Layer</h3><h2>Layer 1 — Web Foundation</h2><p>Browser, Server, HTTP, HTML, CSS এবং Responsive Design-এর Basic Foundation তৈরি হবে এই Layer-এ।</p><h2>Layer 2 — Programming with PHP</h2><p>Programming Logic, Server-side Execution, Data Processing এবং Object-Oriented Thinking-এর শুরু হবে এখানে।</p><h2>Layer 3 — Database Engineering with MySQL</h2><p>SQL, Table Design, Relationship, JOIN, Constraint, ERD এবং Transaction নিয়ে strong Database Foundation তৈরি হবে।</p><h2>Layer 4 — Laravel Core Development</h2><p>Laravel Request Lifecycle, Routing, Controller, Middleware, Validation, Migration, Eloquent, Authentication এবং Authorization শেখা হবে এই Layer-এ।</p><h2>Layer 5 — Modern JavaScript</h2><p>Modern Syntax, Array এবং Object Manipulation, Async Programming, API Call এবং Error Handling-এর Foundation তৈরি হবে।</p><h2>Layer 6 — Vue 3 + Inertia.js</h2><p>Component Thinking, Reactivity, Form Handling এবং Laravel Backend-এর সঙ্গে Vue Frontend connect করার complete Flow শেখা হবে।</p><h2>Layer 7 — Software Engineering &amp; Production Skills</h2><p>Testing, Security, Performance, Git, Deployment, Queue, Scheduler, Monitoring এবং Maintainability-এর মতো Professional Skill এখানে যোগ হবে।</p><h2>Layer 8 — Project, Portfolio &amp; Career Readiness</h2><p>আগের সব Layer-এর Skill ব্যবহার করে Production-grade Project তৈরি, Portfolio সাজানো এবং Career-ready হওয়ার প্রস্তুতি নেওয়া হবে।</p><h2>এটিকে শুধু Course Module হিসেবে দেখবেন না</h2><p>এই Layerগুলোকে শুধু আটটি Course Module হিসেবে দেখলে পুরো Picture পরিষ্কার হবে না।</p><p>বরং এটিকে একটি <strong>Skill Dependency Map</strong> হিসেবে দেখা উচিত।</p><p>অর্থাৎ, কোন Skill-এর ওপর পরের Skill দাঁড়ায় এবং কোথায় Foundation দুর্বল হলে পরে Problem তৈরি হতে পারে—এই connection বোঝা জরুরি।</p><p>এই Flow-তে প্রতিটি Step-এর একটি clear purpose আছে।</p><p>HTML এবং CSS না বুঝে UI Layer পরিষ্কার হবে না।</p><p>PHP এবং OOP দুর্বল হলে Laravel-এর Class, Dependency Injection এবং Service Container বুঝতে সমস্যা হবে।</p><p>SQL এবং Database Design না বুঝে Eloquent ব্যবহার করলে Query, Relationship এবং Performance magic-এর মতো লাগবে।</p><p>JavaScript না বুঝে Vue শেখা শুরু করলে Component Logic এবং Reactivity দ্রুত confusing হয়ে যেতে পারে।</p><p>আর Testing, Security এবং Deployment ছাড়া Feature তৈরি করা গেলেও সেটি Professional বা Production-ready Application হয়ে ওঠে না।</p><p>তাই এই Blueprint-এর উদ্দেশ্য শুধু কী কী শিখতে হবে, সেই list দেওয়া নয়।</p><p>এর উদ্দেশ্য হলো শেখার সঠিক order এবং প্রতিটি Skill-এর dependency পরিষ্কার করা।</p><p>এর পরের লেসন থেকেই পুরো ওয়েব ফাউন্ডেশনের জার্নি শুরু হবে। তাই ওই লেসনের লক্ষ্য হবে Web-এর core mechanism এবং User Interface-এর basic structure পরিষ্কার করা।</p><p>কারণ Laravel, Vue, Inertia বা API শেখার আগে আপনাকে বুঝতে হবে Web আসলে কীভাবে কাজ করে।</p><p>Browser কীভাবে Request পাঠায়, Server কীভাবে Response দেয়, HTML কীভাবে Page Structure তৈরি করে এবং CSS কীভাবে সেই Structure-কে Responsive Interface-এ রূপ দেয়—এই Basic Foundation ছাড়া পরের Technologyগুলো অনেক সময় শুধু Syntax মনে হবে।</p>',
        ]);

        $segments = $service->buildNarrationSegments($lesson);
        $legacyChunks = $service->chunkSegments($segments, 850);
        $chunks = $service->chunkSegments($segments, 2400);

        $this->assertNotEmpty($segments);
        $this->assertLessThan(count($legacyChunks), count($chunks));
        $this->assertContains('Layer 1 — Web Foundation', $segments);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(2400, mb_strlen($chunk));
        }

        $longCodeTokenChunks = $service->chunkSegments([str_repeat('x', 2401)], 2400);
        $this->assertCount(2, $longCodeTokenChunks);
        $this->assertSame(2400, mb_strlen($longCodeTokenChunks[0]));
    }

    public function test_inline_code_paragraphs_are_read_naturally_without_fragmentation(): void
    {
        $service = app(LessonNarrationService::class);
        $lesson = new Lesson([
            'title' => 'CSS এর উদ্দেশ্য',
            'content' => '<p>এই Box-এর চারটি গুরুত্বপূর্ণ অংশ হলো <code>content</code>, <code>padding</code>, <code>border</code> এবং <code>margin</code>।</p><p><code>block</code>, <code>inline</code>, <code>inline-block</code>, <code>flex</code>, <code>grid</code> এবং <code>none</code>—এই Display Properties কাজ করে।</p>',
        ]);

        $segments = $service->buildNarrationSegments($lesson);
        $fullText = implode(' ', $segments);

        $this->assertStringNotContainsString('কোড উদাহরণ: content', $fullText);
        $this->assertStringNotContainsString('কোড উদাহরণ: block', $fullText);
        $this->assertStringContainsString('এই Box-এর চারটি গুরুত্বপূর্ণ অংশ হলো content, padding, border এবং margin।', $fullText);
    }
}
