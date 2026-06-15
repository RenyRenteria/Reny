<?php

namespace Tests\Feature;

use App\Models\FanEvent;
use App\Models\User;
use App\Services\TicketCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_ticket_code_is_hashed_and_not_stored_as_personal_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Ticket Holder',
            'email' => 'holder@example.com',
        ]);
        $event = $this->event();
        $issued = app(TicketCodeService::class)->issue($user, $event);

        $ticket = $issued['ticket']->fresh();

        $this->assertNotSame($issued['code'], $ticket->ticket_code_hash);
        $this->assertStringStartsWith('TKT-'.$ticket->id.'-', $issued['code']);
        $this->assertStringNotContainsString($user->email, $ticket->ticket_code_hash);
        $this->assertSame(substr($issued['code'], -8), $ticket->ticket_code_preview);
        $this->assertSame('Ticket Holder', $ticket->holder_name);
    }

    public function test_staff_can_check_in_ticket_with_raw_code(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['role' => 'admin']);
        $event = $this->event();
        $issued = app(TicketCodeService::class)->issue($user, $event);

        $this->actingAs($staff)
            ->postJson(route('tickets.check-in'), [
                'code' => $issued['code'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('ticket_id', $issued['ticket']->id)
            ->assertJsonPath('event', 'Royal Listening Session');

        $this->assertSame('checked_in', $issued['ticket']->fresh()->status);
        $this->assertNotNull($issued['ticket']->fresh()->checked_in_at);
    }

    public function test_signed_ticket_code_can_be_regenerated_after_reload(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create(['role' => 'admin']);
        $event = $this->event();
        $service = app(TicketCodeService::class);
        $issued = $service->issue($user, $event);

        $regeneratedCode = $service->displayCode($issued['ticket']->fresh());

        $this->assertSame($issued['code'], $regeneratedCode);

        $this->actingAs($staff)
            ->postJson(route('tickets.check-in'), [
                'code' => $regeneratedCode,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in');
    }

    public function test_non_staff_cannot_check_in_tickets(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $issued = app(TicketCodeService::class)->issue($user, $event);

        $this->actingAs($user)
            ->postJson(route('tickets.check-in'), [
                'code' => $issued['code'],
            ])
            ->assertForbidden();

        $this->assertSame('confirmed', $issued['ticket']->fresh()->status);
    }

    public function test_invalid_ticket_code_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)
            ->postJson(route('tickets.check-in'), [
                'code' => 'not-a-real-ticket',
            ])
            ->assertNotFound();
    }

    private function event(): FanEvent
    {
        return FanEvent::create([
            'title' => 'Royal Listening Session',
            'venue' => 'Nexlab Stage',
            'address' => 'Panama City',
            'timezone' => 'America/Panama',
            'starts_at' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
    }
}
