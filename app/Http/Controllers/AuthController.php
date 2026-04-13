<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginView()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $validator->validated();

        $user = User::where('email', $credentials['email'])->first();

        // 🔐 Force bcrypt safety (Laravel 12 compliant)
        if (!str_starts_with($user->password, '$2y$')) {
            return back()->withErrors([
                'password' => 'Password hash is invalid. Please reset your password.',
            ]);
        }

        if (Auth::attempt($credentials)) {
            return redirect()->route('attendance.dashboard');
        }

        return back()->withErrors([
            'password' => 'The password does not match with username',
        ])->withInput();
    }


    public function registerView(){
        return view('register');
    }

    public function register(Request $request){

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email','unique:users'],
            'password' => ['required',"confirmed", Password::min(7)],
        ]);

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated["name"],
            "email" => $validated["email"],
            "password" => Hash::make($validated["password"])
        ]);

        auth()->login($user);

        return redirect()->route('attendance.dashboard');
    }

    public function logout()
    {
        auth()->logout();
        return redirect()->route('login');
    }
}
