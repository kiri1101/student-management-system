<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\RoleDashboardResolver;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly RoleDashboardResolver $resolver) {}

    public function toResponse($request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->intended($this->resolver->pathFor($user));
    }
}
