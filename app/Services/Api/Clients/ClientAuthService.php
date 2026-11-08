<?php

namespace App\Services\Api\Clients;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Enum\OTPType;
use App\Helpers\OTPHelper;
use App\Models\Clients\Client;
use App\Services\ResultService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ClientAuthService
{
    public function __construct(protected SettingsService $settingsService)
    {
    }

    /**
     * The auth guard / OTP guard used for clients.
     */
    public const GUARD = 'clients';

    /**
     * Fixed OTP code for testing.
     *
     * TODO: replace with a randomly generated code delivered to the client
     * (SMS/mail) once real OTP delivery is implemented.
     */
    public const OTP_CODE = '123456';

    /**
     * Max failed login attempts before throttling.
     */
    protected const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Extra data needed by the registration page (translated enum options).
     */
    public function registrationBuilder(Request $request): ResultService
    {
        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Success'),
            item: [
                'genders' => GenderType::labels(),
                'marital_statuses' => MaritalStatusType::labels(),
            ]
        );
    }

    /**
     * Register a new client and send the registration OTP.
     */
    public function register(array $data): ResultService
    {
        // Recheck immediately before writing, so disabling registration also
        // blocks direct API calls (not just the dashboard registration form).
        $availability = $this->settingsService->registrationAvailability();
        if (!$availability->valid) {
            return $availability;
        }

        $client = Client::create($data);

        $this->sendOtp($client, OTPType::REGISTER);

        return new ResultService(
            valid: true,
            code: 201,
            message: __('messages.Registered successfully'),
            item: [
                'client' => $client->transformItemExclude(),
            ]
        );
    }

    /**
     * Verify the registration OTP and issue the first access token.
     */
    public function verifyOtp(array $data): ResultService
    {
        $client = Client::where('email', $data['email'])->first();

        if (!$client) {
            return new ResultService(false, 404, __('messages.Client not found'));
        }

        if ($client->email_verified_at) {
            return new ResultService(false, 400, __('messages.Account already verified'));
        }

        $result = OTPHelper::validateOTPCode($data['code'], self::GUARD, OTPType::REGISTER, $client->email);
        if (!$result->valid) {
            return $result;
        }

        $client->email_verified_at = now();
        $client->save();

        $result->item['code']->delete();

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Account verified successfully'),
            item: [
                'access_token' => $client->createToken('client-auth-token')->plainTextToken,
                'client' => $client->transformItemExclude(),
            ]
        );
    }

    /**
     * Resend the registration OTP.
     */
    public function resendOtp(array $data): ResultService
    {
        $client = Client::where('email', $data['email'])->first();

        if (!$client) {
            return new ResultService(false, 404, __('messages.Client not found'));
        }

        if ($client->email_verified_at) {
            return new ResultService(false, 400, __('messages.Account already verified'));
        }

        $this->sendOtp($client, OTPType::REGISTER);

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Code sent successfully')
        );
    }

    /**
     * Login a verified client and issue an access token.
     */
    public function login(array $data, string $ip): ResultService
    {
        $throttleKey = Str::lower($data['email']) . '|' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return new ResultService(false, 429, __('messages.Too many login attempts', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]));
        }

        $client = Client::where('email', $data['email'])->first();

        if (!$client || !Hash::check($data['password'], $client->password)) {
            RateLimiter::hit($throttleKey, 60);

            return new ResultService(false, 401, __('messages.These credentials do not match our records'));
        }

        RateLimiter::clear($throttleKey);

        if (!$client->is_active) {
            return new ResultService(false, 403, __('messages.Account is inactive'));
        }

        if (!$client->email_verified_at) {
            // Help the client finish registration: send a fresh verification code.
            $this->sendOtp($client, OTPType::REGISTER);

            return new ResultService(false, 403, __('messages.Account not verified'));
        }

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Logged in successfully'),
            item: [
                'access_token' => $client->createToken('client-auth-token')->plainTextToken,
                'client' => $client->transformItemExclude(),
            ]
        );
    }

    /**
     * Send the reset password OTP.
     */
    public function forgetPassword(array $data): ResultService
    {
        $client = Client::where('email', $data['email'])->first();

        if (!$client) {
            return new ResultService(false, 404, __('messages.Client not found'));
        }

        $this->sendOtp($client, OTPType::RESET_PASSWORD);

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Code sent successfully')
        );
    }

    /**
     * Reset the password using the reset password OTP.
     */
    public function resetPassword(array $data): ResultService
    {
        $client = Client::where('email', $data['email'])->first();

        if (!$client) {
            return new ResultService(false, 404, __('messages.Client not found'));
        }

        $result = OTPHelper::validateOTPCode($data['code'], self::GUARD, OTPType::RESET_PASSWORD, $client->email);
        if (!$result->valid) {
            return $result;
        }

        $client->password = $data['password'];
        $client->save();

        $result->item['code']->delete();

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Password reset successfully')
        );
    }

    /**
     * Create the OTP code for the client.
     */
    protected function sendOtp(Client $client, OTPType $type): void
    {
        OTPHelper::createOTPCode($client->email, self::GUARD, $type, self::OTP_CODE);

        // TODO: deliver the code to the client (SMS/mail notification).
    }
}
