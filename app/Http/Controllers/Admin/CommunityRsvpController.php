<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rsvp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityRsvpController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $eventKey = trim((string) $request->query('event'));
        abort_if($eventKey === '', 404);

        $eventName = Rsvp::query()
            ->where('event_key', $eventKey)
            ->latest('created_at')
            ->value('event_name') ?: $eventKey;
        $filename = str($eventName)->slug('-')->toString().'-rsvps.csv';

        return response()->streamDownload(function () use ($eventKey): void {
            echo "\xEF\xBB\xBF";

            $output = fopen('php://output', 'w');
            fputcsv($output, ['nombre', 'correo', 'país']);

            Rsvp::query()
                ->where('event_key', $eventKey)
                ->latest('created_at')
                ->cursor()
                ->each(function (Rsvp $rsvp) use ($output): void {
                    fputcsv($output, [
                        $rsvp->name,
                        $rsvp->email,
                        $rsvp->country,
                    ]);
                });
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
