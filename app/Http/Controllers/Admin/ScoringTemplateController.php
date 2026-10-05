<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScoringTemplateCriterion;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScoringTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');

        $templates = Template::query()
            ->withCount(['vacancy', 'assessment', 'criteria'])
            ->with(['criteria' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->whereNull('archived_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('type', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('status', 1))
            ->when($status === 'inactive', fn ($query) => $query->where('status', 0))
            ->orderBy('type')
            ->orderByDesc('version')
            ->paginate(25)
            ->withQueryString();

        return view('admin.scoring_templates.index', compact('templates', 'search', 'status'));
    }

    public function create()
    {
        return view('admin.scoring_templates.create', [
            'template' => new Template(['status' => 1, 'version' => 1]),
            'criteria' => collect([
                ['key'=>'Education','label'=>'Education','max_points'=>10,'source_type'=>'manual','instructions'=>''],
                ['key'=>'Training','label'=>'Training','max_points'=>10,'source_type'=>'manual','instructions'=>''],
                ['key'=>'Experience','label'=>'Experience','max_points'=>10,'source_type'=>'manual','instructions'=>''],
                ['key'=>'Performance','label'=>'Performance','max_points'=>20,'source_type'=>'manual','instructions'=>''],
                ['key'=>'Written_Examination','label'=>'Written Examination','max_points'=>20,'source_type'=>'written','instructions'=>''],
                ['key'=>'Skills_Test','label'=>'Skills / Performance Test','max_points'=>20,'source_type'=>'skills','instructions'=>''],
                ['key'=>'Interview','label'=>'Behavioral Event Interview','max_points'=>10,'source_type'=>'interview','instructions'=>''],
            ]),
            'inUse' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);
        $criteria = $this->validatedCriteria($request);
        $this->assertTotal($criteria);

        $template = DB::transaction(function () use ($data, $criteria) {
            $template = Template::create([
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'version' => 1,
                'template' => $this->legacyPayload($criteria),
                'status' => (int) $data['status'],
            ]);

            $this->syncCriteria($template, $criteria);

            return $template;
        });

        return redirect()->route('admin.scoring_templates.edit', $template)
            ->with('status', 'Scoring template created successfully.');
    }

    public function edit(Template $scoringTemplate)
    {
        abort_if($scoringTemplate->archived_at, 404);

        $scoringTemplate->load(['criteria' => fn ($q) => $q->orderBy('sort_order')]);
        $inUse = $scoringTemplate->vacancy()->exists() || $scoringTemplate->assessment()->exists();

        return view('admin.scoring_templates.edit', [
            'template' => $scoringTemplate,
            'criteria' => $scoringTemplate->criteria,
            'inUse' => $inUse,
        ]);
    }

    public function update(Request $request, Template $scoringTemplate)
    {
        abort_if($scoringTemplate->archived_at, 404);

        $data = $this->validateTemplate($request);
        $criteria = $this->validatedCriteria($request);
        $this->assertTotal($criteria);

        $inUse = $scoringTemplate->vacancy()->exists() || $scoringTemplate->assessment()->exists();

        if ($inUse && $this->structureChanged($scoringTemplate, $criteria)) {
            throw ValidationException::withMessages([
                'criteria' => 'This scoring template is already in use. Its scoring structure is frozen. Duplicate it as a new version before changing criteria or points.',
            ]);
        }

        DB::transaction(function () use ($scoringTemplate, $data, $criteria, $inUse) {
            $scoringTemplate->update([
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'status' => (int) $data['status'],
            ]);

            if (!$inUse) {
                $scoringTemplate->update(['template' => $this->legacyPayload($criteria)]);
                $this->syncCriteria($scoringTemplate, $criteria);
            }
        });

        return back()->with('status', 'Scoring template updated.');
    }

    public function duplicate(Template $scoringTemplate)
    {
        abort_if($scoringTemplate->archived_at, 404);
        $scoringTemplate->load('criteria');

        $rootId = $scoringTemplate->parent_template_id ?: $scoringTemplate->id;
        $nextVersion = Template::where(function ($query) use ($rootId) {
                $query->where('id', $rootId)->orWhere('parent_template_id', $rootId);
            })
            ->max('version') + 1;

        $copy = DB::transaction(function () use ($scoringTemplate, $rootId, $nextVersion) {
            $copy = Template::create([
                'type' => $scoringTemplate->type,
                'description' => $scoringTemplate->description,
                'version' => $nextVersion,
                'parent_template_id' => $rootId,
                'template' => $scoringTemplate->template,
                'status' => 0,
            ]);

            foreach ($scoringTemplate->criteria as $criterion) {
                $copy->criteria()->create([
                    'key' => $criterion->key,
                    'label' => $criterion->label,
                    'max_points' => $criterion->max_points,
                    'source_type' => $criterion->source_type,
                    'instructions' => $criterion->instructions,
                    'sort_order' => $criterion->sort_order,
                    'is_active' => $criterion->is_active,
                ]);
            }

            return $copy;
        });

        return redirect()->route('admin.scoring_templates.edit', $copy)
            ->with('status', 'New draft version created. Review and activate it before assigning it to a vacancy.');
    }

    public function archive(Template $scoringTemplate)
    {
        if ($scoringTemplate->vacancy()->where('status', 1)->exists()) {
            return back()->with('status', 'This scoring template is assigned to an active vacancy and cannot be archived.');
        }

        $scoringTemplate->update([
            'status' => 0,
            'archived_at' => now(),
        ]);

        return redirect()->route('admin.scoring_templates.index')
            ->with('status', 'Scoring template archived. Historical records remain intact.');
    }

    protected function validateTemplate(Request $request): array
    {
        return $request->validate([
            'type' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|integer|in:0,1',
        ]);
    }

    protected function validatedCriteria(Request $request): array
    {
        $data = $request->validate([
            'criteria' => 'required|array|min:1|max:30',
            'criteria.*.key' => 'required|string|max:120',
            'criteria.*.label' => 'required|string|max:255',
            'criteria.*.max_points' => 'required|numeric|min:0.001|max:100',
            'criteria.*.source_type' => 'required|in:manual,written,skills,interview,other',
            'criteria.*.instructions' => 'nullable|string|max:2000',
        ]);

        $criteria = collect($data['criteria'])
            ->map(function ($criterion) {
                $key = trim((string) $criterion['key']);
                $key = preg_replace('/\s+/', '_', $key);
                return [
                    'key' => $key,
                    'label' => trim((string) $criterion['label']),
                    'max_points' => round((float) $criterion['max_points'], 3),
                    'source_type' => $criterion['source_type'],
                    'instructions' => trim((string) ($criterion['instructions'] ?? '')) ?: null,
                ];
            })
            ->values();

        if ($criteria->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'criteria' => 'Criterion keys must be unique within a scoring template.',
            ]);
        }

        return $criteria->all();
    }

    protected function assertTotal(array $criteria): void
    {
        $total = collect($criteria)->sum('max_points');
        if (abs($total - 100.0) > 0.001) {
            throw ValidationException::withMessages([
                'criteria' => 'The scoring template must total exactly 100 points. Current total: '.number_format($total, 3).'.',
            ]);
        }
    }

    protected function legacyPayload(array $criteria): string
    {
        $payload = [];
        foreach ($criteria as $criterion) {
            $payload[$criterion['key']] = $criterion['max_points'];
        }

        // Existing CAR/RQA views expect the final non-numeric element to be remarks.
        $payload['Remarks'] = 'Remarks';

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function syncCriteria(Template $template, array $criteria): void
    {
        $template->criteria()->delete();

        foreach ($criteria as $index => $criterion) {
            $template->criteria()->create([
                ...$criterion,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    protected function structureChanged(Template $template, array $criteria): bool
    {
        $current = $template->criteria()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($criterion) => [
                'key' => (string) $criterion->key,
                'label' => (string) $criterion->label,
                'max_points' => round((float) $criterion->max_points, 3),
                'source_type' => (string) $criterion->source_type,
                'instructions' => $criterion->instructions ?: null,
            ])
            ->values()
            ->all();

        return $current !== array_values($criteria);
    }
}
