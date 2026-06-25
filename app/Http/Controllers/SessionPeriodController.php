<?php

namespace App\Http\Controllers;

use App\Http\Requests\SessionPeriodRequest;
use Illuminate\Http\RedirectResponse;

class SessionPeriodController extends Controller
{
    /**
     * Persist the selected reporting period to the session so it applies to
     * every page for the duration of the user's session, then return to the
     * originating page so its data re-renders for the new window.
     */
    public function update(SessionPeriodRequest $request): RedirectResponse
    {
        $request->session()->put('session_period', $request->selection());

        return back();
    }
}
