<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UnitKerja;
use App\Models\LinkHub;
use App\Models\LinkHubItem;
use App\Models\LockedAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LinkHubTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;
    protected $unitKerja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitKerja = UnitKerja::create([
            'nama_unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Landak',
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@penink.test',
            'password' => Hash::make('password123'),
            'user_type' => 'ASN',
            'role' => 'user',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // CREATE LINK HUB
    // =====================================================

    /** @test */
    public function authenticated_user_can_create_link_hub()
    {
        $response = $this->auth()->postJson('/api/link-hubs', [
            'title' => 'Kominfo Landak',
            'description' => 'Link Hub resmi Diskominfo',
            'short_code' => 'kominfo',
            'type' => 'ASN',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'title', 'short_code'],
            ]);

        $this->assertDatabaseHas('link_hubs', [
            'short_code' => 'kominfo',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function link_hub_requires_title()
    {
        $response = $this->auth()->postJson('/api/link-hubs', [
            'short_code' => 'test',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_link_hub()
    {
        $response = $this->postJson('/api/link-hubs', [
            'title' => 'Test',
            'short_code' => 'test',
            'type' => 'UMUM',
        ]);

        $response->assertStatus(401);
    }

    // =====================================================
    // AUTO-CLAIM ALIAS TERKUNCI (LOGIKA OPD)
    // =====================================================

    /** @test */
    public function user_can_claim_locked_alias_owned_by_their_opd()
    {
        // Bikin LockedAlias untuk OPD user ini
        LockedAlias::create([
            'alias' => 'kominfo',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        // Bikin LinkHub existing dengan alias itu (milik user lain / admin)
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@penink.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'user_type' => 'ASN',
            'unit_kerja_id' => $this->unitKerja->id,
        ]);

        LinkHub::create([
            'user_id' => $admin->id,
            'title' => 'Kominfo Existing',
            'short_code' => 'kominfo',
            'type' => 'ASN',
            'status' => true,
        ]);

        // User coba bikin LinkHub dengan alias kominfo → harus auto-claim
        $response = $this->auth()->postJson('/api/link-hubs', [
            'title' => 'Kominfo Baru',
            'description' => 'Claimed by OPD',
            'short_code' => 'kominfo',
            'type' => 'ASN',
        ]);

        $response->assertStatus(200);

        // Cek: LinkHub sekarang milik user ini
        $this->assertDatabaseHas('link_hubs', [
            'short_code' => 'kominfo',
            'user_id' => $this->user->id,
            'title' => 'Kominfo Baru',
        ]);
    }

    // =====================================================
    // LIST LINK HUB
    // =====================================================

    /** @test */
    public function user_can_see_own_link_hubs()
    {
        LinkHub::create([
            'user_id' => $this->user->id,
            'title' => 'My Hub',
            'short_code' => 'myhub',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->getJson('/api/link-hubs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    // =====================================================
    // ADD LINK ITEM
    // =====================================================

    /** @test */
    public function user_can_add_link_item_to_own_hub()
    {
        $hub = LinkHub::create([
            'user_id' => $this->user->id,
            'title' => 'My Hub',
            'short_code' => 'myhub',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->postJson("/api/link-hubs/{$hub->id}/items", [
            'title' => 'Website Resmi',
            'url' => 'https://landak.go.id',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('link_hub_items', [
            'link_hub_id' => $hub->id,
            'title' => 'Website Resmi',
            'url' => 'https://landak.go.id',
        ]);
    }

    /** @test */
    public function link_item_with_blocked_content_is_rejected()
    {
        $hub = LinkHub::create([
            'user_id' => $this->user->id,
            'title' => 'My Hub',
            'short_code' => 'myhub',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->postJson("/api/link-hubs/{$hub->id}/items", [
            'title' => 'Situs Judi',
            'url' => 'https://situs-judi-online.com',
        ]);

        $response->assertStatus(422);
    }

    // =====================================================
    // DELETE LINK HUB
    // =====================================================

    /** @test */
    public function user_can_delete_own_link_hub()
    {
        $hub = LinkHub::create([
            'user_id' => $this->user->id,
            'title' => 'Delete Me',
            'short_code' => 'deleteme',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->deleteJson("/api/link-hubs/{$hub->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('link_hubs', [
            'id' => $hub->id,
        ]);
    }

    // =====================================================
    // TOGGLE STATUS
    // =====================================================

    /** @test */
    public function user_can_toggle_link_hub_status()
    {
        $hub = LinkHub::create([
            'user_id' => $this->user->id,
            'title' => 'Toggle Test',
            'short_code' => 'toggle',
            'type' => 'UMUM',
            'status' => true,
        ]);

        $response = $this->auth()->patchJson("/api/link-hubs/{$hub->id}/status");

        $response->assertStatus(200);

        $this->assertDatabaseHas('link_hubs', [
            'id' => $hub->id,
            'status' => false,
        ]);
    }
}