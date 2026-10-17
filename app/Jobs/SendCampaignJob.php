<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MessageLog;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public Campaign $campaign)
    {
    }

    public function handle(SmsService $sms): void
    {
        $campaign = $this->campaign->fresh();
        if ($campaign->status === 'sent') {
            return;
        }

        $campaign->update(['status' => 'sending']);
        $sent = 0;
        $failed = 0;

        foreach ($campaign->recipients()->cursor() as $contact) {
            $text = $this->personalize($campaign->message, $contact);

            try {
                if ($campaign->channel === 'sms') {
                    $sms->send($contact->phone, $text);
                } else {
                    Mail::raw($text, function ($m) use ($contact, $campaign) {
                        $m->to($contact->email, $contact->name)
                          ->subject($campaign->subject ?: $campaign->name);
                    });
                }
                $sent++;
                $this->log($campaign, $contact, 'sent');
            } catch (\Throwable $e) {
                $failed++;
                $this->log($campaign, $contact, 'failed', $e->getMessage());
            }
        }

        $campaign->update([
            'status'       => 'sent',
            'sent_count'   => $sent,
            'failed_count' => $failed,
            'sent_at'      => now(),
        ]);
    }

    private function personalize(string $message, Contact $contact): string
    {
        return str_replace('{nom}', $contact->name, $message);
    }

    private function log(Campaign $campaign, Contact $contact, string $status, ?string $error = null): void
    {
        MessageLog::create([
            'campaign_id' => $campaign->id,
            'contact_id'  => $contact->id,
            'channel'     => $campaign->channel,
            'status'      => $status,
            'error'       => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
