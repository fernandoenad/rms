<?php

namespace App\Menu\Filters;

use App\Models\ApplicationClaimRequest;
use Illuminate\Support\Facades\Gate;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class HistoricalClaimsCountFilter implements FilterInterface
{
    /**
     * Show the number of claims needing HR action, never the total history.
     * GateFilter runs first, so no claim data is queried for hidden menu items.
     */
    public function transform($item)
    {
        if (($item['key'] ?? null) !== 'historical-claims'
            || !empty($item['restricted'])
            || !Gate::allows('review-applicant-claims')) {
            return $item;
        }

        $pending = ApplicationClaimRequest::query()
            ->whereIn('status', ['pending', 'clarification'])
            ->count();

        // Leave the menu uncluttered when the actionable queue is empty.
        if ($pending > 0) {
            $item['label'] = $pending;
            $item['label_color'] = 'warning';
        } else {
            unset($item['label'], $item['label_color']);
        }

        return $item;
    }
}
