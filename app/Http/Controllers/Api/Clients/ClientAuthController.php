<?php

namespace App\Http\Controllers\Api\Clients;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Clients\Auth\ClientForgetPasswordRequest;
use App\Http\Requests\Api\Clients\Auth\ClientLoginRequest;
use App\Http\Requests\Api\Clients\Auth\ClientLogoutRequest;
use App\Http\Requests\Api\Clients\Auth\ClientRegisterRequest;
use App\Http\Requests\Api\Clients\Auth\ClientResetPasswordRequest;
use App\Http\Requests\Api\Clients\Auth\ResendOtpRequest;
use App\Http\Requests\Api\Clients\Auth\VerifyOtpRequest;
use App\Models\Clients\Client;
use App\Services\Api\Clients\ClientAuthService;
use App\Traits\ClientApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientAuthController extends Controller
{
    use ClientApiResponse;

    public function __construct(protected ClientAuthService $clientAuthService)
    {
    }

    /**
     * Registration page builder: translated genders and marital statuses.
     */
    public function registrationBuilder(Request $request): JsonResponse
    {
        $result = $this->clientAuthService->registrationBuilder($request);

        return $this->respond($result);
    }

    /**
     * Register a new client.
     */
    public function register(ClientRegisterRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->register($request->validated());

        return $this->respond($result);
    }

    /**
     * Verify the registration OTP.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->verifyOtp($request->validated());

        return $this->respond($result);
    }

    /**
     * Resend the registration OTP.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->resendOtp($request->validated());

        return $this->respond($result);
    }

    /**
     * Login client and create token.
     */
    public function login(ClientLoginRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->login($request->validated(), $request->ip());

        return $this->respond($result);
    }

    /**
     * Send the reset password OTP.
     */
    public function forgetPassword(ClientForgetPasswordRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->forgetPassword($request->validated());

        return $this->respond($result);
    }

    /**
     * Reset the password using the OTP.
     */
    public function resetPassword(ClientResetPasswordRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->resetPassword($request->validated());

        return $this->respond($result);
    }

    /**
     * Get the authenticated client.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(__('messages.Success'), $request->user()->transformItemExclude());
    }

    /**
     * Logout client and revoke the current token.
     */
    public function logout(ClientLogoutRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user();
        if ($deviceToken = $request->validated('device_token')) {
            NotificationHelper::removeDeviceToken($deviceToken, $client);
        }
        $client->currentAccessToken()->delete();

        return $this->successResponse(__('messages.Logged out successfully'));
    }
}
