<?php

namespace App\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Clients\Auth\ClientForgetPasswordRequest;
use App\Http\Requests\Api\Clients\Auth\ClientLoginRequest;
use App\Http\Requests\Api\Clients\Auth\ClientRegisterRequest;
use App\Http\Requests\Api\Clients\Auth\ClientResetPasswordRequest;
use App\Http\Requests\Api\Clients\Auth\ResendOtpRequest;
use App\Http\Requests\Api\Clients\Auth\VerifyOtpRequest;
use App\Services\Api\Clients\ClientAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientAuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected ClientAuthService $clientAuthService)
    {
    }

    /**
     * Registration page builder: translated genders and marital statuses.
     */
    public function registrationBuilder(Request $request): JsonResponse
    {
        $result = $this->clientAuthService->registrationBuilder($request);

        return $this->showAll($result->item, $result->code);
    }

    /**
     * Register a new client.
     */
    public function register(ClientRegisterRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->register($request->validated());

        if ($result->valid) {
            return $this->successMessageWithItem($result->message, $result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    /**
     * Verify the registration OTP.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->verifyOtp($request->validated());

        if ($result->valid) {
            return $this->successMessageWithItem($result->message, $result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    /**
     * Resend the registration OTP.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->resendOtp($request->validated());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    /**
     * Login client and create token.
     */
    public function login(ClientLoginRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->login($request->validated(), $request->ip());

        if ($result->valid) {
            return $this->successMessageWithItem($result->message, $result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    /**
     * Send the reset password OTP.
     */
    public function forgetPassword(ClientForgetPasswordRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->forgetPassword($request->validated());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    /**
     * Reset the password using the OTP.
     */
    public function resetPassword(ClientResetPasswordRequest $request): JsonResponse
    {
        $result = $this->clientAuthService->resetPassword($request->validated());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    /**
     * Get the authenticated client.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->showOne($request->user()->transformItemExclude());
    }

    /**
     * Logout client and revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successMessage(__('messages.Logged out successfully'));
    }
}
