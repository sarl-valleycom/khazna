<?php

namespace App\Http\Controllers\Forecast;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\ForecastQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __invoke(Request $request, ForecastQuery $forecast): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $includeOverdueIn = $request->boolean('include_overdue');

        return Inertia::render('forecast/Index', [
            ...$forecast->handle($actor, $request->integer('days', 30), $includeOverdueIn),
            'includeOverdueIn' => $includeOverdueIn,
        ]);
    }
}
