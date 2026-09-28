<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Service\Editor\EditorService;

class ApiKeyController extends Controller
{
    public function __construct(
        protected EditorService $editorService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $keys = ApiKey::with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('editor.apiKeys.index', [
            'keys' => $keys,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('editor.apiKeys.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_internal' => 'boolean',
            'throttle_rate' => 'nullable|integer|min:1',
        ]);

        // Generate a random key (UUID‑like)
        $rawKey = Str::uuid()->toString();
        $prefix = substr($rawKey, 0, 8);

        // Get current editor's anonymous ID
        $editorToken = session('anonymous_token');
        $anonymousId = AnonymousId::where('token', $editorToken)->first();

        $apiKey = ApiKey::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'key_hash' => Hash::make($rawKey),
            'key_prefix' => $prefix,
            'is_internal' => $validated['is_internal'] ?? false,
            'throttle_rate' => $validated['throttle_rate'] ?? null,
            'enabled' => true,
            'created_by_anonymous_id' => $anonymousId?->id,
            'usage_count' => 0,
        ]);

        // Show the raw key only once (flash to session)
        session()->flash('api_key_raw', $rawKey);
        session()->flash('api_key_id', $apiKey->id);

        return redirect()->route('editor.apiKeys.show', $apiKey)
            ->with('success', 'API key created successfully. Please copy the key below – it will not be shown again.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ApiKey $apiKey)
    {
        $rawKey = session('api_key_raw');
        $showRaw = !empty($rawKey) && session('api_key_id') == $apiKey->id;

        return view('editor.apiKeys.show', [
            'key' => $apiKey,
            'rawKey' => $showRaw ? $rawKey : null,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ApiKey $apiKey)
    {
        return view('editor.apiKeys.edit', [
            'key' => $apiKey,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_internal' => 'boolean',
            'throttle_rate' => 'nullable|integer|min:1',
            'enabled' => 'boolean',
        ]);

        $apiKey->update([
            ...$validated,
            'is_internal' => $request->boolean('is_internal'),
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('editor.apiKeys.show', $apiKey)
            ->with('success', 'API key updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ApiKey $apiKey)
    {
        $apiKey->delete();

        return redirect()->route('editor.apiKeys.index')
            ->with('success', 'API key deleted successfully.');
    }
}
