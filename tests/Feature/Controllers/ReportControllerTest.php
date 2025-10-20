<?php

use App\Models\Report;

test('if report is greater than expiration time abort forbidden', function () {
    $createReportAt = now()->subSeconds(config('unblock.report_expiration') + 1);

    $report = Report::factory()->create([
        'created_at' => $createReportAt,
        'updated_at' => $createReportAt,
    ]);

    $this->get('/report/'.$report->id)->assertForbidden();
});

// TODO: Fix array to string conversion error in view
// it('can get report', function () {
//     $report = Report::factory()->create([
//         'logs' => [
//             'csf' => 'Simple string log',
//             'exim' => 'Simple string log',
//         ],
//         'analysis' => [
//             'details' => 'Simple string analysis',
//             'was_blocked' => true,
//         ],
//     ]);

//     $response = $this->get('/report/'.$report->id);

//     ray($response->getContent());

//     $response->assertOk();
// });
