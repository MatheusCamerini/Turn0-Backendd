<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SessionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionMessageController extends Controller
{
    use ResolvesCampaign;

    public function index(Request $request, string $campaignSlug, int $sessionId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $session = $campaign->sessions()->findOrFail($sessionId);

        $since = $request->query('since');

        $query = $session->messages()
            ->with('user:id,name')
            ->orderBy('created_at');

        if ($since) {
            $query->where('id', '>', (int) $since);
        }

        $messages = $query->limit(200)->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, string $campaignSlug, int $sessionId): JsonResponse
    {
        $campaign = $this->findCampaign($campaignSlug);
        $this->ensureCampaignAccess($request, $campaign);

        $session = $campaign->sessions()->findOrFail($sessionId);

        if ($session->status !== 'active') {
            abort(422, 'O chat só está disponível em sessões ativas.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $session->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $message->load('user:id,name');

        return response()->json([
            'message' => 'Mensagem enviada.',
            'chat_message' => $message,
        ], 201);
    }
}
