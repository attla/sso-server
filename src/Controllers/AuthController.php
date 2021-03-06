<?php

namespace Attla\SSO\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Attla\SSO\Resolver;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function identifier(Request $request)
    {
        $token = Resolver::getClientProviderToken($request);
        $redirect = $request->redirect ?: $request->r ?: route(config('sso.redirect'));

        if ($user = \Auth::user()) {
            $callback = Resolver::callback($token, $user, $redirect) ?: route(config('sso.redirect'));
            return view('sso::identifier', compact(
                'user',
                'token',
                'callback',
                'redirect'
            ));
        }

        return to_route(config('sso.route-group.as') . 'login', [
            'token' => $token,
            'redirect' => $redirect,
        ]);
    }

    public function login(Request $request)
    {
        $token = $request->token;
        $redirect = $request->redirect ?: route(config('sso.redirect'));
        return view('sso::login', compact('token', 'redirect'));
    }

    public function sign(Request $request)
    {
        $inputs = config('sso.validation.sign');
        $this->validate($request, $inputs);

        $remember = $request->has('remember') ? 525600 : 30;
        $token = $request->token;

        if (\Auth::attempt($request->only(array_keys($inputs)), $remember)) {
            $callback = Resolver::callback(
                $token,
                \Auth::user(),
                $request->redirect ?: $request->r ?: route(config('sso.redirect'))
            ) ?: route(config('sso.redirect'));

            return redirect($callback);
        }

        return back()->withErrors('E-mail ou senha não conferem.');
    }

    public function logout(Request $request)
    {
        \Auth::logout();

        if ($client = Resolver::resolveClientProvider($request)) {
            return redirect($client->host);
        }

        return redirect('/');
    }

    public function register(Request $request)
    {
        $token = $request->token;
        $redirect = $request->redirect ?: $request->r ?: route(config('sso.redirect'));

        if (!$token and $token = Resolver::getClientProviderToken($request)) {
            return to_route(config('sso.route-group.as') . 'register', [
                'token' => $token,
                'redirect' => $redirect,
            ]);
        }

        return view('sso::register', compact('token', 'redirect'));
    }

    public function signup(Request $request)
    {
        $inputs = config('sso.validation.signup');
        $this->validate($request, $inputs);

        $token = $request->token;
        $user = new User($request->only(array_keys($inputs)));

        $user->forceFill([
            'password' => encrypt($request->input('password')),
        ]);

        if ($user->save()) {
            \Auth::login($user, 525600);
            $callback = Resolver::callback(
                $token,
                \Auth::user(),
                $request->redirect ?: $request->r ?: route(config('sso.redirect'))
            ) ?: route(config('sso.redirect'));
            flash("Seja bem-vindo, {$user->name}!");

            return redirect($callback);
        }

        return back()->withErrors('Occorreu um erro ao efetuar o cadastro.');
    }
}
