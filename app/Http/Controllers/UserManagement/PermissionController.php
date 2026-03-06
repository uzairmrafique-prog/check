<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function search()
    {
        $results = Permission::select('id', 'name as text')->get();
        return response()->json(['results' => $results]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = Permission::when($request->search, function($q){
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
            'group_name' => null,
            'action_name' => null,
            'status' => null,
        ];

        return response()->json([
            'form' => $form,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required', 
            'group_name' => 'required',
            'action' => 'required',
            'status' => 'required'
        ]);
        Permission::create($validated);

        return response()->json(['msg' => 'Permission created successfully']);
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
        $form = Permission::findOrFail($id);

        return response()->json([
            'form' => $form,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // dd($request->assign_permission);
        $request->validate([
            'name' => 'required', 
            'group_name' => 'required',
            'action' => 'required',
            'status' => 'required'
        ]);

        $permission = Permission::findOrFail($id);
        $permission->fill($request->all());
        $permission->save();

        return response()->json(['msg' => 'Permission updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
