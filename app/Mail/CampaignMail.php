<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public Contact $contact)
    {
    }

    public function envelope(): Envelope
    {
        $subject = $this->campaign->subject ?: $this->campaign->name;

        // Un contact non enregistré = email de test
        return new Envelope(subject: $this->contact->exists ? $subject : '[TEST] ' . $subject);
    }

    public function content(): Content
    {
        $text = str_replace('{nom}', (string) $this->contact->name, $this->campaign->message);

        // Texte échappé, retours à la ligne conservés, liens cliquables
        $html = nl2br(preg_replace(
            '~(https?://[^\s<]+)~i',
            '<a href="$1" style="color:#2563eb;">$1</a>',
            e($text)
        ));

        return new Content(
            view: 'emails.campaign',
            text: 'emails.campaign-text',
            with: [
                'html'           => $html,
                'plain'          => $text,
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe'      => '<' . $this->unsubscribeUrl() . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscribe', ['id' => $this->contact->id ?? 0]);
    }
}