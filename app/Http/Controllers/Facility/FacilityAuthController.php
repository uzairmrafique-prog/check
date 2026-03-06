<?php

namespace App\Http\Controllers\Facility;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Mail\Email_OTP;
use Illuminate\Support\Facades\Mail;
use Psy\Readline\Hoa\Console;


class FacilityAuthController extends Controller
{
    public function register(Request $request){
        $credentials = Validator::make($request->all(), [
            'name' => ['required', 'min:3'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile_no' => ['required', 'unique:users,mobile_no'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        if($credentials->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $credentials->errors()->toArray()
            ], 422);
        }

        $validatedData = $credentials->validated();

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'mobile_no' => $validatedData['mobile_no'],
            'password' => $validatedData['password'],
            'email_verified' => false
        ]);

        if($user){
            // Auth::login($user);
            $user->syncRoles('Facility Manager');

            // Authenticating user after email verification

            $this->send_otp($user->email);
    

            return response()->json([
                'success' => true,
                'message' => 'Your account has been created, now verify your email to complete your profile',
                'email' => $user->email
            ], 201);
        }

        return response()->json([
            'success' => false,
            'server_error' => 'Failed to create account, please try again'
        ], 500);   
    }

    public function send_otp($email){
        try{
            $to = $email;
            $subject = 'Email verification';
            $message = 'Verify your email to complete your profile.';
            $otp = random_int(100000, 999999);

            $user = User::firstWhere('email', $email);

            // storing user email in session to check the status of otp whether it is sent ot not
            session(['otp_pending' => $user->email]);

            $user->email_otp = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save();

            Mail::to($to)->send(new Email_OTP($subject, $message, $otp));
        }
        catch(\Exception $e){
            return response()->json([
                'success' => 'false',
                'message' => 'Failed to send email, please try again'
            ], 500);
        }
    }

    public function check_otp_pending(){
        return response()->json([
            'otp_pending' => (bool) session('otp_pending')
        ]);
    }   

    public function verify_email(Request $request){
        $otp_request = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:6']
        ]);

        if($otp_request->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $otp_request->errors()->toArray()
            ], 422);
        }

        $validatedData = $otp_request->validated();

        $user = User::where('email', $validatedData['email'])->first();

        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if otp has expired or not:
        $is_otp_expired = now()->greaterThan($user->otp_expires_at);

        if($is_otp_expired){
            return response()->json([
                'success' => false,
                'message' => 'Your OTP has been expired, please click on resend otp button to get another OTP'
            ]);
        }

        $match_otp = $user->email_otp && $user->email_otp === $validatedData['otp'];

        if(!$match_otp){
            return response()->json([
                'success' => false,
                'message' => 'Your otp is incorrect'
            ], 422);
        }

        $user->email_verified = true;
        $user->email_otp = null;
        $user->otp_expires_at = null;
        $user->save();

        // Now loginn user and destroy the otp_pending session:

        Auth::login($user);

        session()->forget('otp_pending');

        return response()->json([
            'success' => true,
            'message' => 'Email verified'
        ]);

    }

    public function resend_otp(Request $request){
        $email = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        if($email->fails()){
             return response()->json([
                'success' => false,
                'message' => 'Your email is invalid'
            ], 422);
        }

        $validatedData = $email->validated();
        $user = User::where('email', $validatedData['email'])->first();

        if($user->email_verified){
            return response()->json([
            'success' => false,
            'message' => 'Email is already verified.'
            ], 400);
        }

        return $this->send_otp($user->email);
    }

    public function login(Request $request){
        $credentials = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'remember' => ['nullable', 'boolean']
        ]);

        if($credentials->fails()){
            return response()->json([
                'auth' => false,
                'message' => 'Your credentials are invalid',
                'errors' => $credentials->errors()->toArray()
            ], 422);
        }

        $validatedData = $credentials->validated();

        if(Auth::attempt(['email' => $validatedData['email'], 'password' => $validatedData['password']], $request->boolean('remember'))){

            $user = Auth::user();
            
            // if(!$user->hasRole('Club Branch Manager')){
            //     Auth::logout();
            //     return response()->json([
            //         'auth' => false,
            //         'message' => 'Unauthorized: Only facility managers can login here'
            //     ], 403);
            // }

            $request->session()->regenerate();
            return response()->json([
                'auth' => true,
                'message' => 'Login successfull',
                'user' => $request->user()
            ], 200);
        }

        return response()->json([
            'auth' => false,
            'message' => 'The provided credentials do not match our records'
        ], 401);
    }

}
