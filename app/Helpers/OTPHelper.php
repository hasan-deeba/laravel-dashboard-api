<?php

namespace App\Helpers;

use App\Enum\OTPType;
use App\Models\AccessManagement\UsersOtpCode;
use App\Services\ResultService;
use Carbon\Carbon;

class OTPHelper
{
    public static function generateCode(): int
    {
        return 1234;
        return $otpCode = rand(1000, 9999);
    }

    public static function otpSecondsBeforeExpire(): int
    {
        return 3600;
    }

    public static function createOTPCode($email, $guard, $type)
    {
        $code = self::generateCode();
        UsersOtpCode::create([
            'email' => $email,
            'guard' => $guard,
            'code' => $code,
            'type' =>$type,
            'expires_at' => Carbon::now()->addSeconds(self::otpSecondsBeforeExpire()),
        ]);
        return $code;
    }

    public static function validateOTPCode($code, $guard, $type): ResultService
    {
        $code = UsersOtpCode::where([
            'code' => $code,
            'guard' => $guard,
            'type' => $type,
        ])->first();

        if (!$code)
            return new ResultService(valid: false, code: 404, message: __('messages.Incorrect code'));

        if ($code->expires_at < Carbon::now()->toDateTimeString())
            return new ResultService(valid: false, code: 404, message: __('messages.Expired code'));

        return new ResultService(item: $code);
    }


}
