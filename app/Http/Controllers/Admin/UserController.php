<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function __construct()
    {
        // Staff account management includes password resets and role changes.
        // Only HR administrators may access these endpoints.
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        // The legacy all-user ->get() request loaded every staff user into
        // PHP memory and scaled with the entire applicant/staff account base.
        // Bound each web request to 25 staff profiles with their staff roles.
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));

        $users = User::query()
            ->select(['id', 'email', 'name'])
            ->whereHas('role')
            ->with(['role:id,user_id,level,status'])
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->orderBy('id')
            // simplePaginate avoids the full COUNT(*) query required by
            // regular paginate and fetches no more than one page of rows.
            ->simplePaginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'search' => $search]);
    }

    private function requireStaffAccount(User $user): void
    {
        // Guard direct URL access to edit/reset/delete staff-only endpoints:
        // never treat an applicant-only account as a staff account.
        abort_unless($user->role()->exists(), 404, 'Staff user not found.');
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store (Request $request)
    {
        //dd($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'level' => 'required',
        ]);
    
        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password123'),
        ]);

        $newRole = Role::create([
            'user_id' => $newUser->id,
            'level' => $data['level'],
            'status' => 1,
        ]);
    
        return redirect(route('admin.users.index'))->with('status', 'User was successfully saved.');
    }

    public function edit(User $user)
    {
        $this->requireStaffAccount($user);
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update (User $user, Request $request)
    {
        $this->requireStaffAccount($user);
        //dd($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'level' => 'required',
            'status' => 'required',
        ]);
    
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $user->role->update([
            'level' => $data['level'],
            'status' => $data['status'],
        ]);
    
        return redirect(route('admin.users.index'))->with('status', 'User was successfully updated.');
    }

    public function reset (User $user)
    {
        $this->requireStaffAccount($user);
        return view('admin.users.reset', ['user' => $user]);
    }

    public function resetOk (User $user, Request $request)
    {
        $this->requireStaffAccount($user);
        //dd($request);   
        $user->update([
            'password' => Hash::make('password123'),
        ]);
    
        return redirect(route('admin.users.index'))->with('status', 'User was successfully updated.');
    }

    public function delete(User $user)
    {
        $this->requireStaffAccount($user);
        return view('admin.users.delete', ['user' => $user]);
    }

    public function destroy(User $user)
    {
        $this->requireStaffAccount($user);
        $user->delete();
        
        return redirect(route('admin.users.index'))->with('status', 'User was successfully deleted.');
    }
}
