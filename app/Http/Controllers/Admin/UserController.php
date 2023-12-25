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
        $this->middleware('auth');
    }

    public function index()
    {
        $users = User::with('role')->get();

        return view('admin.users.index',['users' => $users]);
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
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update (User $user, Request $request)
    {
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
        return view('admin.users.reset', ['user' => $user]);
    }

    public function resetOk (User $user, Request $request)
    {
        //dd($request);   
        $user->update([
            'password' => Hash::make('password123'),
        ]);
    
        return redirect(route('admin.users.index'))->with('status', 'User was successfully updated.');
    }

    public function delete(User $user)
    {
        return view('admin.users.delete', ['user' => $user]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        
        return redirect(route('admin.users.index'))->with('status', 'User was successfully deleted.');
    }
}
