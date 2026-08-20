<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CommunityMemberDirectory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityMemberController extends Controller
{
    public function __invoke(Request $request, CommunityMemberDirectory $directory): StreamedResponse
    {
        $filters = $directory->filters($request);
        $filename = 'community-members'.($filters['plan'] === CommunityMemberDirectory::PLAN_ALL ? '' : '-'.$filters['plan']).'.csv';

        return response()->streamDownload(function () use ($directory, $filters): void {
            echo "\xEF\xBB\xBF";

            $output = fopen('php://output', 'w');
            fputcsv($output, ['plan', 'photo', 'username', 'email', 'country', 'member since']);

            $directory->query($filters['search'], $filters['plan'])
                ->oldest('created_at')
                ->cursor()
                ->each(function (User $user) use ($directory, $output): void {
                    fputcsv($output, [
                        $directory->planLabel($user),
                        $user->avatar_path ? url(ltrim($user->avatar_path, '/')) : '',
                        $user->username ?: $user->name,
                        $user->email,
                        $directory->countryLabel($user->country_code),
                        $user->created_at?->timezone(config('admin.publishing_timezone', 'America/Panama'))->toDateString(),
                    ]);
                });
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
