<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignSessionController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $sessions = $campaign->sessions()
            ->with('map')
            ->latest()
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function store(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'campaign_map_id' => ['nullable', 'exists:campaign_maps,id'],
            'notes' => ['nullable', 'string'],
            'state' => ['nullable', 'array'],
            'start_immediately' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['campaign_map_id'])) {
            $campaign->maps()->findOrFail($validated['campaign_map_id']);
        }

        $startImmediately = !empty($validated['start_immediately']);
        unset($validated['start_immediately']);

        if ($startImmediately) {
            $campaign->sessions()->where('status', 'active')->update([
                'status' => 'finished',
                'ended_at' => now(),
            ]);
        }

        $session = $campaign->sessions()->create([
            'name' => $validated['name'],
            'campaign_map_id' => $validated['campaign_map_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'state' => $validated['state'] ?? $this->defaultState(),
            'status' => $startImmediately ? 'active' : 'scheduled',
            'started_at' => $startImmediately ? now() : null,
        ]);

        $session->load('map');

        return response()->json([
            'message' => 'Sessão criada com sucesso.',
            'session' => $session,
        ], 201);
    }

    public function show(Request $request, string $campaignSlug, int $sessionId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $session = $campaign->sessions()->with('map')->findOrFail($sessionId);

        return response()->json(['session' => $session]);
    }

    public function update(Request $request, string $campaignSlug, int $sessionId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $session = $campaign->sessions()->findOrFail($sessionId);

        $isMaster = $campaign->master_id === $request->user()->id;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'campaign_map_id' => ['nullable', 'exists:campaign_maps,id'],
            'notes' => ['nullable', 'string'],
            'state' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:scheduled,active,finished'],
        ]);

        if (!$isMaster) {
            unset($validated['status'], $validated['campaign_map_id'], $validated['name'], $validated['notes']);
        }

        if (isset($validated['campaign_map_id'])) {
            $campaign->maps()->findOrFail($validated['campaign_map_id']);
        }

        if (isset($validated['status'])) {
            if ($validated['status'] === 'active') {
                $campaign->sessions()->where('status', 'active')->update([
                    'status' => 'finished',
                    'ended_at' => now(),
                ]);
                $session->started_at = $session->started_at ?? now();
            }

            if ($validated['status'] === 'finished') {
                $session->ended_at = now();
            }
        }

        if (isset($validated['state'])) {
            $validated['state'] = $this->mergeSessionState(
                $session->state,
                $validated['state'],
                $request->user()->id,
                $isMaster
            );
        }

        $session->fill($validated);
        $session->save();
        $session->load('map');

        return response()->json([
            'message' => 'Sessão atualizada com sucesso.',
            'session' => $session,
        ]);
    }

    public function destroy(Request $request, string $campaignSlug, int $sessionId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $session = $campaign->sessions()->findOrFail($sessionId);
        $session->delete();

        return response()->json(['message' => 'Sessão excluída com sucesso.']);
    }

    public function active(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $session = $campaign->sessions()
            ->with('map')
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json(['session' => $session]);
    }

    private function defaultState(): array
    {
        return [
            'tokens' => [],
            'fogCells' => [],
            'showGrid' => true,
        ];
    }

    private function mergeSessionState(?array $current, array $incoming, int $userId, bool $isMaster): array
    {
        $current = $current ?? $this->defaultState();

        if ($isMaster) {
            return [
                'tokens' => $incoming['tokens'] ?? $current['tokens'] ?? [],
                'fogCells' => $incoming['fogCells'] ?? $current['fogCells'] ?? [],
                'showGrid' => $incoming['showGrid'] ?? $current['showGrid'] ?? true,
            ];
        }

        $currentTokens = $current['tokens'] ?? [];
        $incomingById = collect($incoming['tokens'] ?? [])->keyBy('id');

        $mergedTokens = collect($currentTokens)->map(function (array $token) use ($incomingById, $userId) {
            $id = $token['id'] ?? null;
            if (!$id || !$incomingById->has($id)) {
                return $token;
            }

            $incomingToken = $incomingById->get($id);

            if (($token['type'] ?? '') === 'player' && (int) ($token['ownerUserId'] ?? 0) === $userId) {
                return array_merge($token, [
                    'x' => $incomingToken['x'] ?? $token['x'],
                    'y' => $incomingToken['y'] ?? $token['y'],
                ]);
            }

            return $token;
        })->all();

        return array_merge($current, ['tokens' => $mergedTokens]);
    }
}
