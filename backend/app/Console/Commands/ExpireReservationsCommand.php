<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExpireReservations;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = 'Release expired inventory reservations back into available ticket pools';

    public function handle(ExpireReservations $job): int
    {
        $this->info('Checking for expired inventory reservations...');
        $count = $job->handle();
        $this->info("Released {$count} expired reservations.");

        return self::SUCCESS;
    }
}

