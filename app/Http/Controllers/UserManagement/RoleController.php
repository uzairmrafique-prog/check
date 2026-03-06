<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function search()
    {
        $results = Role::select('id', 'name as text')->get();
        return response()->json(['results' => $results]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = Role::when($request->search, function($q){
            $q->where('name', 'LIKE', '%'.request('search').'%');
        })
        ->paginate($request->per_page ? $request->per_page : 25, ['*'], 'page', $request->page_no ? $request->page_no : 1);
        
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $form = [
            'name' => null,
            'redirected_to' => null,
        ];

        $all_permissions = Permission::select('id', 'name', 'group_name')->get();
        $all_permissions = $all_permissions->groupBy('group_name')->sortKeys();

        return response()->json([
            'form' => $form,
            'all_permissions' => $all_permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'redirected_to' => 'required']);
        Role::create(['name' => $request->name, 'redirected_to' => $request->redirected_to]);

        return response()->json(['msg' => 'Role created successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $form = Role::findOrFail($id);
        $all_permissions = Permission::select('id', 'name', 'group_name', 'action')->get();
        $all_permissions = $all_permissions->groupBy('group_name')->sortKeys();
        $assign_permission = $form->permissions->pluck('id');

        return response()->json([
            'form' => $form,
            'all_permissions' => $all_permissions,
            'assign_permission' => $assign_permission
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // dd($request->assign_permission);
        $request->validate(['form.name' => 'required', 'form.redirected_to' => 'required']);

        $role = Role::findOrFail($id);

        $role->syncPermissions($request->assign_permission);

        $users = User::role($role)->get();

        foreach($users as $user){
            $user->syncPermissions($role);

            $user->revokePermissionTo(Permission::all());
            $user->givePermissionTo($request->assign_permission);
        }

        $role->name  = $request->form['name'];
        $role->redirected_to  = $request->form['redirected_to'];

        $role->save();

        return response()->json(['msg' => 'Role updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
