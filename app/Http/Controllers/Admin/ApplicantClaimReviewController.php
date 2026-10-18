<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationClaimRequest;
use App\Services\Applicant\ApplicationClaimService;
use Illuminate\Http\Request;

class ApplicantClaimReviewController extends Controller
{
    public function index()
    {
        $claims = ApplicationClaimRequest::whereIn('status', ['pending', 'clarification'])
            ->orderBy('created_at')->paginate(25);
        return view('admin.applicant-claims.index', compact('claims'));
    }

    public function approve(Request $request, ApplicationClaimRequest $claim, ApplicationClaimService $service)
    {
        $data = $request->validate(['review_notes' => 'required|string|min:10|max:2000']);
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
