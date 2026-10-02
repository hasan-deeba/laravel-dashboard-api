<?php

namespace App\Http\Controllers\Cms\AccessManagement;

use App\Enum\OTPType;
use App\Helpers\OTPHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\AccessManagement\Auth\ForgetPasswordRequest;
use App\Http\Requests\Cms\AccessManagement\Auth\LoginRequest;
use App\Http\Requests\Cms\AccessManagement\Auth\RegisterRequest;
use App\Http\Requests\Cms\AccessManagement\Auth\ResetPasswordRequest;
use App\Models\AccessManagement\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use ApiResponse;
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'message' => 'User registered successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(LoginRequest $request)
    {
        $request->authenticate();
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->transformItemExclude(),
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return $request->user()->transformItemExclude();
    }


    /**
     * Forget user password
     */
    public function forgetPassword(ForgetPasswordRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if ($user){
            $code = OTPHelper::createOTPCode($data['email'], 'cms', OTPType::RESET_PASSWORD);

            try {
//                $user->notify(new ResetPasswordCode($code, false));
                return $this->successMessage(__('messages.Code sent successfully'));
            } catch (\Exception $e) {
                Log::debug("Error forget password : " . $e->getMessage());
                return $this->errorResponse(__('general.Something went wrong'));
            }
        }
        return $this->errorResponse(__('messages.Incorrect phone number'));

    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();
        if (!$user)
            return $this->errorResponse(__('messages.User not found'));

        $result = OTPHelper::validateOTPCode($data['code'], 'cms', OTPType::RESET_PASSWORD);
        if ($result->valid){
            $user->password = Hash::make($data['password']);
            $user->save();
            $result->item->delete();
        }
        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    /**
     * Logout user and revoke token
     */
    public function logout(Request $request)
    {
        Auth::guard('cms')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
