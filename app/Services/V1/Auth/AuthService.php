<?php

namespace App\Services\V1\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->sendOtp($user->email, 'verification');

        return [
            'user' => $user,
            'message' => 'User registered successfully. Please verify your email with the OTP sent.'
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        if (!$user->email_verified_at) {
            throw new \Exception('Please verify your email first');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    public function sendOtp(string $email, string $type): void
    {
        // Delete existing unused OTPs
        Otp::where('email', $email)
            ->where('type', $type)
            ->where('used', false)
            ->delete();

        $otpCode = Otp::generateOtp();
        
        Otp::create([
            'email' => $email,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new OtpMail($otpCode, $type));
    }

    public function verifyOtp(string $email, string $otp, string $type): bool
    {
        $otpRecord = Otp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('used', false)
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return false;
        }

        $otpRecord->markAsUsed();

        if ($type === 'verification') {
            User::where('email', $email)->update([
                'email_verified_at' => Carbon::now()
            ]);
        }

        return true;
    }

    public function resetPassword(string $email, string $password, string $otp): bool
    {
        if (!$this->verifyOtp($email, $otp, 'password_reset')) {
            return false;
        }

        User::where('email', $email)->update([
            'password' => Hash::make($password)
        ]);

        return true;
    }

    public function refreshToken(User $user): array
    {
        // Revoke current tokens
        $user->tokens()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }
}
