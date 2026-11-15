<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\KolesaTokenUpdateRequest;
use App\Models\KolesaSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class KolesaController extends Controller
{
    public function edit(Request $request): Response
    {
        $setting = KolesaSetting::current();

        return Inertia::render('settings/Kolesa', [
            'hasToken' => $setting->hasToken(),
            'tokenUpdatedAt' => $setting->token_updated_at?->toIso8601String(),
        ]);
    }

    public function update(KolesaTokenUpdateRequest $request): RedirectResponse
    {
        $setting = KolesaSetting::current();
        $setting->token = $request->validated('token');
        $setting->token_updated_at = Carbon::now();
        $setting->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Токен сохранён.')]);

        return to_route('kolesa.edit');
    }
}
