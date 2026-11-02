<?php

namespace SzentirasHu\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Reference\ReferenceDataService;

/**
 * Public, key-less access to the reference parser's data. The index names the current version
 * and is cached briefly, so clients learn about a deploy within minutes; the versioned document
 * never changes, so it is cached forever and no CDN purge is ever needed.
 */
class ReferenceDataController extends Controller
{
    private const INDEX_MAX_AGE = 300;

    private const VERSIONED_MAX_AGE = 31536000;

    public function __construct(protected ReferenceDataService $referenceDataService) {}

    public function index(): JsonResponse
    {
        $version = $this->referenceDataService->version();

        return response()->json([
            'version' => $version,
            'url' => route('api.reference-data.show', ['version' => $version]),
        ])->header('Cache-Control', 'public, max-age=' . self::INDEX_MAX_AGE);
    }

    public function show(string $version): Response|JsonResponse
    {
        if (! hash_equals($this->referenceDataService->version(), $version)) {
            return response()->json(['error' => 'Unknown reference data version.'], 404)
                ->header('Cache-Control', 'no-store');
        }

        return response($this->referenceDataService->json(), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=' . self::VERSIONED_MAX_AGE . ', immutable',
        ]);
    }
}
