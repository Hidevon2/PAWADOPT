<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Database\Seeders\PetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): array
    {
        $user = User::factory()->create(['password' => 'test-staff-password']);
        $user->forceFill(['role' => $role])->save();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-staff-password'])->assertOk()->json('token');
        return [$user, $token];
    }

    private function apply(string $token, Pet $pet): int
    {
        return $this->withToken($token)->postJson('/api/pets/'.$pet->id.'/applications', [
            'phone' => '09123456789', 'city' => 'Quezon City', 'housing' => 'House',
            'experience' => 'Had pets before', 'message' => 'A calm home with plenty of space and time for daily pet care.',
        ])->assertCreated()->json('data.id');
    }

    public function test_registration_cannot_assign_a_staff_role_and_private_endpoints_require_staff(): void
    {
        $token = $this->postJson('/api/register', ['name' => 'Adopter', 'email' => 'public@example.test', 'password' => 'test-adopter-password', 'password_confirmation' => 'test-adopter-password', 'role' => 'admin'])->assertCreated()->assertJsonPath('user.role', 'adopter')->json('token');
        $this->getJson('/api/staff/overview')->assertUnauthorized();
        foreach (['overview', 'applications', 'messages', 'users'] as $path) {
            $this->withToken($token)->getJson('/api/staff/'.$path)->assertForbidden();
        }
    }

    public function test_managers_manage_pets_and_messages_but_cannot_manage_roles(): void
    {
        [$manager, $token] = $this->actor('manager');
        $this->withToken($token)->getJson('/api/staff/overview')->assertOk();
        $fields = ['name' => 'Maple', 'species' => 'Cats', 'breed' => 'Domestic Mix', 'age_months' => 6, 'gender' => 'Female', 'size' => 'Small', 'location' => 'Pasig', 'description' => 'A gentle young cat looking for a safe and caring home.', 'traits' => ['Gentle'], 'image' => '/images/willow.jpg', 'adoption_fee' => 300, 'status' => 'available'];
        $id = $this->withToken($token)->postJson('/api/staff/pets', $fields)->assertCreated()->json('data.id');
        $this->withToken($token)->putJson('/api/staff/pets/'.$id, [...$fields, 'status' => 'on_hold'])->assertOk();
        $this->getJson('/api/pets/'.$id)->assertJsonPath('data.status', 'on_hold');
        $this->withToken($token)->postJson('/api/staff/pets', [...$fields, 'image' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->withToken($token)->getJson('/api/staff/users')->assertForbidden();
        $this->withToken($token)->patchJson('/api/staff/users/'.$manager->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->postJson('/api/contact', ['name' => 'Visitor', 'email' => 'visitor@example.test', 'subject' => 'Question', 'message' => 'How can I prepare for adopting a cat?'])->assertCreated();
        $this->withToken($token)->getJson('/api/staff/messages')->assertJsonCount(1, 'data');
        $this->withToken($token)->patchJson('/api/staff/messages/1', ['resolved' => true])->assertNoContent();
        $this->assertDatabaseMissing('contact_messages', ['id' => 1, 'resolved_at' => null]);
    }

    public function test_approving_a_match_closes_competing_applications_and_updates_adopter_history(): void
    {
        $this->seed(PetSeeder::class);
        [, $adopter] = $this->actor('adopter');
        [, $other] = $this->actor('adopter');
        [, $staff] = $this->actor('manager');
        $pet = Pet::first();
        $id = $this->apply($adopter, $pet);
        $otherId = $this->apply($other, $pet);
        $this->withToken($staff)->patchJson('/api/staff/applications/'.$id, ['status' => 'reviewing'])->assertOk();
        $this->withToken($staff)->patchJson('/api/staff/applications/'.$id, ['status' => 'approved', 'review_note' => 'A good match for this fictional workflow.'])->assertOk();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'status' => 'adopted']);
        $this->assertDatabaseHas('adoption_applications', ['id' => $otherId, 'status' => 'rejected']);
        $this->withToken($adopter)->getJson('/api/applications')->assertJsonPath('data.0.status', 'approved');
        $this->withToken($staff)->patchJson('/api/staff/applications/'.$otherId, ['status' => 'approved'])->assertConflict();
        $this->withToken($adopter)->patchJson('/api/staff/applications/'.$id, ['status' => 'rejected'])->assertForbidden();
        $this->withToken($staff)->putJson('/api/staff/pets/'.$pet->id, [...$pet->fresh()->only(['name', 'species', 'breed', 'age_months', 'gender', 'size', 'location', 'description', 'traits', 'image', 'adoption_fee']), 'status' => 'available'])->assertConflict();
    }

    public function test_only_admins_can_change_other_roles_and_changes_revoke_old_tokens(): void
    {
        [$admin, $token] = $this->actor('admin');
        [$adopter, $oldToken] = $this->actor('adopter');
        $this->withToken($token)->getJson('/api/staff/users')->assertOk()->assertJsonMissingPath('data.0.password');
        $this->withToken($token)->patchJson('/api/staff/users/'.$admin->id.'/role', ['role' => 'adopter'])->assertConflict();
        $this->withToken($token)->patchJson('/api/staff/users/'.$adopter->id.'/role', ['role' => 'manager'])->assertOk();
        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $adopter->email, 'password' => 'test-staff-password'])->assertOk()->assertJsonPath('user.role', 'manager');
    }

    public function test_password_changes_require_the_current_password_and_revoke_other_sessions(): void
    {
        [$user, $token] = $this->actor('admin');
        $oldToken = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-staff-password'])->assertOk()->json('token');
        $this->withToken($token)->putJson('/api/account/password', ['current_password' => 'wrong', 'password' => 'new-staff-password', 'password_confirmation' => 'new-staff-password'])->assertUnprocessable();
        $this->withToken($token)->putJson('/api/account/password', ['current_password' => 'test-staff-password', 'password' => 'new-staff-password', 'password_confirmation' => 'new-staff-password'])->assertNoContent();
        $this->assertTrue(Hash::check('new-staff-password', $user->fresh()->password));
        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/me')->assertOk();
    }
}
