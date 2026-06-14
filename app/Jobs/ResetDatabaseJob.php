<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $mode = 'full')
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $property = "Tables_in_{$dbName}";

        Schema::disableForeignKeyConstraints();

        if ($this->mode === 'transactions') {
            $transactionTables = ['orders', 'order_details', 'order_addons', 'payments', 'activity_log'];
            foreach ($transactionTables as $tableName) {
                if (Schema::hasTable($tableName)) {
                    DB::table($tableName)->truncate();
                }
            }
        } else {
            // Full Reset (default)
            foreach ($tables as $table) {
                $tableName = $table->$property;

                // Skip migrations and system tables to prevent breaking the app
                if (in_array($tableName, ['migrations', 'system', 'users', 'branches', 'provinces', 'cities', 'districts', 'villages'])) {
                    continue;
                }

                DB::table($tableName)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
