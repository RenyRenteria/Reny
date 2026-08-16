<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\PayPalSandboxE2eControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalSandboxE2eController extends Controller
{
    public function prepare(Request $request, PayPalSandboxE2eControl $control): JsonResponse
    {
        $control->authorize($request);
        $validated = $request->validate([
            'run_reference' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $control->prepareExistingCustomer($validated['run_reference']);

        return response()->json([
            'status' => 'ready',
            ...$control->configuration(),
        ]);
    }

    public function arm(Request $request, PayPalSandboxE2eControl $control): JsonResponse
    {
        $control->authorize($request);
        $validated = $request->validate([
            'run_reference' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $control->armPostCaptureFailure($validated['run_reference']);

        return response()->json(['status' => 'armed']);
    }

    public function release(Request $request, PayPalSandboxE2eControl $control): JsonResponse
    {
        $control->authorize($request);
        $validated = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $control->releaseCaptureWebhook($validated['paypal_order_id']);

        return response()->json(['status' => 'released']);
    }

    public function state(Request $request, PayPalSandboxE2eControl $control): JsonResponse
    {
        $control->authorize($request);
        $validated = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        return response()->json($control->checkoutState($validated['paypal_order_id']));
    }
}
