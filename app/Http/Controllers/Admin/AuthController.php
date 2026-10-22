<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('admin.login');
    }
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (!Auth::attempt(['email' => $d['email'], 'password' => $d['password'], 'is_admin' => true], $r->boolean('remember'))) return back()->withErrors(['email' => 'Invalid administrator credentials.'])->onlyInput('email');
        $r->session()->regenerate();
        return redirect()->intended('/admin');
    }
    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
