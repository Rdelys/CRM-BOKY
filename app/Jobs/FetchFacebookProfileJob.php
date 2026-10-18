<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\FacebookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchFacebookProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $contactId) {}

    public function handle(FacebookService $fb): void
    {
        $contact = Contact::find($this->contactId);

        if ($contact && $contact->facebook_psid && ($name = $fb->profileName($contact->facebook_psid))) {
            $contact->update(['name' => mb_substr($name, 0, 120)]);
        }
    }
}