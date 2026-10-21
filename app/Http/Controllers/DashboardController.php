<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodeRequest;
use App\Services\DashboardService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke(PeriodeRequest $request, DashboardService $service)
    {
        [$start,$end] = $request->periode();
        $request->validate(['q' => 'nullable|string|max:191']);
        $defaultInterval = abs(Carbon::parse($start)->diffInDays(Carbon::parse($end))) > 60 ? 'bulan' : 'hari';
        $interval = $request->input('interval', $defaultInterval);

        return view('dashboard', $service->data($request->user(), $start, $end, $interval, $request->input('q', '')) + compact('start', 'end', 'interval'));
    }
}
