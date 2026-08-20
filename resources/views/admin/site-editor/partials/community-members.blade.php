@php
    $members = $communityMembers['members'];
    $directory = $communityMembers['directory'];
@endphp

<div class="admin-page-heading">
    <div>
        <p class="admin-kicker">Community / Members</p>
        <h1>Members</h1>
        <p>Usuarios registrados con plan Free o Royal Pass.</p>
    </div>
    <a class="admin-button admin-button-primary" href="{{ $communityMembers['export_url'] }}">Export CSV</a>
</div>

<section class="admin-panel community-directory-panel" aria-labelledby="community-members-title">
    <div class="admin-section-head">
        <div>
            <h2 id="community-members-title">Member database</h2>
            <p class="admin-panel-copy">{{ number_format($members->total()) }} results</p>
        </div>
    </div>

    <form class="community-directory-filters" method="GET" action="{{ route('admin.site-editor.show', ['page' => 'community']) }}">
        <input name="community_section" type="hidden" value="members">
        <label>
            <span>Search</span>
            <input name="member_search" type="search" value="{{ $communityMembers['search'] }}" placeholder="Username, name, email or country">
        </label>
        <label>
            <span>Plan</span>
            <select name="member_plan">
                <option value="all" @selected($communityMembers['plan'] === 'all')>All plans</option>
                <option value="free" @selected($communityMembers['plan'] === 'free')>Free</option>
                <option value="royal" @selected($communityMembers['plan'] === 'royal')>Royal Pass</option>
            </select>
        </label>
        <button class="admin-button admin-button-soft" type="submit">Apply</button>
        @if ($communityMembers['search'] !== '' || $communityMembers['plan'] !== 'all')
            <a class="admin-button admin-button-ghost" href="{{ route('admin.site-editor.show', ['page' => 'community', 'community_section' => 'members']) }}">Clear</a>
        @endif
    </form>

    <div class="community-member-table-wrap">
        <table class="community-member-table">
            <thead>
                <tr><th>Plan</th><th>Photo</th><th>Username</th><th>Email</th><th>Country</th><th>A member since</th></tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    @php
                        $avatarPath = trim((string) $member->avatar_path);
                        $avatarUrl = $avatarPath === '' ? null : (filter_var($avatarPath, FILTER_VALIDATE_URL) ? $avatarPath : asset(ltrim($avatarPath, '/')));
                        $displayUsername = $member->username ?: $member->name;
                        $initials = str($displayUsername)->replaceMatches('/[^\pL\pN]+/u', '')->substr(0, 2)->upper();
                    @endphp
                    <tr>
                        <td><span @class(['community-plan-pill', 'is-royal' => $member->hasRoyalAccess()])>{{ $directory->planLabel($member) }}</span></td>
                        <td>
                            <span class="community-member-avatar">
                                @if ($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ $displayUsername }} profile photo" loading="lazy">
                                @else
                                    {{ $initials ?: 'M' }}
                                @endif
                            </span>
                        </td>
                        <td><strong>{{ $member->username ? '@'.$member->username : $member->name }}</strong></td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $directory->countryLabel($member->country_code) }}</td>
                        <td>{{ $member->created_at?->timezone(config('admin.publishing_timezone', 'America/Panama'))->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No members match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($members->hasPages())
        <nav class="community-pagination" aria-label="Member pages">
            @if ($members->onFirstPage())
                <span>Previous</span>
            @else
                <a href="{{ $members->previousPageUrl() }}">Previous</a>
            @endif
            <strong>Page {{ $members->currentPage() }} of {{ $members->lastPage() }}</strong>
            @if ($members->hasMorePages())
                <a href="{{ $members->nextPageUrl() }}">Next</a>
            @else
                <span>Next</span>
            @endif
        </nav>
    @endif
</section>
