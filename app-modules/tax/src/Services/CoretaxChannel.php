<?php

declare(strict_types=1);

namespace Modules\Tax\Services;

use Illuminate\Support\Facades\Http;
use Modules\Tax\Domain\EfakturInvoice;
use Modules\Tax\Domain\EfakturSubmissionStatus;
use Modules\Tax\Domain\EfakturXmlBuilder;
use Modules\Tax\Models\EfakturSubmission;
use Throwable;

/**
 * Transmits a queued e-Faktur to Coretax (or a PJAP relay) and drives its status
 * machine on the reply. The XML is built by EfakturXmlBuilder from an invoice the
 * caller resolves (seller = the company, buyer = the customer — both above Tax in
 * the dependency law, so they are handed in, not fetched here).
 *
 * The state moves only through the EfakturSubmissionStatus guard: Queued/Failed →
 * Sent on a successful POST, → Acked once the NSFP/approval code returns, → Failed
 * (retryable) on any error. An already-Acked submission is left untouched — its
 * serial is spent.
 *
 * NOTE: authored for the team to verify against a Coretax sandbox — it performs live
 * HTTP and is not exercised by the in-session pure-domain suite (which covers the XML
 * bytes and the transition guard instead).
 */
final class CoretaxChannel
{
    public function __construct(
        private readonly EfakturXmlBuilder $builder,
    ) {}

    public function send(EfakturSubmission $submission, EfakturInvoice $invoice): EfakturSubmission
    {
        if ($submission->status->isTerminal()) {
            return $submission; // already acked — do not re-file
        }

        $xml = $this->builder->build($invoice);
        $endpoint = (string) config('tax.coretax.endpoint');
        $channel = (string) config('tax.coretax.channel', 'coretax_api');

        try {
            $submission->update([
                'status' => $submission->status->transitionTo(EfakturSubmissionStatus::Sent),
                'channel' => $channel,
                'request_payload' => array_merge($submission->request_payload ?? [], ['xml' => $xml]),
                'attempts' => $submission->attempts + 1,
            ]);

            $response = Http::withToken((string) config('tax.coretax.token'))
                ->withBody($xml, 'application/xml')
                ->post($endpoint);

            $body = $response->json() ?? [];

            if ($response->successful() && ($body['status'] ?? null) === 'approved') {
                $submission->update([
                    'status' => $submission->status->transitionTo(EfakturSubmissionStatus::Acked),
                    'nsfp' => $body['nsfp'] ?? $body['taxInvoiceNumber'] ?? null,
                    'approval_code' => $body['approvalCode'] ?? null,
                    'response_payload' => $body,
                    'acked_at' => now(),
                ]);

                return $submission;
            }

            throw new \RuntimeException('Coretax rejected the submission: '.$response->status());
        } catch (Throwable $e) {
            $submission->update([
                'status' => $submission->status === EfakturSubmissionStatus::Sent
                    ? $submission->status->transitionTo(EfakturSubmissionStatus::Failed)
                    : $submission->status,
                'last_error' => $e->getMessage(),
            ]);

            return $submission;
        }
    }
}
