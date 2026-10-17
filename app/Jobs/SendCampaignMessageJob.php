<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\MessageLog;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/** Envoie UN message (email ou SMS) d'une campagne. */
class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $logId)
    {
    }

    /** Délai avant chaque nouvelle tentative : 1 min, puis 5 min. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $log = MessageLog::with(['campaign', 'contact'])->find($this->logId);

        // Déjà traité (ou supprimé) : on ne renvoie jamais deux fois
        if (!$log || $log->status !== 'pending') {
            return;
        }

        $campaign = $log->campaign;
        $contact = $log->contact;

        // Campagne supprimée ou annulée entre-temps
        if (!$campaign || $campaign->status !== 'sending') {
            $log->update(['status' => 'cancelled']);

            return;
        }

        // Contact supprimé ou désinscrit depuis le lancement
        if (!$contact || $contact->unsubscribed) {
            $log->update(['status' => 'skipped', 'error' => $contact ? 'Désinscrit' : 'Contact supprimé']);
            $this->finishIfDone($campaign);

            return;
        }

        if ($campaign->channel === 'sms') {
            app(SmsService::class)->send(
                $contact->phone,
                str_replace('{nom}', (string) $contact->name, $campaign->message)
            );
        } else {
            Mail::to($contact->email, $contact->name)->send(new CampaignMail($campaign, $contact));
        }

        $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        $this->finishIfDone($campaign);
    }

    /** Appelé après la dernière tentative ratée. */
    public function failed(\Throwable $e): void
    {
        $log = MessageLog::with('campaign')->find($this->logId);

        if ($log && $log->status === 'pending') {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            if ($log->campaign) {
                $this->finishIfDone($log->campaign);
            }
        }
    }

    /** Quand plus aucun message n'est en attente, la campagne passe à "sent". */
    private function finishIfDone(Campaign $campaign): void
    {
        if ($campaign->logs()->where('status', 'pending')->exists()) {
            return;
        }

        $stats = $campaign->stats();

        Campaign::where('id', $campaign->id)
            ->where('status', 'sending')
            ->update([
                'status'       => 'sent',
                'sent_count'   => $stats['sent'],
                'failed_count' => $stats['failed'],
                'sent_at'      => now(),
            ]);
    }
}