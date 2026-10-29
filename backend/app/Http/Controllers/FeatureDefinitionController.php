<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FeatureDefinitionResource;
use App\Models\FeatureDefinition;
use Illuminate\Http\JsonResponse;

class FeatureDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => FeatureDefinitionResource::collection(
                FeatureDefinition::query()->orderBy('key')->get(),
            ),
        ]);
    }
}
