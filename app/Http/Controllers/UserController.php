<?php

namespace App\Http\Controllers;

use App\Models\Setup\VendorInfo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $data =  User::with(['roles' => function ($query) {
            $query->select('id', 'name as text');
        }])
        ->when($request->search, function ($q) {
            $q->whereAny(['name', 'email'], 'LIKE', '%' . request('search') . '%');
        })
        ->when($request->role_id, function($q) use ($request){
            $q->whereHas('roles', function($q2) use ($request) {
                $q2->where('id', $request->role_id);
            });
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
        // dd($request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|same:password',
            'mobile_no' => 'nullable|string|max:15',
            'roles' => 'required',
        ]);

        $user = User::create($validated);
        $user->assignRole($request->roles[0]['text']);

        if($request->roles[0]['text'] == 'Club Owner'){
            $vendor = new VendorInfo;
            $vendor->user_id = $user->id;
            $vendor->save();
        }

        if ($user->roles[0]['name'] == 'Admin') {
            $user->save();
        }
        return response()->json(['msg' => 'User created successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json([
            'user' => $user
        ]);
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
            'email' => 'required|email|unique:users,email,' . $id,
            // 'password' => 'required|string|min:6',
            // 'confirm_password' => 'required|same:password',
            // 'pin_code' => 'required|string|min:4|max:6',
            'roles' => 'required',
            'mobile_no' => 'required|string|max:15',
        ]);
        $model = User::findOrFail($id);
        $model->name = $request->name;
        $model->email = $request->email;
        $model->mobile_no = $request->mobile_no;
        if ($request->password) {
            $model->password = $request->password;
        }

        $model->save();
        $model->syncRoles($request->roles[0]['text']);

        return response()->json(['msg' => 'User updated successfully']);
    }
}
