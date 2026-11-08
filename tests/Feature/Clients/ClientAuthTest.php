<?php

namespace Tests\Feature\Clients;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Enum\OTPType;
use App\Models\AccessManagement\User;
use App\Models\Clients\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Hasan',
            'last_name' => 'Deeba',
            'company_name' => 'HeroDash',
            'age' => 30,
            'gender' => GenderType::MALE->value,
            'marital_status' => MaritalStatusType::SINGLE->value,
            'email' => 'hasan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    protected function registerClient(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/register', $this->validPayload($overrides));
    }

    protected function verifyClient(string $email = 'hasan@example.com'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/verify-otp', [
            'email' => $email,
            'code' => '123456',
        ]);
    }

    /* ---------------------------------------------------------------------- */
    /*                          Registration builder                          */
    /* ---------------------------------------------------------------------- */

    public function test_registration_builder_returns_translated_enum_options(): void
    {
        $response = $this->getJson('/api/register/builder');

        $response->assertOk()
            ->assertJsonPath('data.genders.MALE', 'Male')
            ->assertJsonPath('data.genders.FEMALE', 'Female')
            ->assertJsonPath('data.marital_statuses.SINGLE', 'Single')
            ->assertJsonPath('data.marital_statuses.MARRIED', 'Married')
            ->assertJsonPath('data.marital_statuses.DIVORCED', 'Divorced')
            ->assertJsonPath('data.marital_statuses.WIDOWED', 'Widowed');
    }

    public function test_registration_builder_returns_arabic_options(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/register/builder');

        $response->assertOk()
            ->assertJsonPath('data.genders.MALE', 'ذكر')
            ->assertJsonPath('data.genders.FEMALE', 'أنثى')
            ->assertJsonPath('data.marital_statuses.MARRIED', 'متزوج');
    }

    /* ---------------------------------------------------------------------- */
    /*                              Registration                              */
    /* ---------------------------------------------------------------------- */

    public function test_client_can_register(): void
    {
        $response = $this->registerClient();

        $response->assertStatus(201)
            ->assertJsonPath('data.client.email', 'hasan@example.com')
            ->assertJsonPath('data.client.full_name', 'Hasan Deeba')
            ->assertJsonPath('data.client.gender.value', GenderType::MALE->value)
            ->assertJsonPath('data.client.gender.name', 'Male')
            ->assertJsonPath('data.client.gender.color', 'primary')
            ->assertJsonPath('data.client.marital_status.value', MaritalStatusType::SINGLE->value)
            ->assertJsonPath('data.client.marital_status.name', 'Single')
            ->assertJsonPath('data.client.is_verified', false);

        $this->assertDatabaseHas('clients', [
            'email' => 'hasan@example.com',
            'first_name' => 'Hasan',
            'last_name' => 'Deeba',
            'company_name' => 'HeroDash',
            'gender' => GenderType::MALE->value,
            'marital_status' => MaritalStatusType::SINGLE->value,
        ]);

        $client = Client::where('email', 'hasan@example.com')->first();
        $this->assertTrue(Hash::check('password123', $client->password));
        $this->assertNull($client->email_verified_at);

        // The fixed testing OTP is stored for the clients guard.
        $this->assertDatabaseHas('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
            'code' => '123456',
            'type' => OTPType::REGISTER->value,
        ]);
    }

    public function test_registration_rejects_invalid_enum_values(): void
    {
        $response = $this->registerClient([
            'gender' => 'OTHER',
            'marital_status' => 'UNKNOWN',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender', 'marital_status']);
    }

    public function test_registration_requires_unique_email(): void
    {
        Client::factory()->create(['email' => 'hasan@example.com']);

        $response = $this->registerClient();

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_mandatory_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'gender', 'marital_status', 'email', 'password']);
    }

    /* ---------------------------------------------------------------------- */
    /*                             OTP verification                           */
    /* ---------------------------------------------------------------------- */

    public function test_client_can_verify_otp(): void
    {
        $this->registerClient();

        $response = $this->verifyClient();

        $response->assertOk()
            ->assertJsonStructure(['message', 'data' => ['access_token', 'client']]);

        $client = Client::where('email', 'hasan@example.com')->first();
        $this->assertNotNull($client->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // OTP is consumed after successful verification.
        $this->assertDatabaseMissing('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
        ]);
    }

    public function test_verify_otp_rejects_incorrect_code(): void
    {
        $this->registerClient();

        $response = $this->postJson('/api/verify-otp', [
            'email' => 'hasan@example.com',
            'code' => '000000',
        ]);

        $response->assertStatus(404);

        $this->assertNull(Client::where('email', 'hasan@example.com')->first()->email_verified_at);
    }

    public function test_verify_otp_rejects_already_verified_account(): void
    {
        $this->registerClient();
        $this->verifyClient();

        $response = $this->verifyClient();

        $response->assertStatus(400);
    }

    public function test_client_can_resend_otp(): void
    {
        $this->registerClient();

        $response = $this->postJson('/api/resend-otp', ['email' => 'hasan@example.com']);

        $response->assertOk();
        $this->assertDatabaseHas('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
            'code' => '123456',
        ]);
        $this->assertDatabaseCount('users_otp_codes', 1);
    }

    public function test_resend_otp_rejects_already_verified_account(): void
    {
        $this->registerClient();
        $this->verifyClient();

        $response = $this->postJson('/api/resend-otp', ['email' => 'hasan@example.com']);

        $response->assertStatus(400);
    }

    /* ---------------------------------------------------------------------- */
    /*                                  Login                                 */
    /* ---------------------------------------------------------------------- */

    public function test_unverified_client_cannot_login(): void
    {
        $this->registerClient();

        $response = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);

        // A fresh registration code is issued to help the client verify.
        $this->assertDatabaseHas('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
            'type' => OTPType::REGISTER->value,
        ]);
    }

    public function test_inactive_client_cannot_login(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_client_can_login(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'data' => ['access_token', 'client']])
            ->assertJsonPath('data.client.email', 'hasan@example.com');

        $token = $response->json('data.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'hasan@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'hasan@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /* ---------------------------------------------------------------------- */
    /*                             Forget password                            */
    /* ---------------------------------------------------------------------- */

    public function test_client_can_request_password_reset(): void
    {
        Client::factory()->create(['email' => 'hasan@example.com']);

        $response = $this->postJson('/api/forget-password', ['email' => 'hasan@example.com']);

        $response->assertOk();
        $this->assertDatabaseHas('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
            'code' => '123456',
            'type' => OTPType::RESET_PASSWORD->value,
        ]);
    }

    public function test_client_can_reset_password(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/forget-password', ['email' => 'hasan@example.com'])->assertOk();

        $response = $this->postJson('/api/reset-password', [
            'email' => 'hasan@example.com',
            'code' => '123456',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();

        // OTP consumed.
        $this->assertDatabaseMissing('users_otp_codes', [
            'email' => 'hasan@example.com',
            'guard' => 'clients',
        ]);

        // New password works, old one does not.
        $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ])->assertStatus(401);
    }

    public function test_reset_password_rejects_incorrect_code(): void
    {
        Client::factory()->create(['email' => 'hasan@example.com']);
        $this->postJson('/api/forget-password', ['email' => 'hasan@example.com'])->assertOk();

        $response = $this->postJson('/api/reset-password', [
            'email' => 'hasan@example.com',
            'code' => '000000',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(404);
    }

    /* ---------------------------------------------------------------------- */
    /*                            Profile / logout                            */
    /* ---------------------------------------------------------------------- */

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_client_can_logout(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('data.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Guards cache the resolved user within a single test process;
        // forget them to simulate a brand new request.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    /* ---------------------------------------------------------------------- */
    /*                            Guard separation                            */
    /* ---------------------------------------------------------------------- */

    public function test_client_token_cannot_access_cms_routes(): void
    {
        Client::factory()->create([
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'hasan@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('data.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/cms/users')
            ->assertStatus(401);
    }

    public function test_cms_token_cannot_access_client_routes(): void
    {
        $user = User::create([
            'name' => 'CMS User',
            'email' => 'cms@example.com',
            'password' => 'password123',
        ]);
        $token = $user->createToken('cms-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
