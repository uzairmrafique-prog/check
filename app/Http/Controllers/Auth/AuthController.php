<?php

namespace App\Http\Controllers\Auth;

use Exception;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{

    public function signUp(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:30',
            'email' => 'required|unique:users|email|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
            'password' => 'min:8|required|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).+$/',
            'confirm_password' => 'min:8|required|same:password|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).+$/',
            'mobile_no' => 'required|unique:users,mobile_no',
        ]);

        $user = User::create($request->all());
        $user->syncRoles(['Customer']);

        return response()->json(['auth' => true, 'message' => 'User registered successfully']);
    }
    
    public function login(Request $request)
    {
        // dd($request->all());
        $login_type = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile_no';

        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $credentials = [
            $login_type => $request->username,
            'password' => $request->password,
        ];

        $remember = $request->has('remember') && $request->remember == true;

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return response()->json([
                'auth' => true,
                'message' => 'Login successful',
                'remember' => $remember
            ]);
        }

        return response()->json(['auth' => false, 'msg' => "The provided credentials do not match our records."]);
    }



    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/sign-in');
    }

    public function is_authenticated(Request $request)
    {
        $authCheck = Auth::check();
        $isGuestMiddleware = $request->middleware == 'guest';

        if ($authCheck && $isGuestMiddleware) {
            return response()->json(['auth' => false, 'redirect' => Auth::user()->roles[0]->redirected_to]);
        } elseif (!$authCheck && $isGuestMiddleware) {
            return response()->json(['auth' => true, 'is_auth_pass' => false]);
        } elseif ($authCheck && !$isGuestMiddleware) {
            $is_allowed = Auth::user()->getPermissionsViaRoles()->pluck('name')->contains($request->current_route);
            if (!$is_allowed) {
                return response()->json(['auth' => true, 'is_auth_pass' => false, 'go_back' => true]);
            }
            return response()->json(['auth' => true, 'is_auth_pass' => true]);
        } else {
            return response()->json(['auth' => false, 'redirect' => 'sign-in']);
        }
    }
    public function syncRoute(Request $request)
    {
        if (Auth::check()) {
            foreach ($request->all() as $route) {
                Permission::updateOrCreate(
                    ['name' => $route['name']],
                    ['name' => $route['name'], 'action' => $route['action'], 'group_name' => $route['group_name'], 'guard_name' => 'web']
                );
            }
            return response()->json(['saved' => true, 'msg' => 'Route Synced Successfully!']);
        } else {
            return response()->json(['unauthorized' => true]);
        }
    }

    public function sent_otp(Request $request)
    {
        $user = User::firstWhere('email', $request->email);
        // try{
        if ($request->type == 'email') {
            $user->email_otp = Str::random(6);

            $subject = "Email Verification Code";

            Mail::raw('Your Email Verification Code Is: ' . $user->email_otp . "\n\nYour Application No Verification Code Is:" . $user->alumni_id, function ($mail) use ($user, $subject) {
                $mail->to($user->email)
                    ->subject($subject);
            });
        } else if ($request->type == 'sms') {
            $user->sms_otp = Str::random(6);
            $sms_data = "Your Mobile Verification Code Is : " . $user->sms_otp;
            // dd($user_phone->phone);
            send_sms($user->mobile_no, $sms_data);
        }
        $user->save();

        return response()->json(['msg' => 'Verfication Code Sent Successfully!']);
    }

    public function verify_otp(Request $request)
    {
        $user = User::firstWhere('email', $request->email);

        $request->validate([
            'type' => 'required',
            'value' => 'required'
        ]);

        if ($request->type == 'email') {
            $matched = $request->value == $user->email_otp;
            if (!$matched) {
                return response()->json([
                    'type' => 'error',
                    'msg' => 'Verification Code is invalid!',
                    'is_verified' => [
                        'email_verify' => $user->email_verify,
                        // 'mobile_verify' => $user->mobile_verify,
                        'std_id_verify' => $user->std_id_verify
                    ]
                ]);
            } else {
                $user->email_verify = 1;
            }
        } else if ($request->type == 'sms') {
            $matched = $request->value == $user->sms_otp;
            if (!$matched) {
                return response()->json([
                    'type' => 'error',
                    'msg' => 'Verification Code is invalid!',
                    'is_verified' => [
                        'email_verify' => $user->email_verify,
                        // 'mobile_verify' => $user->mobile_verify,
                        'std_id_verify' => $user->std_id_verify
                    ]
                ]);
            } else {
                // $user->mobile_verify = 1;
            }
        } else if ($request->type == 'student_id') {
            $matched = $request->value == $user->alumni_id;
            if (!$matched) {
                return response()->json([
                    'type' => 'error',
                    'msg' => 'Verification Code is invalid!',
                    'is_verified' => [
                        'email_verify' => $user->email_verify,
                        // 'mobile_verify' => $user->mobile_verify,
                        'std_id_verify' => $user->std_id_verify
                    ]
                ]);
            } else {
                $user->std_id_verify = 1;
            }
        }
        $user->save();

        $verified = ($user->email_verify == 1 && $user->std_id_verify == 1);
        return response()->json([
            'verfied' => $verified,
            'type' => 'success',
            'msg' => 'Verified',
            'is_verified' => [
                'email_verify' => $user->email_verify,
                // 'mobile_verify' => $user->mobile_verify,
                'std_id_verify' => $user->std_id_verify
            ]
        ]);
    }

    public function sendApplicationStatusUpdateEmail(User $user, $status)
    {
        $subject = "Application Status Update";
        $message = "Dear {$user->name},\n\nYour application status has been updated to: {$status}.\n\nThank you.";

        Mail::raw($message, function ($mail) use ($user, $subject) {
            $mail->to($user->email)
                ->subject($subject);
        });
    }

    public function forgot_password(Request $request)
    {
        $request->validate([
            'email' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) return response(['msg' => 'Email not in the record!'], 422);

        try {
            $user->email_otp = Str::random(6);
            $subject = "Email Verification Code";
            Mail::raw('Your Email Verification Code Is: ' . $user->email_otp, function ($mail) use ($user, $subject) {
                $mail->to($user->email)->subject($subject);
            });
            $user->save();
            return response()->json(['status' => true, 'type' => 'success', 'msg' => "Email Sent Successfully!"]);
        } catch (Exception $e) {
            Log::warning($e);
            return response()->json(['status' => false, 'type' => 'error', 'msg' => "Email Failed!"]);
        }
    }

    public function forgot_pass_verify(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        $request->validate([
            'code' => 'required'
        ]);

        $matched = $request->code == $user->email_otp;
        if (!$matched) {
            return response()->json(['status' => false, 'type' => 'error', 'msg' => 'Verification Code is invalid!']);
        }

        $user->save();

        return response()->json([
            'status' => true,
            'type' => 'success',
            'msg' => 'Verified',
        ]);
    }

    public function set_new_pass(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        $request->validate([
            'email' => 'required',
            'password' => 'min:8|required_with:confirm_password|same:confirm_password|required|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).+$/',
            'confirm_password' => 'required|same:password',
        ]);

        $user->password = $request->password;
        $user->save();

        return response()->json(['msg' => 'Password Updated Successfully']);
    }
}
