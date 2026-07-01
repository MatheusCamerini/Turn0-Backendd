<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharacterSheet;
use App\Models\SheetModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterSheetController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $query = $campaign->characterSheets()
            ->with(['user:id,name,email', 'sheetModel:id,title,slug'])
            ->latest();

        if ($campaign->master_id !== $request->user()->id) {
            $query->where('user_id', $request->user()->id)
                ->where('sheet_type', 'player');
        }

        return response()->json(['character_sheets' => $query->get()]);
    }

    public function store(Request $request, string $campaignSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $validated = $request->validate([
            'sheet_model_id' => ['required', 'exists:sheet_models,id'],
            'character_name' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sheet_type' => ['sometimes', 'in:player,npc,enemy'],
        ]);

        $sheetModel = SheetModel::findOrFail($validated['sheet_model_id']);
        $sheetType = $validated['sheet_type'] ?? 'player';

        $isLinked = $campaign->sheetModels()->where('sheet_model_id', $sheetModel->id)->exists();
        if (!$isLinked) {
            return response()->json([
                'message' => 'Este modelo não está vinculado à campanha.',
            ], 422);
        }

        $isMaster = $campaign->master_id === $request->user()->id;

        if (in_array($sheetType, ['npc', 'enemy'], true)) {
            if (!$isMaster) {
                abort(403, 'Apenas o mestre pode criar fichas de NPCs e inimigos.');
            }

            $sheet = $campaign->characterSheets()->create([
                'sheet_model_id' => $sheetModel->id,
                'sheet_type' => $sheetType,
                'user_id' => null,
                'character_name' => $validated['character_name'],
                'data' => $validated['data'],
            ]);
        } else {
            $ownerId = $validated['user_id'] ?? $request->user()->id;

            if ($ownerId !== $request->user()->id && !$isMaster) {
                abort(403, 'Você só pode criar fichas para si mesmo.');
            }

            $sheet = CharacterSheet::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'user_id' => $ownerId,
                    'sheet_model_id' => $sheetModel->id,
                    'sheet_type' => 'player',
                ],
                [
                    'character_name' => $validated['character_name'],
                    'data' => $validated['data'],
                ]
            );
        }

        $sheet->load(['user:id,name,email', 'sheetModel:id,title,slug']);

        return response()->json([
            'message' => 'Ficha salva com sucesso.',
            'character_sheet' => $sheet,
        ], 201);
    }

    public function show(Request $request, string $campaignSlug, int $sheetId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $sheet = $campaign->characterSheets()
            ->with(['user:id,name,email', 'sheetModel'])
            ->findOrFail($sheetId);

        $isMaster = $campaign->master_id === $request->user()->id;

        if (!$isMaster) {
            if ($sheet->sheet_type !== 'player' || $sheet->user_id !== $request->user()->id) {
                abort(403, 'Você não pode ver esta ficha.');
            }
        }

        return response()->json(['character_sheet' => $sheet]);
    }

    public function update(Request $request, string $campaignSlug, int $sheetId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $sheet = $campaign->characterSheets()->findOrFail($sheetId);
        $isMaster = $campaign->master_id === $request->user()->id;

        if (!$isMaster && ($sheet->user_id !== $request->user()->id || $sheet->sheet_type !== 'player')) {
            abort(403, 'Você não pode editar esta ficha.');
        }

        $validated = $request->validate([
            'character_name' => ['sometimes', 'string', 'max:255'],
            'data' => ['sometimes', 'array'],
        ]);

        $sheet->fill($validated);
        $sheet->save();
        $sheet->load(['user:id,name,email', 'sheetModel:id,title,slug']);

        return response()->json([
            'message' => 'Ficha atualizada com sucesso.',
            'character_sheet' => $sheet,
        ]);
    }

    public function destroy(Request $request, string $campaignSlug, int $sheetId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $sheet = $campaign->characterSheets()->findOrFail($sheetId);
        $isMaster = $campaign->master_id === $request->user()->id;

        if (!$isMaster && ($sheet->user_id !== $request->user()->id || $sheet->sheet_type !== 'player')) {
            abort(403, 'Você não pode excluir esta ficha.');
        }

        $sheet->delete();

        return response()->json(['message' => 'Ficha excluída com sucesso.']);
    }
}
