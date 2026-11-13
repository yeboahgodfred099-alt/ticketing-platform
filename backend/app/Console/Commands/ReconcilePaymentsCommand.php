<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcilePayments;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PaystackAdapter;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile';
    protected $description = 'Reconcile lingering pending payments against Paystack API';

    public function handle(
        PaystackAdapter $adapter,
        OrderFulfillmentService $fulfillment
    ): int {
        $this->info('Starting payment reconciliation check...');
        $job = new ReconcilePayments();
        $count = $job->handle($adapter, $fulfillment);
        $this->info("Payment reconciliation finished. Reconciled {$count} transactions.");

        return self::SUCCESS;
    }
}

