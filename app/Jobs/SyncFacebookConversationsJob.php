<?php

namespace App\Jobs;

use App\Console\Commands\SyncFacebookConversations;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncFacebookConversationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public string $runId) {}

    public function handle(): void
    {
        Artisan::call('crm:sync-facebook', ['--run' => $this->runId]);
    }

    /** Job tué (timeout) ou exception : le toast passe en erreur au lieu de tourner indéfiniment. */
    public function failed(Throwable $e): void
    {
        $status = Cache::get(SyncFacebookConversations::STATUS_KEY);

        if (is_array($status) && ($status['run_id'] ?? null) === $this->runId) {
            Cache::put(SyncFacebookConversations::STATUS_KEY, array_merge($status, [
                'state'       => 'failed',
                'message'     => 'La synchronisation a été interrompue : ' . mb_substr($e->getMessage(), 0, 160),
                'finished_at' => now()->toIso8601String(),
                'updated_at'  => now()->toIso8601String(),
            ]), now()->addDay());
        }
    }
}