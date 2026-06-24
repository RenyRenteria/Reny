<?php

namespace Tests\Feature;

use App\Models\FanEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_confirm_free_store_rsvp(): void
    {
        $user = User::factory()->create([
            'name' => 'RSVP Fan',
        ]);

        $response = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'concert',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('event.name', 'Reny Renteria en Concierto')
            ->assertJsonPath('ticket.status', 'reserved')
            ->assertJsonPath('ticket.rsvp_status', 'confirmed');

        $event = FanEvent::where('title', 'Reny Renteria en Concierto')->firstOrFail();
        $ticket = Ticket::where('user_id', $user->id)->where('event_id', $event->id)->firstOrFail();

        $this->assertSame('store_rsvp', $event->metadata['source']);
        $this->assertSame('concert', $event->metadata['store_event_key']);
        $this->assertSame('reserved', $ticket->status);
        $this->assertSame('confirmed', $ticket->rsvp_status);
        $this->assertNull($ticket->order_id);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Registered')
            ->assertSee('View Details')
            ->assertDontSee('/store/checkout/concert', false);
    }

    public function test_store_rsvp_is_idempotent_for_same_user_and_event(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertOk();

        $second = $this->actingAs($user)->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertOk();

        $this->assertSame($first->json('ticket.id'), $second->json('ticket.id'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_rsvp_requires_login_and_does_not_create_ticket(): void
    {
        $this->postJson(route('store.rsvp'), [
            'event_key' => 'making',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_unknown_store_rsvp_event_returns_retryable_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('store.rsvp'), [
                'event_key' => 'missing-event',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_key');

        $this->assertDatabaseCount('tickets', 0);
    }
}
