<?php

namespace App\Services\Applicant;

use App\Models\Dropdown;
use App\Models\Office;
use App\Models\Town;

class ProfileReferenceData
{
    /**
     * Read the same HRMIS source used by the original RMS recruitment form.
     * Do not synthesize reference values when the secondary database is down.
     */
    public function all(): array
    {
        return [
            'municipalities' => Town::query()->orderBy('name')->pluck('name')->filter()->unique()->values()->all(),
            'genders' => Dropdown::query()->where('type', 'sex')
                ->orderBy('details')->pluck('details')->filter()->unique()->values()->all(),
            'civil_statuses' => Dropdown::query()->where('type', 'civilstatus')
                ->orderBy('details')->pluck('details')->filter()->unique()->values()->all(),
            'districts' => Office::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($office) => ['id' => (int) $office->id, 'name' => $office->name])
                ->values()->all(),
        ];
    }
}
