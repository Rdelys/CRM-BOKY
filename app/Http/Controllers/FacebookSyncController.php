<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncFacebookConversations;
use App\Jobs\SyncFacebookConversationsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FacebookSyncController extends Controller
{
    /** Lance la synchro en arrière-plan. */
    public function start()
    {
        $current = Cache::get(SyncFacebookConversations::STATUS_KEY);

        // Déjà une synchro active : on renvoie son état, le toast s'y rattache
        if ($this->isActive($current)) {
            return response()->json($current + ['already_running' => true], 409);
        }

        $runId = (string) Str::uuid();

        $status = [
            'run_id'      => $runId,
            'trigger'     => 'manual',
            'state'       => 'queued',
            'created'     => 0,
            'updated'     => 0,
            'errors'      => 0,
            'seen'        => 0,
            'pages'       => 0,
            'complete'    => true,
            'message'     => null,
            'started_at'  => now()->toIso8601String(),
            'finished_at' => null,
            'updated_at'  => now()->toIso8601String(),
        ];

        Cache::put(SyncFacebookConversations::STATUS_KEY, $status, now()->addDay());

        SyncFacebookConversationsJob::dispatch($runId);

        return response()->json($status, 202);
    }

    /** État courant (interrogé toutes les 2 s par le toast). */
    public function status()
    {
        return response()->json(
            Cache::get(SyncFacebookConversations::STATUS_KEY) ?? ['state' => 'idle']
        );
    }

    /** Active = en file ou en cours, et mise à jour il y a moins de 10 min (sinon considérée plantée). */
    private function isActive(mixed $status): bool
    {
        return is_array($status)
            && in_array($status['state'] ?? null, ['queued', 'running'], true)
            && isset($status['updated_at'])
            && Carbon::parse($status['updated_at'])->gt(now()->subMinutes(10));
    }
}