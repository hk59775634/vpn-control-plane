<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\VpnUser;
use App\Services\FreeradiusSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('migrate:sqlite-to-mysql {--legacy= : Legacy sqlite path (defaults to LEGACY_SQLITE_PATH)}', function () {
    $legacyPath = $this->option('legacy') ?: env('LEGACY_SQLITE_PATH', database_path('database.sqlite'));
    if (!is_file($legacyPath)) {
        $this->error("Legacy sqlite not found: {$legacyPath}");
        return 1;
    }

    $this->warn('This will wipe the current MySQL database and re-import from SQLite.');

    // 1) Fresh migrate (drops all tables in current connection, then recreates them)
    $this->info('Running migrate:fresh...');
    $this->call('migrate:fresh', ['--force' => true]);

    // 2) Import data from legacy sqlite into current default connection (mysql)
    $this->info('Importing data from legacy sqlite...');
    DB::connection('sqlite_legacy')->statement('PRAGMA foreign_keys = OFF;');

    // Disable FK checks during import to allow any insertion order.
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    $skip = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    $tables = DB::connection('sqlite_legacy')
        ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

    foreach ($tables as $t) {
        $table = $t->name;
        if (in_array($table, $skip, true)) continue;

        // Only import tables that exist in MySQL after migrations.
        try {
            DB::table($table)->limit(1)->get();
        } catch (\Throwable $e) {
            $this->line("Skip (no such table in MySQL): {$table}");
            continue;
        }

        $this->line("Importing {$table}...");
        $rows = DB::connection('sqlite_legacy')->table($table)->get();
        if ($rows->isEmpty()) continue;

        // Insert in chunks to avoid large queries
        $chunkSize = 500;
        $buffer = [];
        $count = 0;

        foreach ($rows as $row) {
            $arr = (array) $row;
            // Normalize JSON columns where legacy defaulted to '{}' but mysql now allows NULL
            if ($table === 'client_commands' && array_key_exists('payload', $arr) && $arr['payload'] === '') {
                $arr['payload'] = null;
            }
            if ($table === 'anti_block_policies' && array_key_exists('custom_profile', $arr) && $arr['custom_profile'] === '') {
                $arr['custom_profile'] = null;
            }

            $buffer[] = $arr;
            $count++;

            if (count($buffer) >= $chunkSize) {
                DB::table($table)->insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer) DB::table($table)->insert($buffer);

        $this->line("  -> {$count} rows");
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    $this->info('Done.');
    return 0;
})->purpose('Recreate MySQL schema and import data from legacy sqlite');

Schedule::command('agent:recover-stale-commands')->everyMinute();

Artisan::command('radius:sync-cache {--user_id= : 仅同步指定 vpn_users.id}', function () {
    /** @var FreeradiusSyncService $svc */
    $svc = app(FreeradiusSyncService::class);
    $userId = $this->option('user_id');

    if ($userId !== null && $userId !== '') {
        $vu = VpnUser::query()->find((int) $userId);
        if (! $vu) {
            $this->error('vpn_user not found');
            return 1;
        }
        $svc->syncVpnUser($vu);
        $this->info('synced vpn_user_id='.$vu->id);
        return 0;
    }

    $count = 0;
    VpnUser::query()->orderBy('id')->chunk(500, function ($rows) use ($svc, &$count) {
        foreach ($rows as $vu) {
            $svc->syncVpnUser($vu);
            $count++;
        }
    });
    $this->info('synced total vpn_users='.$count);
    return 0;
})->purpose('同步 FreeRADIUS SQL/Redis 认证缓存');
