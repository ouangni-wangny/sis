<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\LoginWebAction;
use App\Application\Identity\RevokeDeviceTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginWebAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'web')->toString(),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']->load(['roles', 'permissions'])),
            ],
        ]);
    }

    public function logout(Request $request, RevokeDeviceTokenAction $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['roles', 'permissions', 'agent.grade', 'agent.perimetres.zone', 'agent.perimetres.site']));
    }
}
