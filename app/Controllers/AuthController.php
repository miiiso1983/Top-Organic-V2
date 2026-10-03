<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Response, ApiAuth};

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        $this->viewBare('auth/login', ['error' => null]);
    }

    public function login(): void
    {
        $username = trim((string)$this->input('username'));
        $password = (string)$this->input('password');

        if (Auth::attempt($username, $password)) {
            Response::redirect('/dashboard');
        }
        $this->viewBare('auth/login', ['error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    /** تسجيل دخول WebView من الموبايل باستخدام API token */
    public function webAuth(): void
    {
        $token = (string)$this->input('token');
        $redirect = (string)$this->input('redirect', '/dashboard');
        if (!$token || !preg_match('/^\/[-a-zA-Z0-9_.\/]*$/', $redirect)) {
            Response::redirect('/login');
            return;
        }
        $user = ApiAuth::validateToken($token);
        if (!$user) {
            Response::redirect('/login');
            return;
        }
        $_SESSION['user_id'] = (int)$user['id'];
        Auth::refresh();
        Response::redirect($redirect);
    }
}
