<?php

namespace App\Http\Controllers;

use App\Jobs\FetchFacebookProfileJob;
use App\Models\Contact;
use Illuminate\Http\Request;

class FacebookWebhookController extends Controller
{
    /** Vérification initiale demandée par Meta. */
    public function verify(Request $request)
    {
        if ($request->query('hub_mode') === 'subscribe'
            && hash_equals((string) config('crm.facebook.verify_token'), (string) $request->query('hub_verify_token'))) {
            return response($request->query('hub_challenge'), 200);
        }

        abort(403);
    }

    public function receive(Request $request)
    {
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), (string) config('crm.facebook.app_secret'));
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        $pageId = (string) config('crm.facebook.page_id');

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $psid = (string) ($event['sender']['id'] ?? '');

                // Ignore les échos de la page elle-même
                if ($psid === '' || $psid === $pageId || !empty($event['message']['is_echo'])) {
                    continue;
                }

                $contact = Contact::firstOrNew(['facebook_psid' => $psid]);
                $isNew = !$contact->exists;

                if ($isNew) {
                    $contact->fill(['name' => 'Contact Facebook', 'source' => 'facebook', 'status' => 'prospect']);
                }

                $contact->last_fb_interaction_at = now();

                // Désinscription par message
                $text = trim((string) ($event['message']['text'] ?? ''));
                if (preg_match('/^(stop|arr[êe]ter)$/iu', $text)) {
                    $contact->unsubscribed = true;
                }

                $contact->save();

                if ($isNew) {
                    FetchFacebookProfileJob::dispatch($contact->id);
                }
            }
        }

        // Toujours répondre vite en 200
        return response('EVENT_RECEIVED', 200);
    }
}