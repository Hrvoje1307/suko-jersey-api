<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_page_renders_swagger_ui(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee(url('openapi.yaml'), false);
    }

    public function test_spec_file_is_published_and_covers_every_api_route(): void
    {
        $spec = public_path('openapi.yaml');
        $this->assertFileExists($spec);

        $contents = file_get_contents($spec);

        // Svaka /api ruta mora postojati u specu (bez `api` prefiksa, s {param} placeholderima).
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->map(fn ($route) => '/'.substr($route->uri(), 4))
            ->map(fn ($uri) => preg_replace('/\{(\w+)\}/', '{id}', $uri))
            ->unique();

        $this->assertGreaterThan(0, $routes->count());

        foreach ($routes as $uri) {
            $this->assertStringContainsString("  {$uri}:", $contents, "Spec ne pokriva rutu {$uri}");
        }
    }
}
