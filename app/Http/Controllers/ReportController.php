<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Support\Facades\Date;

class ReportController extends Controller
{
    public function __invoke(string $id)
    {
        $report = Report::where('id', $id)->first();

        // Verificar si el informe existe
        if (! $report) {
            return abort(404, 'Report not found');
        }

        $expirationSeconds = config('unblock.report_expiration');
        $expirationTime = $report->created_at->addSeconds($expirationSeconds);

        if (Date::now()->greaterThan($expirationTime)) {
            return abort(403, 'This report link has expired');
        }

        // Mostrar el informe
        return view('reports.show', ['report' => $report]);
    }
}
