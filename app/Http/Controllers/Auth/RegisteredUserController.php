<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterViewResponse;

class RegisteredUserController extends Controller
{
    public function create(RegisterViewResponse $response): RegisterViewResponse
    {
        return $response;
    }

    public function store(Request $request, CreatesNewUsers $creator): RedirectResponse|JsonResponse
    {
        $user = $creator->create($request->all());
        event(new Registered($user));

        $message = 'Your account has been created and is awaiting administrator approval.';

        return $request->wantsJson()
            ? response()->json(['message' => $message], 201)
            : to_route('login')->with('status', $message);
    }
}
