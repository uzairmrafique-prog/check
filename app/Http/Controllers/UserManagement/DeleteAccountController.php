<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\UserAccountDeletionRequest;
use Illuminate\Http\Request;

class DeleteAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return view('account_deletion.delete_account');
    }

    public function submit(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'mobile_no' => 'required|string|exists:users,mobile_no',
            'reason' => 'required|string'
        ]);

        $exists = UserAccountDeletionRequest::where('email', $request->email)->exists();
        if ($exists) {
            return back()->withErrors(['email' => 'An account deletion request for this email has already been received.']);
        }

        $deletion_request = new UserAccountDeletionRequest();
        $deletion_request->email = $request->email;
        $deletion_request->mobile_no = $request->mobile_no;
        $deletion_request->reason = $request->reason;
        $deletion_request->status = 1;
        $deletion_request->save();

        return redirect('thankyou')->with('success', 'Your deletion request has been submitted');
    }
}
