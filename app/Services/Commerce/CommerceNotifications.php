<?php

namespace App\Services\Commerce;

use App\Mail\CommerceNotice;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CommerceNotifications
{
    public function send(Order $order): void
    {
        if (! config('commerce.notifications_enabled') || ! config('commerce.notifications_since')
            || $order->created_at->lt(Carbon::parse(config('commerce.notifications_since')))) {
            return;
        }
        $customer = data_get($order->metadata, 'customer.email') ?: $order->user?->email;
        $reference = 'RENY-'.$order->id;
        $account = route('account.show');
        if ($order->status === 'completed' && (int) $order->refund_amount_cents === 0) {
            $this->deliver($order, 'customer.receipt', $customer, new CommerceNotice(
                'Pago confirmado · '.$reference,
                'Recibimos tu pago de USD '.number_format($order->amount_cents / 100, 2).'. Consulta tu compra y su estado en tu cuenta.',
                $account,
            ));
        }
        if ($order->refund_amount_cents > 0) {
            $this->deliver($order, 'customer.refund.'.$order->refund_amount_cents, $customer, new CommerceNotice(
                'Reembolso registrado · '.$reference,
                'Registramos un total reembolsado de USD '.number_format($order->refund_amount_cents / 100, 2).'. El plazo de abono depende de PayPal y de tu banco.',
                $account,
            ));
        }
        $fulfillment = $order->yoycolFulfillment;
        if ($fulfillment?->state === 'shipped' && $order->status === 'completed' && (int) $order->refund_amount_cents === 0) {
            $this->deliver($order, 'customer.shipped', $customer, new CommerceNotice(
                'Tu pedido fue enviado · '.$reference,
                'Tu merch ya tiene seguimiento. Entra a tu cuenta para consultar el transportista y los movimientos.',
                $account,
            ));
        }
        $needsPaymentReview = $order->status === 'payment_review'
            || ($order->status === 'pending' && data_get($order->metadata, 'checkout.capture_started') && $order->updated_at->lt(now()->subMinutes(15)));
        $state = $needsPaymentReview ? 'payment_review' : $fulfillment?->state;
        if ($fulfillment && $fulfillment->state !== 'cancelled' && ($order->status === 'refunded' || $order->refund_amount_cents > 0)) {
            $state = 'refund_review';
        }
        if (in_array($state, ['payment_required', 'review', 'refund_review', 'payment_review'], true)) {
            $instruction = match ($state) {
                'payment_required' => 'Pago a Yoycol pendiente. Verifica costos y paga al proveedor para iniciar producción.',
                'refund_review' => 'Cliente reembolsado. Revisa y cancela el pedido en Yoycol si el proveedor lo permite. No pagues producción.',
                'payment_review' => 'Confirmación del cobro pendiente. Revisa PayPal y concilia. No vuelvas a cobrar al cliente.',
                default => 'Pedido Yoycol ambiguo. Concilia por referencia antes de autorizar cualquier reintento.',
            };
            $this->deliver($order, 'operations.'.$state.'.'.now()->toDateString(), config('commerce.operations_email'), new CommerceNotice(
                'Acción pendiente · '.$reference,
                $instruction.($fulfillment ? ' Referencia Yoycol: '.$fulfillment->store_order_sn : ''),
                $fulfillment ? route('admin.yoycol.index') : route('admin.dashboard'),
            ));
        }
    }

    private function deliver(Order $order, string $key, ?string $recipient, CommerceNotice $mail): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Cache::lock('commerce-notice-'.$order->id.'-'.hash('sha256', $key), 120)->get(function () use ($order, $key, $recipient, $mail): void {
            DB::table('commerce_notices')->insertOrIgnore([
                'order_id' => $order->id, 'event_key' => $key, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $notice = DB::table('commerce_notices')->where('order_id', $order->id)->where('event_key', $key);
            if ($notice->value('sent_at')) {
                return;
            }
            try {
                Mail::to($recipient)->send($mail);
                $notice->update(['sent_at' => now(), 'updated_at' => now()]);
            } catch (Throwable $exception) {
                // Retry next schedule. Never include recipients, addresses or provider payloads in logs.
                Log::warning('Commerce notification delivery failed.', ['order_id' => $order->id, 'event' => $key, 'exception' => $exception::class]);
            }
        });
    }
}
