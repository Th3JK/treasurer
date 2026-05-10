<?php

namespace Th3JK\Treasurer\Drivers\Comgate;

use Comgate\SDK\Client;
use Comgate\SDK\Comgate;
use Comgate\SDK\Entity\Codes\PaymentStatusCode;
use Comgate\SDK\Entity\Money;
use Comgate\SDK\Entity\Payment as ComgatePayment;
use Comgate\SDK\Entity\Refund as ComgateRefund;
use Comgate\SDK\Exception\ApiException;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\DTOs\RefundResponse;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Exceptions\GatewayException;
use Throwable;

class ComgateGateway implements Gateway, SupportsCancellation, SupportsPayments, SupportsRefunds
{
    public const NAME = 'comgate';

    private ?Client $client = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $payment = (new ComgatePayment)
            ->setPrice(Money::ofCents($request->amountInCents))
            ->setCurrency($request->currency->comgate())
            ->setLabel($request->description)
            ->setReferenceId($request->referenceId)
            ->setLang($request->language->comgate())
            ->setCountry('CZ')
            ->setMethod($request->method?->comgate() ?? 'ALL')
            ->setTest($this->isSandbox())
            ->setUrlPaidRedirect($request->returnUrl)
            ->setUrlCancelledRedirect($request->returnUrl)
            ->setUrlPendingRedirect($request->returnUrl);

        if ($request->email !== null) {
            $payment->setEmail($request->email);
        }

        try {
            $response = $this->client()->createPayment($payment);
        } catch (Throwable $e) {
            throw $this->wrap($e, 'create payment');
        }

        return new PaymentResponse(
            paymentId: $response->getTransId(),
            state: PaymentState::PENDING,
            amountInCents: $request->amountInCents,
            currency: $request->currency,
            provider: self::NAME,
            redirectUrl: $response->getRedirect(),
            method: $request->method,
            raw: $response->toArray(),
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        try {
            $response = $this->client()->getStatus($paymentId);
        } catch (Throwable $e) {
            throw $this->wrap($e, 'fetch payment');
        }

        return new PaymentResponse(
            paymentId: $response->getTransId(),
            state: $this->mapState($response->getStatus()),
            amountInCents: $response->getPrice()->get(),
            currency: Currency::tryFrom($response->getCurrency()) ?? Currency::CZK,
            provider: self::NAME,
            redirectUrl: null,
            raw: $response->toArray(),
        );
    }

    public function refundPayment(string $paymentId, RefundRequest $request): RefundResponse
    {
        $refund = (new ComgateRefund)
            ->setTransId($paymentId)
            ->setAmount(Money::ofCents($request->amountInCents))
            ->setCurrency($this->config['options']['currency'] ?? 'CZK')
            ->setTest($this->isSandbox());

        if ($request->referenceId !== null) {
            $refund->setRefId($request->referenceId);
        }

        try {
            $response = $this->client()->refundPayment($refund);
        } catch (Throwable $e) {
            throw $this->wrap($e, 'refund payment');
        }

        return new RefundResponse(
            state: RefundState::REQUESTED,
            amountInCents: $request->amountInCents,
            provider: self::NAME,
            refundId: null,
            raw: $response->toArray(),
        );
    }

    public function cancelPayment(string $paymentId): bool
    {
        try {
            $this->client()->cancelPayment($paymentId);

            return true;
        } catch (ApiException) {
            return false;
        } catch (Throwable $e) {
            throw $this->wrap($e, 'cancel payment');
        }
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $builder = Comgate::defaults()
            ->setMerchant((string) ($this->config['credentials']['merchant'] ?? ''))
            ->setSecret((string) ($this->config['credentials']['secret'] ?? ''));

        $url = $this->config['urls']['api'] ?? null;
        if (is_string($url) && $url !== '') {
            $builder->setUrl($url);
        }

        return $this->client = $builder->createClient();
    }

    private function isSandbox(): bool
    {
        return ($this->config['environment'] ?? 'sandbox') !== 'production';
    }

    private function mapState(string $native): PaymentState
    {
        return match ($native) {
            PaymentStatusCode::PENDING, PaymentStatusCode::AUTHORIZED => PaymentState::PENDING,
            PaymentStatusCode::PAID => PaymentState::PAID,
            PaymentStatusCode::CANCELLED => PaymentState::CANCELLED,
            default => PaymentState::PENDING,
        };
    }

    private function wrap(Throwable $e, string $action): GatewayException
    {
        $code = $e instanceof ApiException ? (int) $e->getCode() : null;

        return new GatewayException(
            message: "Comgate failed to {$action}: ".$e->getMessage(),
            provider: self::NAME,
            statusCode: $code,
            body: null,
            previous: $e,
        );
    }
}
