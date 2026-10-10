<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UnitKerja;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $unitKerja = UnitKerja::create([
            'nama_unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Landak',
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@penink.test',
            'password' => Hash::make('password123'),
            'user_type' => 'ASN',
            'role' => 'user',
            'unit_kerja_id' => $unitKerja->id,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // CREATE SHORT LINK
    // =====================================================

    /** @test */
    public function authenticated_user_can_create_short_link()
    {
        $response = $this->auth()->postJson('/api/short-links', [
            'original_url' => 'https://example.com/halaman-panjang',
            'short_code' => 'promosi2026',
            'title' => 'Promosi 2026',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'short_code', 'original_url'],
            ]);

        $this->assertDatabaseHas('short_links', [
            'short_code' => 'promosi2026',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_cannot_create_short_link_with_duplicate_alias()
    {
        // Bikin shortlink pertama
        ShortLink::create([
            'user_id' => $this->user->id,
            'original_url' => 'https://example.com/first',
            'short_code' => 'promosi2026',
            'title' => 'First',
            'type' => 'UMUM',
            'status' => true,
        ]);

        // Coba bikin dengan alias yang sama
        $response = $this->auth()->postJson('/api/short-links', [
            'original_url' => 'https://example.com/second',
            'short_code' => 'promosi2026',
            'title' => 'Second',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function short_link_creation_requires_url()
    {
        $response = $this->auth()->postJson('/api/short-links', [
            'short_code' => 'test',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_short_link()
    {
        $response = $this->postJson('/api/short-links', [
            'original_url' => 'https://example.com',
            'short_code' => 'test',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(401);
    }

    // =====================================================
    // READ SHORT LINKS
    // =====================================================

    /** @test */
    public function user_can_see_own_short_links()
    {
        ShortLink::create([
            'user_id' => $this->user->id,
            'original_url' => 'https://example.com/1',
            'short_code' => 'link1',
            'title' => 'Link 1',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->getJson('/api/short-links');

        $response->assertStatus(200)
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    // =====================================================
    // UPDATE SHORT LINK
    // =====================================================

    /** @test */
    public function user_can_update_own_short_link()
    {
        $shortLink = ShortLink::create([
            'user_id' => $this->user->id,
            'original_url' => 'https://example.com/old',
            'short_code' => 'oldlink',
            'title' => 'Old',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->putJson("/api/short-links/{$shortLink->id}", [
            'original_url' => 'https://example.com/new',
            'short_code' => 'newlink',
            'title' => 'New',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('short_links', [
            'id' => $shortLink->id,
            'short_code' => 'newlink',
        ]);
    }

    // =====================================================
    // DELETE SHORT LINK
    // =====================================================

    /** @test */
    public function user_can_delete_own_short_link()
    {
        $shortLink = ShortLink::create([
            'user_id' => $this->user->id,
            'original_url' => 'https://example.com/delete-me',
            'short_code' => 'deleteme',
            'title' => 'Delete Me',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->deleteJson("/api/short-links/{$shortLink->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('short_links', [
            'id' => $shortLink->id,
        ]);
    }

    // =====================================================
    // TOGGLE STATUS
    // =====================================================

    /** @test */
    public function user_can_toggle_short_link_status()
    {
        $shortLink = ShortLink::create([
            'user_id' => $this->user->id,
            'original_url' => 'https://example.com',
            'short_code' => 'toggle',
            'title' => 'Toggle Test',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->patchJson("/api/short-links/{$shortLink->id}/status");

        $response->assertStatus(200);

        $this->assertDatabaseHas('short_links', [
            'id' => $shortLink->id,
            'status' => false,
        ]);
    }

    // =====================================================
    // MODERASI KONTEN (JUDOL/PINJOL)
    // =====================================================

    /** @test */
    public function short_link_with_blocked_content_is_rejected()
    {
        $response = $this->auth()->postJson('/api/short-links', [
            'original_url' => 'https://situs-judi-online.com',
            'short_code' => 'judol',
            'title' => 'Test Judol',
            'type' => 'UMUM',
        ]);

        // Harusnya ditolak (422 atau 400, tergantung implementasi)
        $response->assertStatus(422);
    }
}