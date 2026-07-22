<?php

namespace SzentirasHu\Http\Controllers\Profile;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Http\Requests\StoreSelfServiceApiKeyRequest;

class ApiKeyController extends Controller
{
    /**
     * Maximum number of self-service API keys a single user may hold.
     */
    protected const MAX_KEYS_PER_USER = 5;

    /**
     * Display the current user's self-service API keys.
     */
    public function index(): View
    {
        $anonymousId = $this->currentAnonymousId();

        $keys = ApiKey::where('created_by_anonymous_id', $anonymousId->id)
            ->where('is_self_service', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('profile.apiKeys.index', [
            'keys' => $keys,
            'maxKeys' => self::MAX_KEYS_PER_USER,
        ]);
    }

    /**
     * Show the form for creating a new self-service API key.
     */
    public function create(): View
    {
        return view('profile.apiKeys.create');
    }

    /**
     * Store a newly created self-service API key.
     */
    public function store(StoreSelfServiceApiKeyRequest $request): RedirectResponse
    {
        $anonymousId = $this->currentAnonymousId();

        $keyCount = ApiKey::where('created_by_anonymous_id', $anonymousId->id)
            ->where('is_self_service', true)
            ->count();

        if ($keyCount >= self::MAX_KEYS_PER_USER) {
            return redirect()->route('profile.apiKeys.index')
                ->withErrors(['name' => 'Elérted a maximális API kulcsok számát (' . self::MAX_KEYS_PER_USER . ').']);
        }

        $validated = $request->validated();

        $rawKey = Str::uuid()->toString();
        $prefix = substr($rawKey, 0, 8);

        $apiKey = ApiKey::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'key_hash' => Hash::make($rawKey),
            'key_plain' => $rawKey,
            'key_prefix' => $prefix,
            'is_internal' => false,
            'is_self_service' => true,
            'throttle_rate' => null,
            'enabled' => true,
            'created_by_anonymous_id' => $anonymousId->id,
            'usage_count' => 0,
        ]);

        return redirect()->route('profile.apiKeys.show', $apiKey)
            ->with('success', 'Az API kulcs sikeresen létrejött.');
    }

    /**
     * Display the specified self-service API key.
     */
    public function show(ApiKey $apiKey): View
    {
        $this->authorizeOwnership($apiKey);

        return view('profile.apiKeys.show', [
            'key' => $apiKey,
        ]);
    }

    /**
     * Remove the specified self-service API key.
     */
    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeOwnership($apiKey);

        $apiKey->delete();

        return redirect()->route('profile.apiKeys.index')
            ->with('success', 'Az API kulcs sikeresen törölve.');
    }

    /**
     * Resolve the currently logged-in anonymous user.
     */
    protected function currentAnonymousId(): AnonymousId
    {
        $token = Session::get('anonymous_token');

        return AnonymousId::where('token', $token)->firstOrFail();
    }

    /**
     * Ensure the key belongs to the current user and was self-served.
     */
    protected function authorizeOwnership(ApiKey $apiKey): void
    {
        $anonymousId = $this->currentAnonymousId();

        if (!$apiKey->is_self_service || $apiKey->created_by_anonymous_id !== $anonymousId->id) {
            abort(403);
        }
    }
}
