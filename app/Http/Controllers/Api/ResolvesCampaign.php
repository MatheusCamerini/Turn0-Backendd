<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;

trait ResolvesCampaign
{
    protected function findCampaign(string $slug): Campaign
    {
        return Campaign::query()->where('slug', $slug)->firstOrFail();
    }

    protected function userBelongsToCampaign(Request $request, Campaign $campaign, array $roles = []): bool
    {
        if ($campaign->master_id === $request->user()->id) {
            return true;
        }

        $query = $campaign->members()
            ->where('user_id', $request->user()->id)
            ->where('campaign_user.status', 'active');

        if ($roles) {
            $query->whereIn('campaign_user.role', $roles);
        }

        return $query->exists();
    }

    protected function ensureCampaignAccess(Request $request, Campaign $campaign, array $roles = []): void
    {
        if (!$this->userBelongsToCampaign($request, $campaign, $roles)) {
            abort(403, 'Você não tem acesso a esta campanha.');
        }
    }

    protected function ensureMaster(Request $request, Campaign $campaign): void
    {
        if ($campaign->master_id !== $request->user()->id) {
            abort(403, 'Apenas o mestre pode realizar esta ação.');
        }
    }
}
