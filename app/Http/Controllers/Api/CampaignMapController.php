<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignMapController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $maps = $campaign->maps()->orderBy('sort_order')->get();

        return response()->json(['maps' => $maps]);
    }

    public function store(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:10240'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($request->boolean('is_default')) {
            $campaign->maps()->update(['is_default' => false]);
        }

        $path = $request->file('image')->store("campaigns/{$campaign->id}/maps", 'public');

        $map = $campaign->maps()->create([
            'name' => $validated['name'],
            'image_path' => $path,
            'width' => $validated['width'] ?? 1200,
            'height' => $validated['height'] ?? 800,
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Mapa adicionado com sucesso.',
            'map' => $map,
        ], 201);
    }

    public function update(Request $request, string $campaignSlug, int $mapId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $map = $campaign->maps()->findOrFail($mapId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($request->boolean('is_default')) {
            $campaign->maps()->where('id', '!=', $map->id)->update(['is_default' => false]);
        }

        $map->fill($validated);
        $map->save();

        return response()->json([
            'message' => 'Mapa atualizado com sucesso.',
            'map' => $map,
        ]);
    }

    public function destroy(Request $request, string $campaignSlug, int $mapId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $map = $campaign->maps()->findOrFail($mapId);

        Storage::disk('public')->delete($map->image_path);
        $map->delete();

        return response()->json(['message' => 'Mapa removido com sucesso.']);
    }
}
