<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignLauncher;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = "Lance les campagnes programmées dont l'heure est arrivée";

    public function handle(CampaignLauncher $launcher): int
    {
        $due = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        foreach ($due as $campaign) {
            try {
                if ($launcher->launch($campaign)) {
                    $this->info("Campagne #{$campaign->id} « {$campaign->name} » lancée.");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("Campagne #{$campaign->id} : {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}