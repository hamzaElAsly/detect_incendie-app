<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\IssueApiToken;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\FactoryResource;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $register, IssueApiToken $tokens): JsonResponse
    {
        [$user, $factory] = $register->execute($request->validated());
        $user->load('factories');

        return response()->json(['data' => new UserResource($user), 'factory' => new FactoryResource($factory), 'factory_id' => $factory->id, 'token' => $tokens->execute($user, 'registration'), 'token_type' => 'Bearer'], 201);
    }

    public function login(LoginRequest $request, IssueApiToken $tokens): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }
        if (! $user->is_active) {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => new UserResource($user->load('factories')), 'token' => $tokens->execute($user, $request->input('device_name', 'api')), 'token_type' => 'Bearer']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('factories'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function refresh(Request $request, IssueApiToken $tokens): JsonResponse
    {
        $user = $request->user();
        $request->attributes->get('api_token')->delete();

        return response()->json(['token' => $tokens->execute($user, 'refresh'), 'token_type' => 'Bearer']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If the account exists, a password reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->apiTokens()->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }
}
