<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SheetModel;
use App\Support\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SheetModelController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $models = SheetModel::query()
            ->where('user_id', $userId)
            ->orWhere('is_public', true)
            ->latest()
            ->get();

        return response()->json(['sheet_models' => $models]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'schema' => ['required', 'array', 'min:1'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $validated['is_public'] = true;
        $model = SheetModel::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => SlugGenerator::unique($validated['title'], SheetModel::class),
            'description' => $validated['description'] ?? null,
            'schema' => $validated['schema'],
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json([
            'message' => 'Modelo de ficha criado com sucesso.',
            'sheet_model' => $model,
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $model = SheetModel::query()->where('slug', $slug)->firstOrFail();

        if ($model->user_id !== $request->user()->id && !$model->is_public) {
            abort(403, 'Você não tem acesso a este modelo.');
        }

        return response()->json(['sheet_model' => $model]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $model = SheetModel::query()->where('slug', $slug)->firstOrFail();

        if ($model->user_id !== $request->user()->id) {
            abort(403, 'Apenas o autor pode editar este modelo.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'schema' => ['sometimes', 'array', 'min:1'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['title'])) {
            $model->title = $validated['title'];
            $model->slug = SlugGenerator::unique($validated['title'], SheetModel::class, $model->id);
        }

        $model->fill($validated);
        $model->save();

        return response()->json([
            'message' => 'Modelo atualizado com sucesso.',
            'sheet_model' => $model,
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $model = SheetModel::query()->where('slug', $slug)->firstOrFail();

        if ($model->user_id !== $request->user()->id) {
            abort(403, 'Apenas o autor pode excluir este modelo.');
        }

        $model->delete();

        return response()->json(['message' => 'Modelo excluído com sucesso.']);
    }

    public function attachToCampaign(Request $request, string $campaignSlug, string $modelSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $model = SheetModel::query()->where('slug', $modelSlug)->firstOrFail();

        if ($model->user_id !== $request->user()->id && !$model->is_public) {
            abort(403, 'Este modelo não pode ser vinculado à campanha.');
        }

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            \Illuminate\Support\Facades\DB::table('campaign_sheet_model')
                ->where('campaign_id', $campaign->id)
                ->update(['is_default' => false]);
        }

        $campaign->sheetModels()->syncWithoutDetaching([
            $model->id => ['is_default' => $isDefault],
        ]);

        return response()->json([
            'message' => 'Modelo vinculado à campanha.',
            'campaign' => $campaign->load('sheetModels:id,title,slug'),
        ]);
    }

    public function detachFromCampaign(Request $request, string $campaignSlug, string $modelSlug): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureMaster($request, $campaign);

        $model = SheetModel::query()->where('slug', $modelSlug)->firstOrFail();
        $campaign->sheetModels()->detach($model->id);

        return response()->json(['message' => 'Modelo desvinculado da campanha.']);
    }
}
