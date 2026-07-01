<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CampaignController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $campaigns = Campaign::query()
            ->where('master_id', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->with(['master:id,name,email', 'sheetModels:id,title,slug'])
            ->withCount(['members', 'sessions'])
            ->latest()
            ->get()
            ->map(function (Campaign $campaign) use ($userId) {
                $campaign->user_role = $campaign->master_id === $userId
                    ? 'master'
                    : 'player';

                return $campaign;
            });

        return response()->json(['campaigns' => $campaigns]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        $campaign = Campaign::create([
            'master_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => SlugGenerator::unique($validated['title'], Campaign::class),
            'description' => $validated['description'],
            'password' => $validated['password'],
        ]);

        $campaign->members()->attach($request->user()->id, [
            'role' => 'master',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Campanha criada com sucesso.',
            'campaign' => $campaign->load('master:id,name,email'),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);
        $this->ensureCampaignAccess($request, $campaign);

        $campaign->load([
            'master:id,name,email',
            'members:id,name,email',
            'sheetModels:id,title,slug',
            'maps',
            'sessions.map',
        ]);

        $campaign->user_role = $campaign->master_id === $request->user()->id
            ? 'master'
            : 'player';

        return response()->json(['campaign' => $campaign]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);
        $this->ensureMaster($request, $campaign);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'password' => ['sometimes', 'string', 'min:4'],
        ]);

        if (isset($validated['title'])) {
            $campaign->title = $validated['title'];
            $campaign->slug = SlugGenerator::unique($validated['title'], Campaign::class, $campaign->id);
        }

        if (isset($validated['description'])) {
            $campaign->description = $validated['description'];
        }

        if (isset($validated['password'])) {
            $campaign->password = $validated['password'];
        }

        $campaign->save();

        return response()->json([
            'message' => 'Campanha atualizada com sucesso.',
            'campaign' => $campaign,
        ]);
    }

    public function join(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($validated['password'], $campaign->password)) {
            return response()->json(['message' => 'Senha da campanha incorreta.'], 422);
        }

        if ($campaign->master_id === $request->user()->id) {
            return response()->json(['message' => 'Você já é o mestre desta campanha.']);
        }

        $campaign->members()->syncWithoutDetaching([
            $request->user()->id => [
                'role' => 'player',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        return response()->json([
            'message' => 'Você entrou na campanha com sucesso.',
            'campaign' => $campaign->load('master:id,name,email'),
        ]);
    }

    public function members(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);
        $this->ensureCampaignAccess($request, $campaign);

        $members = $campaign->members()
            ->select('users.id', 'users.name', 'users.email')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'status' => $member->pivot->status,
                    'joined_at' => $member->pivot->joined_at,
                ];
            });

        return response()->json(['members' => $members]);
    }

    public function leave(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);

        if ($campaign->master_id === $request->user()->id) {
            return response()->json([
                'message' => 'O mestre não pode sair da campanha. Exclua-a se desejar encerrá-la.',
            ], 422);
        }

        $this->ensureCampaignAccess($request, $campaign);

        $campaign->members()->detach($request->user()->id);
        $campaign->characterSheets()
            ->where('user_id', $request->user()->id)
            ->where('sheet_type', 'player')
            ->delete();

        return response()->json(['message' => 'Você saiu da campanha com sucesso.']);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $campaign = $this->findCampaign($slug);
        $this->ensureMaster($request, $campaign);

        $campaign->delete();

        return response()->json(['message' => 'Campanha excluída com sucesso.']);
    }
}
