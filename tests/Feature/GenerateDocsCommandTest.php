<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GenerateDocsCommandTest extends TestCase
{
    private string $docsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Write into a throwaway directory, never the real docs/ — this suite
        // runs against an in-memory sqlite DB (phpunit.xml), so live-schema
        // extraction would be empty and must never clobber the real output.
        $this->docsPath = storage_path('framework/testing/docs-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docsPath);

        parent::tearDown();
    }

    public function test_it_generates_all_expected_doc_files_with_key_sections(): void
    {
        $this->artisan('docs:generate', ['--path' => $this->docsPath])->assertExitCode(0);

        $this->assertFileExists($this->docsPath.'/README.md');
        $this->assertFileExists($this->docsPath.'/architecture.md');
        $this->assertFileExists($this->docsPath.'/controllers.md');
        $this->assertFileExists($this->docsPath.'/models.md');
        $this->assertFileExists($this->docsPath.'/routes.md');

        $this->assertStringContainsString('# Controllers', File::get($this->docsPath.'/controllers.md'));
        $this->assertStringContainsString('# Models', File::get($this->docsPath.'/models.md'));
        $this->assertStringContainsString('# Routes', File::get($this->docsPath.'/routes.md'));
        $this->assertStringContainsString('## Authentication', File::get($this->docsPath.'/architecture.md'));
        $this->assertStringContainsString('## Reference', File::get($this->docsPath.'/README.md'));
    }

    public function test_check_flag_passes_immediately_after_a_fresh_generate(): void
    {
        $this->artisan('docs:generate', ['--path' => $this->docsPath])->assertExitCode(0);
        $this->artisan('docs:generate', ['--path' => $this->docsPath, '--check' => true])->assertExitCode(0);
    }

    public function test_check_flag_detects_drift_when_a_route_is_added(): void
    {
        $this->artisan('docs:generate', ['--path' => $this->docsPath])->assertExitCode(0);

        Route::get('/docs-generator-test-drift-route', fn () => 'x')->name('docs.generator.test.drift');

        $this->artisan('docs:generate', ['--path' => $this->docsPath, '--check' => true])->assertExitCode(1);
    }

    public function test_it_extracts_form_request_rules_and_describes_sanctum_auth(): void
    {
        $this->artisan('docs:generate', ['--path' => $this->docsPath])->assertExitCode(0);

        $controllers = File::get($this->docsPath.'/controllers.md');
        $architecture = File::get($this->docsPath.'/architecture.md');

        // The controllers are thin and validate via Form Requests, so the
        // extractor must resolve rules() from the type-hinted request — this
        // confirms it pulled real rule data, not just method headings.
        $this->assertStringContainsString('Api\V1\Auth\AdminAuthController', $controllers);
        $this->assertStringContainsString('required|email|max:50|unique:users,email', $controllers);

        // Auth section reflects this app's Sanctum design, not JWT/cookies.
        $this->assertStringContainsString('Sanctum', $architecture);
    }
}
