<?php

namespace Tests\Feature\Clients;

use App\Helpers\NotificationHelper;
use App\Models\Clients\Client;
use App\Notifications\ClientTestPushNotification;
use App\Services\Api\Clients\FirebaseCloudMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_login_can_register_the_current_device_token(): void
    {
        $this->postJson('/api/register', [
            'first_name' => 'New',
            'last_name' => 'Client',
            'gender' => 'MALE',
            'marital_status' => 'SINGLE',
            'email' => 'new-client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $registeredClient = Client::where('email', 'new-client@example.com')->firstOrFail();
        $this->assertDatabaseMissing('client_devices', ['client_id' => $registeredClient->id]);
        $this->postJson('/api/verify-otp', [
            'email' => $registeredClient->email,
            'code' => '123456',
            'device_token' => 'verify-fcm-token',
            'device_platform' => 'ios',
        ])->assertOk();
        $this->assertDatabaseHas('client_devices', [
            'client_id' => $registeredClient->id,
            'token' => 'verify-fcm-token',
            'platform' => 'ios',
        ]);

        $verifiedClient = Client::factory()->create([
            'email' => 'verified-client@example.com',
            'email_verified_at' => now(),
            'password' => 'password123',
        ]);
        $this->postJson('/api/login', [
            'email' => $verifiedClient->email,
            'password' => 'password123',
            'device_token' => 'login-fcm-token',
            'device_platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('client_devices', [
            'client_id' => $verifiedClient->id,
            'token' => 'login-fcm-token',
            'platform' => 'android',
        ]);
    }

    public function test_logout_unregisters_the_submitted_device_token(): void
    {
        $client = Client::factory()->create();
        $client->devices()->create(['token' => 'logout-fcm-token', 'platform' => 'ios']);
        $plainTextToken = $client->createToken('test')->plainTextToken;

        $this->withToken($plainTextToken)->postJson('/api/logout', [
            'device_token' => 'logout-fcm-token',
        ])->assertOk();

        $this->assertDatabaseMissing('client_devices', ['token' => 'logout-fcm-token']);
    }

    public function test_login_reassigns_an_existing_device_token_to_the_current_client(): void
    {
        $firstClient = Client::factory()->create([
            'email' => 'first-device-user@example.com',
            'email_verified_at' => now(),
            'password' => 'password123',
        ]);
        $secondClient = Client::factory()->create([
            'email' => 'second-device-user@example.com',
            'email_verified_at' => now(),
            'password' => 'password123',
        ]);

        foreach ([$firstClient, $secondClient] as $client) {
            $this->postJson('/api/login', [
                'email' => $client->email,
                'password' => 'password123',
                'device_token' => 'same-device-token',
                'device_platform' => 'ios',
            ])->assertOk();
        }

        $this->assertDatabaseHas('client_devices', [
            'client_id' => $secondClient->id,
            'token' => 'same-device-token',
            'platform' => 'ios',
        ]);
        $this->assertDatabaseCount('client_devices', 1);
    }

    public function test_helper_can_send_one_notification_to_multiple_app_clients(): void
    {
        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();
        $firstClient->devices()->create(['token' => 'first-fcm-token', 'platform' => 'ios']);
        $secondClient->devices()->create(['token' => 'second-fcm-token', 'platform' => 'android']);

        $firebase = \Mockery::mock(FirebaseCloudMessaging::class);
        $firebase->shouldReceive('send')->once()->with('first-fcm-token', 'Bulk test', 'Hello users', ['kind' => 'test_push']);
        $firebase->shouldReceive('send')->once()->with('second-fcm-token', 'Bulk test', 'Hello users', ['kind' => 'test_push']);
        $this->app->instance(FirebaseCloudMessaging::class, $firebase);

        $sent = NotificationHelper::sendToMultiUser(
            [$firstClient, $secondClient],
            new ClientTestPushNotification('Bulk test', 'Hello users'),
        );

        $this->assertSame(2, $sent);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_test_push_requires_a_registered_device(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($client, 'clients')->postJson('/api/notifications/test', [
            'title' => 'Test',
            'body' => 'Hello from FCM',
        ])->assertStatus(409)->assertExactJson([
            'message' => 'Register this device for notifications first.',
        ]);
    }

    public function test_test_push_sends_to_client_device_and_persists_a_database_notification(): void
    {
        $client = Client::factory()->create();
        $client->devices()->create(['token' => 'registered-fcm-token', 'platform' => 'android']);

        $firebase = \Mockery::mock(FirebaseCloudMessaging::class);
        $firebase->shouldReceive('send')->once()->with('registered-fcm-token', 'Push test', 'It works', ['kind' => 'test_push']);
        $this->app->instance(FirebaseCloudMessaging::class, $firebase);

        $this->actingAs($client, 'clients')->postJson('/api/notifications/test', [
            'title' => 'Push test',
            'body' => 'It works',
        ])->assertOk()->assertJsonPath('data.devices_targeted', 1);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Client::class,
            'notifiable_id' => $client->id,
        ]);
    }

    public function test_notification_endpoints_require_client_authentication(): void
    {
        $this->postJson('/api/notifications/test', [
            'title' => 'Test', 'body' => 'Hello',
        ])->assertUnauthorized();
    }
}
