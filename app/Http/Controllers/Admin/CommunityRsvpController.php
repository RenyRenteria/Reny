<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CommunityRsvpDirectory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityRsvpController extends Controller
{
    public function __invoke(Request $request, CommunityRsvpDirectory $directory): StreamedResponse
    {
        $eventKey = trim((string) $request->query('event'));
        abort_if($eventKey === '', 404);
        $event = $directory->event($eventKey);
        abort_unless($event, 404);
        $eventName = (string) $event['event_name'];
        $filename = str($eventName)->slug('-')->toString().'-rsvps.csv';

        return response()->streamDownload(function () use ($event): void {
            echo "\xEF\xBB\xBF";

            $output = fopen('php://output', 'w');
            fputcsv($output, ['name', 'email', 'tickets']);

            $event['registrations']
                ->sortByDesc('latest_at')
                ->each(function (array $registration) use ($output): void {
                    fputcsv($output, [
                        $registration['name'],
                        $registration['email'],
                        $registration['tickets'],
                    ]);
                });
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
