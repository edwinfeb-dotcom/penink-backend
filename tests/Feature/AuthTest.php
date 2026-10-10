<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser($overrides = [])
    {
        $unitKerja = UnitKerja::create([
            'nama_unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Landak',
        ]);

        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@penink.test',
            'password' => Hash::make('password123'),
            'user_type' => 'ASN',
            'role' => 'user',
            'unit_kerja_id' => $unitKerja->id,
        ], $overrides));
    }

    // =====================================================
    // LOGIN TEST
    // =====================================================

    /** @test */
    public function user_can_login_with_email()
    {
        $this->createUser();

        $response = $this->postJson('/api/login', [
            'login' => 'test@penink.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    /** @test */
    public function user_cannot_login_with_wrong_password()
    {
        $this->createUser();

        $response = $this->postJson('/api/login', [
            'login' => 'test@penink.test',
            'password' => 'password-salah',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Email/Username atau password salah.',
            ]);
    }

    /** @test */
    public function user_cannot_login_with_nonexistent_email()
    {
        $response = $this->postJson('/api/login', [
            'login' => 'tidak-ada@penink.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);
    }

    // =====================================================
    // REGISTER TEST
    // =====================================================

    /** @test */
    public function user_can_register_as_umum()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'User Baru',
            'email' => 'baru@penink.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'UMUM',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Registrasi berhasil. Silakan login.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'baru@penink.test',
            'user_type' => 'UMUM',
        ]);
    }

    /** @test */
    public function register_requires_password_confirmation()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'User Baru',
            'email' => 'baru@penink.test',
            'password' => 'password123',
            'password_confirmation' => 'password-berbeda',
            'user_type' => 'UMUM',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function register_requires_unique_email()
    {
        $this->createUser();

        $response = $this->postJson('/api/register', [
            'name' => 'User Baru',
            'email' => 'test@penink.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'UMUM',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function asn_register_requires_unit_kerja()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'User ASN',
            'email' => 'asn@penink.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'ASN',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_kerja_id']);
    }

    // =====================================================
    // LOGOUT TEST
    // =====================================================

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = $this->createUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout berhasil.',
            ]);
    }
}