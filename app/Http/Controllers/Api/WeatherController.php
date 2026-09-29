<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WeatherUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __invoke(WeatherService $weather): JsonResponse
    {
        try {
            return response()->json(['data' => $weather->current()]);
        } catch (WeatherUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
