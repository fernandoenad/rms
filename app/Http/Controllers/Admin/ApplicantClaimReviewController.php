<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationClaimRequest;
use App\Menu\Filters\HistoricalClaimsCountFilter;
use Illuminate\Support\Facades\Gate;
use App\Services\Applicant\ApplicationClaimService;
use Illuminate\Http\Request;

class ApplicantClaimReviewController extends Controller
{
    /**
     * Small, authorization-gated endpoint for the live HR sidebar badge.
     * No claimant details are returned and responses must not be cached.
     */
    public function pendingCount()
    {
        Gate::authorize('review-applicant-claims');

        return response()->json([
            'count' => HistoricalClaimsCountFilter::actionableCount(),
        ])->header('Cache-Control', 'private, no-store, no-cache, max-age=0');
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $status = in_array($status, ['open', 'approved', 'rejected', 'cancelled'], true) ? $status : 'open';
        $query = ApplicationClaimRequest::with(['profile.user', 'application.vacancy', 'ownershipLink']);
        if ($status === 'open') {
            $query->whereIn('status', ['pending', 'clarification']);
        } else {
            $query->where('status', $status);
        }
        $claims = $query->orderByDesc('created_at')->paginate(25)->appends(['status' => $status]);
        return view('admin.applicant-claims.index', compact('claims', 'status'));
    }

    public function clarification(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate(['review_notes' => 'required|string|min:10|max:2000']);
        $service->requestClarification($claim, $request->user()->id, $data['review_notes']);
        return back()->with('status', 'Clarification requested. No ownership access has been granted.');
    }

    public function approve(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate(['review_notes' => 'required|string|min:10|max:2000', 'identity_verified' => 'accepted']);
        $service->approve($claim, $request->user()->id, $data['review_notes']);
        return back()->with('status', 'Claim approved.');
    }

    public function cancelApproval(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate([
            'cancellation_reason' => 'required|string|min:15|max:2000',
            'confirm_revoke' => 'accepted',
        ]);
        $service->cancelApproval($claim, $request->user()->id, $data['cancellation_reason']);
        return redirect()->route('admin.applicant_claims.index', ['status' => 'cancelled'])
            ->with('status', 'Claim approval cancelled. HR-approved ownership access was revoked.');
    }

    public function reject(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate(['review_notes' => 'required|string|min:10|max:2000']);
        $service->reject($claim, $request->user()->id, $data['review_notes']);
        return back()->with('status', 'Claim rejected.');
    }
}
