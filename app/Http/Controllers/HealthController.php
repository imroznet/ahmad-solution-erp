<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return response('Service is available.', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
