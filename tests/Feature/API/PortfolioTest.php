<?php

namespace Tests\Feature\API;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching portfolio automatically provisions a new one.
     */
    public function test_get_current_portfolio_provisions_one_if_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'John Doe',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'user_id',
                'title',
                'slug',
                'draft_content',
                'published_content',
                'settings',
                'is_published'
            ])
            ->assertJson([
                'title' => 'Portafolio de John Doe',
                'is_published' => false,
            ]);

        $this->assertDatabaseHas('portfolios', [
            'user_id' => $user->id,
            'title' => 'Portafolio de John Doe',
        ]);
    }

    /**
     * Test saving draft updates only draft_content and not published_content.
     */
    public function test_saving_draft_updates_only_draft_content(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'title' => 'My Test Portfolio',
            'slug' => 'my-test-portfolio',
            'is_published' => false,
        ]);

        Sanctum::actingAs($user);

        $draftData = ['ROOT' => ['type' => 'Container', 'props' => []]];

        $response = $this->putJson('/api/portfolio/save', [
            'draft_content' => $draftData,
            'title' => 'Updated Draft Title',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('portfolio.title', 'Updated Draft Title')
            ->assertJsonPath('portfolio.draft_content', $draftData);

        $portfolio->refresh();
        $this->assertEquals($draftData, $portfolio->draft_content);
        $this->assertNull($portfolio->published_content);
        $this->assertFalse($portfolio->is_published);
    }

    /**
     * Test publishing copies draft_content to published_content and marks it published.
     */
    public function test_publishing_copies_draft_to_published_content(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $draftData = ['ROOT' => ['type' => 'Container', 'props' => ['padding' => 20]]];
        
        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'title' => 'My Test Portfolio',
            'slug' => 'my-test-portfolio',
            'draft_content' => $draftData,
            'published_content' => null,
            'is_published' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/portfolio/publish');

        $response->assertStatus(200)
            ->assertJsonPath('portfolio.is_published', true)
            ->assertJsonPath('portfolio.published_content', $draftData);

        $portfolio->refresh();
        $this->assertTrue($portfolio->is_published);
        $this->assertEquals($draftData, $portfolio->published_content);
        // Ensure draft remains intact
        $this->assertEquals($draftData, $portfolio->draft_content);
    }

    /**
     * Test unpublishing takes the public link offline.
     */
    public function test_unpublishing_sets_is_published_false(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'title' => 'My Test Portfolio',
            'slug' => 'my-test-portfolio',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/portfolio/unpublish');

        $response->assertStatus(200)
            ->assertJsonPath('portfolio.is_published', false);

        $portfolio->refresh();
        $this->assertFalse($portfolio->is_published);
    }

    /**
     * Test fetching public portfolio by slug.
     */
    public function test_public_view_of_published_portfolio(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Student',
            'email' => 'jane@unimar.edu.ve',
        ]);
        $publishedData = ['ROOT' => ['type' => 'Container', 'props' => []]];

        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'title' => 'Jane Public Page',
            'slug' => 'jane-slug',
            'draft_content' => null,
            'published_content' => $publishedData,
            'is_published' => true,
        ]);

        // Accessing publicly (no actingAs user)
        $response = $this->getJson('/api/public/portfolios/jane-slug');

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Jane Public Page',
                'slug' => 'jane-slug',
                'published_content' => $publishedData,
                'author' => [
                    'name' => 'Jane Student',
                    'email' => 'jane@unimar.edu.ve',
                ]
            ]);
    }

    /**
     * Test public view fails if portfolio is not published or slug doesn't exist.
     */
    public function test_public_view_fails_if_not_published_or_missing(): void
    {
        $user = User::factory()->create();
        
        Portfolio::create([
            'user_id' => $user->id,
            'title' => 'Offline Page',
            'slug' => 'offline-slug',
            'published_content' => null,
            'is_published' => false,
        ]);

        // 1. Fetching offline slug -> 403 Forbidden
        $response1 = $this->getJson('/api/public/portfolios/offline-slug');
        $response1->assertStatus(403);

        // 2. Fetching non-existent slug -> 404 Not Found
        $response2 = $this->getJson('/api/public/portfolios/does-not-exist');
        $response2->assertStatus(404);
    }
}
