<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\UseCases\User\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'profile'])
            ->with('success', 'プロフィールを更新しました。');
    }
}
