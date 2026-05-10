<?php

namespace Th3JK\Treasurer\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Th3JK\Treasurer\Contracts\SupportsWebhooks;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Services\PaymentService;
use Th3JK\Treasurer\Support\GatewayRegistry;

class WebhookController extends Controller
{
    public function __invoke(
        string $gateway,
        string $token,
        Request $request,
        GatewayRegistry $registry,
        ConfigRepository $config,
        PaymentService $payments,
    ): Response {
        $providerConfig = (array) $config->get("treasurer.providers.{$gateway}", []);

        $expected = (string) ($providerConfig['webhooks']['token'] ?? '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $driver = $registry->resolve($gateway, $providerConfig);

        if (! $driver instanceof SupportsWebhooks) {
            abort(501);
        }

        if (! $driver->verifySignature($request)) {
            abort(401);
        }

        $event = $driver->parseEvent($request);
        if ($event === null) {
            return response()->noContent();
        }

        $payment = Payment::query()
            ->where('provider', $gateway)
            ->where('provider_payment_id', $event->paymentId)
            ->first();

        if ($payment === null) {
            abort(404);
        }

        $payments->refresh($payment);

        return response()->noContent();
    }
}
