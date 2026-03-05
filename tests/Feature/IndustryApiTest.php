<?php

namespace Tests\Feature;

use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test industries index endpoint returns successful response.
     */
    public function test_industries_index_returns_successful_response(): void
    {
        $response = $this->get('/api/v1/industries');

        $response->assertStatus(200);
    }

    /**
     * Test industries index returns all industries without pagination.
     */
    public function test_industries_index_returns_all_industries(): void
    {
        // Create test industries
        $industry1 = CatalogIndustry::create(['slug' => 'test-industry-1']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry1->id,
            'name' => 'Test Industry 1',
            'status' => 1,
        ]);

        $industry2 = CatalogIndustry::create(['slug' => 'test-industry-2']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry2->id,
            'name' => 'Test Industry 2',
            'status' => 1,
        ]);

        $response = $this->get('/api/v1/industries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'slug', 'meta', 'categories_count'],
                ],
                'message',
            ])
            ->assertJsonCount(2, 'data'); // Verify all industries are returned
    }

    /**
     * Test industries show endpoint.
     */
    public function test_industries_show_returns_industry_details(): void
    {
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $response = $this->get("/api/v1/industries/{$industry->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'slug',
                    'meta' => ['name', 'status'],
                    'categories_count',
                ],
                'message',
            ]);
    }

    /**
     * Test industries show endpoint with invalid ID.
     */
    public function test_industries_show_returns_404_for_invalid_id(): void
    {
        $response = $this->get('/api/v1/industries/99999');

        $response->assertStatus(404);
    }

    /**
     * Test industries search functionality.
     */
    public function test_industries_search_by_name(): void
    {
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $response = $this->get('/api/v1/industries?q=Test');

        $response->assertStatus(200);
    }
}
