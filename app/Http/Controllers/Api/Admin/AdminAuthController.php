<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $user = AdminUser::where('email', $request->validated('email'))->first();

        // Ista poruka za nepostojećeg usera i krivu lozinku.
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Pogrešni podaci za prijavu.'], 401);
        }

        return response()->json([
            'token' => $user->createToken('admin')->plainTextToken,
        ]);
    }
}
