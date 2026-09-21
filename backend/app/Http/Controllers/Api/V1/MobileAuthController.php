<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\LoginMobileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class MobileAuthController extends Controller
{
    public function login(MobileLoginRequest $request, LoginMobileAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('matricule')->toString(),
            $request->string('pin')->toString(),
            $request->string('device_name', 'mobile')->toString(),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }
}
