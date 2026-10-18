<?php

namespace App\Services;

use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\MessageLog;

class CampaignLauncher
{
    /**
     * Lance une campagne (brouillon ou programmée) : crée un message "pending" par destinataire
     * et planifie un job par message, espacés selon la cadence (messages par minute).
     *
     * @return bool false si la campagne n'était pas lançable (déjà lancée, annulée…)
     */
    public function launch(Campaign $campaign): bool
    {
        // Verrou atomique : un seul appel peut passer la campagne en "sending"
        $locked = Campaign::where('id', $campaign->id)
            ->whereIn('status', ['draft', 'scheduled'])
            ->update(['status' => 'sending']);

        if (!$locked) {
            return false;
        }

        $campaign->refresh();

        $gap = $this->gap($campaign);
        $position = 0;

        try {
            $this->queueNewRecipients($campaign, $gap, $position);
        } catch (\Throwable $e) {
            // On revient à l'état précédent pour pouvoir relancer proprement
            MessageLog::where('campaign_id', $campaign->id)->where('status', 'pending')->delete();
            Campaign::where('id', $campaign->id)->update(['status' => 'draft']);

            throw $e;
        }

        // Aucun destinataire au moment du lancement
        if ($position === 0) {
            $campaign->update([
                'status'       => 'sent',
                'sent_count'   => 0,
                'failed_count' => 0,
                'sent_at'      => now(),
            ]);
        }

        return true;
    }

    /**
     * Relance une campagne déjà terminée (envoyée ou annulée).
     *
     * - mode "failed" : uniquement les messages en échec ou annulés, plus les nouveaux contacts ciblés
     *   qui n'ont jamais été servis.
     * - mode "all"    : tous les contacts ciblés, y compris ceux qui ont déjà reçu le message.
     *
     * @return int|null nombre de messages remis en file, ou null si la campagne n'était pas relançable
     */
    public function relaunch(Campaign $campaign, string $mode = 'failed'): ?int
    {
        $previous = $campaign->status;

        // Verrou atomique : empêche un double clic de relancer deux fois
        $locked = Campaign::where('id', $campaign->id)
            ->whereIn('status', ['sent', 'cancelled'])
            ->update(['status' => 'sending']);

        if (!$locked) {
            return null;
        }

        $campaign->refresh();

        $gap = $this->gap($campaign);
        $position = 0;
        $statuses = $mode === 'all' ? ['sent', 'failed', 'cancelled'] : ['failed', 'cancelled'];

        try {
            MessageLog::where('campaign_id', $campaign->id)
                ->whereIn('status', $statuses)
                ->chunkById(500, function ($logs) use ($gap, &$position) {
                    foreach ($logs as $log) {
                        $log->update(['status' => 'pending', 'error' => null, 'sent_at' => null]);
                        $this->dispatchFor($log->id, $gap, $position);
                    }
                });

            // Nouveaux contacts ciblés qui n'ont jamais été servis
            $this->queueNewRecipients($campaign, $gap, $position);
        } catch (\Throwable $e) {
            // Relance interrompue : les messages déjà remis en attente sont marqués en échec
            MessageLog::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed', 'error' => 'Relance interrompue']);
            Campaign::where('id', $campaign->id)->update(['status' => $previous]);

            throw $e;
        }

        // Rien à relancer : la campagne retrouve son état
        if ($position === 0) {
            Campaign::where('id', $campaign->id)->update(['status' => $previous]);
        }

        return $position;
    }

    /** Crée un message "pending" pour chaque destinataire qui n'en a pas encore, et le planifie. */
    private function queueNewRecipients(Campaign $campaign, float $gap, int &$position): void
    {
        $campaign->recipients()
            ->whereDoesntHave('logs', fn ($q) => $q->where('campaign_id', $campaign->id))
            ->chunkById(500, function ($contacts) use ($campaign, $gap, &$position) {
                foreach ($contacts as $contact) {
                    $log = MessageLog::create([
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $contact->id,
                        'channel'     => $campaign->channel,
                        'status'      => 'pending',
                    ]);

                    $this->dispatchFor($log->id, $gap, $position);
                }
            });
    }

    /** Planifie l'envoi d'un message, espacé selon sa position dans la file. */
    private function dispatchFor(int $logId, float $gap, int &$position): void
    {
        SendCampaignMessageJob::dispatch($logId)
            ->delay(now()->addSeconds((int) round($position * $gap)));

        $position++;
    }

    /** Secondes entre deux messages. */
    private function gap(Campaign $campaign): float
    {
        return 60 / max(1, (int) $campaign->per_minute);
    }
}