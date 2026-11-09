<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Pet;
use Database\Seeders\PetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tests\TestCase;

class PawAdoptTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'adopter@example.test'): string
    {
        return $this->postJson('/api/register', [
            'name' => 'Test Adopter', 'email' => $email,
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertCreated()->json('token');
    }

    public function test_android_webview_origin_can_send_authenticated_api_requests(): void
    {
        $this->options('/api/favorites', [], [
            'Origin' => 'http://localhost',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost');
    }

    public function test_unlisted_origins_do_not_receive_cross_origin_access(): void
    {
        $this->options('/api/favorites', [], [
            'Origin' => 'https://unlisted.example.test',
            'Access-Control-Request-Method' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_catalog_and_database_health_are_public(): void
    {
        $this->seed(PetSeeder::class);
        $this->getJson('/api/hello')->assertOk()->assertJsonPath('database', 'connected');
        $this->getJson('/api/pets')->assertOk()->assertJsonCount(8, 'data')->assertJsonPath('data.0.name', 'Sage');
        $this->getJson('/api/pets?species=Rabbits')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_registration_hashes_credentials_and_logout_revokes_the_session(): void
    {
        $token = $this->register();
        $this->assertTrue(Hash::check('a-long-password', User::first()->password));
        $this->assertNotEquals($token, ApiToken::first()->token_hash);
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'adopter@example.test')->assertJsonMissingPath('user.password');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_validation_and_bad_login_cannot_create_a_session(): void
    {
        $this->postJson('/api/register', ['name' => 'A', 'email' => 'wrong', 'password' => 'tiny'])->assertUnprocessable();
        $this->register();
        $this->postJson('/api/login', ['email' => 'adopter@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->assertDatabaseCount('api_tokens', 1);
        $this->postJson('/api/login', ['email' => 'ADOPTER@example.test', 'password' => 'a-long-password'])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_expired_tokens_and_guests_cannot_access_private_records(): void
    {
        $this->getJson('/api/favorites')->assertUnauthorized();
        $this->getJson('/api/applications')->assertUnauthorized();
        $token = $this->register();
        ApiToken::query()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_favorites_are_idempotent_and_isolated_between_accounts(): void
    {
        $this->seed(PetSeeder::class);
        $firstToken = $this->register();
        $pet = Pet::first();
        $this->withToken($firstToken)->putJson('/api/favorites/'.$pet->id)->assertOk();
        $this->withToken($firstToken)->putJson('/api/favorites/'.$pet->id)->assertOk();
        $this->assertDatabaseCount('favorites', 1);
        $otherToken = $this->register('another@example.test');
        $this->withToken($otherToken)->getJson('/api/favorites')->assertJsonCount(0, 'data');
        $this->withToken($otherToken)->deleteJson('/api/favorites/'.$pet->id)->assertNoContent();
        $this->withToken($firstToken)->getJson('/api/favorites')->assertJsonPath('data.0', $pet->id);
        $this->withToken($firstToken)->deleteJson('/api/favorites/'.$pet->id)->assertNoContent();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_application_persists_valid_data_rejects_duplicates_and_stays_private(): void
    {
        $this->seed(PetSeeder::class);
        $token = $this->register();
        $pet = Pet::first();
        $fields = ['phone' => '09123456789', 'city' => 'Quezon City', 'housing' => 'House', 'experience' => 'Had pets before', 'message' => 'We have a quiet and spacious home with plenty of time for daily care.'];
        $this->withToken($token)->postJson('/api/pets/'.$pet->id.'/applications', $fields)->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.pet.id', $pet->id);
        $this->withToken($token)->postJson('/api/pets/'.$pet->id.'/applications', $fields)->assertConflict();
        $this->assertDatabaseCount('adoption_applications', 1);
        $other = $this->register('private@example.test');
        $this->withToken($other)->getJson('/api/applications')->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/applications')->assertJsonCount(1, 'data');
    }

    public function test_invalid_or_unavailable_pet_applications_do_not_persist(): void
    {
        $this->seed(PetSeeder::class);
        $token = $this->register();
        $pet = Pet::first();
        $this->withToken($token)->postJson('/api/pets/'.$pet->id.'/applications', [])->assertUnprocessable();
        $pet->update(['status' => 'adopted']);
        $this->withToken($token)->postJson('/api/pets/'.$pet->id.'/applications', [
            'phone' => '+63 912 345 6789', 'city' => 'Pasig', 'housing' => 'Apartment',
            'experience' => 'First-time adopter', 'message' => 'I can provide a loving home and daily care for this pet.',
        ])->assertConflict();
        $this->assertDatabaseCount('adoption_applications', 0);
    }

    public function test_contact_message_is_validated_and_saved_with_a_reference(): void
    {
        $this->postJson('/api/contact', ['email' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('contact_messages', 0);
        $this->postJson('/api/contact', [
            'name' => 'Test Visitor', 'email' => 'visitor@example.test', 'subject' => 'Pet care question',
            'message' => 'What should a first-time adopter prepare?',
        ])->assertCreated()->assertJsonPath('reference', 'MSG-00001');
        $this->assertDatabaseHas('contact_messages', ['subject' => 'Pet care question']);
    }
}
