<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPermission;
use App\Models\User;
use App\Services\AssessmentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentPermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','admin']);
    }

    public function index()
    {
        $users = User::with('role')->orderBy('name')->get();
        $grants = AssessmentPermission::all()->groupBy('user_id');
        $capabilities = AssessmentAccessService::CAPABILITIES;

        return view('admin.assessment_permissions.index', compact('users','grants','capabilities'));
    }

    public function update(Request $request, User $user)
    {
        $allowed = array_keys(AssessmentAccessService::CAPABILITIES);
        $data = $request->validate([
            'capabilities'=>'nullable|array',
            'capabilities.*'=>'in:'.implode(',',$allowed),
        ]);

        DB::transaction(function () use ($user,$data) {
            AssessmentPermission::where('user_id',$user->id)->delete();

            foreach (array_unique($data['capabilities'] ?? []) as $capability) {
                AssessmentPermission::create([
                    'user_id'=>$user->id,
                    'capability'=>$capability,
                    'granted_by'=>auth()->id(),
                ]);
            }
        });

        return back()->with('status','Assessment capabilities updated for '.$user->email.'.');
    }
}
