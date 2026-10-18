<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationClaimRequest;
use App\Services\Applicant\ApplicationClaimService;
use Illuminate\Http\Request;

class ApplicantClaimReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $status = in_array($status, ['open', 'approved', 'rejected'], true) ? $status : 'open';
        $query = ApplicationClaimRequest::with('profile');
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

    public function reject(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate(['review_notes' => 'required|string|min:10|max:2000']);
        $service->reject($claim, $request->user()->id, $data['review_notes']);
        return back()->with('status', 'Claim rejected.');
    }
}
