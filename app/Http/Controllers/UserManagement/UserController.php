<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = User::with(['roles' => function($query){
            $query->select('id', 'name as text');
        }])
        ->when($request->search, function($q){
            $q->whereAny(['name', 'email'], 'LIKE', '%'.request('search').'%');
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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    { 
      
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
        ]);

        User::create($validated);

        return response()->json(['msg' => 'User created successfully']);
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            // 'password' => 'nullable',
            // 'pin_code' => 'nullable',
            'roles' => 'required|array|min:1',
            // 'confirm_password' => 'nullable|same:password',
        ]);

        $model = User::findOrFail($id);
        $model->name = $request->name;
        $model->email = $request->email;
        // $model->password = $request->password ? $request->password : $model->password;
        // $model->pin_code = $request->pin_code ? $request->pin_code : $model->pin_code;
        if($request->password){
            $model->password = $request->password;
        }
        if($request->pin_code){
            $model->pin_code = $request->pin_code;
        }
        $model->save();

        $model->syncRoles(collect($request->roles)->pluck('text'));

        return response()->json(['msg' => 'User updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function sync_candidates(){
        return response()->json(['msg' => 'Candidates synced successfully!']);
    }
}
