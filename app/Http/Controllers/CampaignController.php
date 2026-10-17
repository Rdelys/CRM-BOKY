<?php

namespace App\Http\Controllers;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MessageLog;
use App\Services\CampaignLauncher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest()->paginate(20);

        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('campaigns.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:120',
            'channel'        => ['required', Rule::in(['sms', 'email'])],
            'subject'        => 'nullable|string|max:150|required_if:channel,email',
            'message'        => 'required|string|max:2000',
            'segment_status' => ['nullable', Rule::in(array_keys(Contact::STATUSES))],
            'segment_source' => ['nullable', Rule::in(array_keys(Contact::SOURCES))],
            'per_minute'     => 'required|integer|min:1|max:600',
            'scheduled_at'   => 'nullable|date|after:now',
        ]);

        $campaign = Campaign::create($data);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campagne créée. Envoyez un test, puis lancez ou programmez l\'envoi.');
    }

    public function show(Campaign $campaign)
    {
        $recipientCount = $campaign->recipients()->count();
        $stats = $campaign->stats();
        $total = in_array($campaign->status, ['draft', 'scheduled'], true)
            ? $recipientCount
            : array_sum($stats);
        $logs = $campaign->logs()->with('contact')->latest('id')->limit(50)->get();

        // Contacts à qui on peut renvoyer : échecs, annulés, et nouveaux contacts jamais servis
        $retryCount = 0;
        if (in_array($campaign->status, ['sent', 'cancelled'], true)) {
            $retryCount = $stats['failed'] + $stats['cancelled']
                + $campaign->recipients()
                    ->whereDoesntHave('logs', fn ($q) => $q->where('campaign_id', $campaign->id))
                    ->count();
        }

        return view('campaigns.show', compact('campaign', 'recipientCount', 'stats', 'total', 'logs', 'retryCount'));
    }

    /** Envoi immédiat (la cadence de la campagne s'applique). */
    public function send(Campaign $campaign, CampaignLauncher $launcher)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return back()->withErrors(['campaign' => 'Cette campagne a déjà été lancée.']);
        }

        $count = $campaign->recipients()->count();
        if ($count === 0) {
            return back()->withErrors(['campaign' => 'Aucun destinataire pour cette campagne.']);
        }

        try {
            $launched = $launcher->launch($campaign);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['campaign' => 'Impossible de lancer l\'envoi : ' . $e->getMessage()]);
        }

        if (!$launched) {
            return back()->withErrors(['campaign' => 'Cette campagne a déjà été lancée.']);
        }

        $minutes = (int) ceil($count / max(1, (int) $campaign->per_minute));

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Envoi lancé : {$count} destinataire(s), environ {$minutes} min.");
    }

    /** Renvoie une campagne terminée : aux contacts sans envoi réussi, ou à tous. */
    public function relaunch(Request $request, Campaign $campaign, CampaignLauncher $launcher)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['failed', 'all'])],
        ]);

        if (!in_array($campaign->status, ['sent', 'cancelled'], true)) {
            return back()->withErrors(['campaign' => 'Seule une campagne terminée peut être renvoyée.']);
        }

        try {
            $queued = $launcher->relaunch($campaign, $data['mode']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['campaign' => 'Impossible de relancer l\'envoi : ' . $e->getMessage()]);
        }

        if ($queued === null) {
            return back()->withErrors(['campaign' => 'Cette campagne est déjà en cours d\'envoi.']);
        }

        if ($queued === 0) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('success', 'Aucun message à renvoyer : tous les envois ont réussi.');
        }

        $minutes = (int) ceil($queued / max(1, (int) $campaign->per_minute));

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Renvoi lancé : {$queued} message(s), environ {$minutes} min.");
    }

    /** Programme l'envoi à une date et une heure précises. */
    public function schedule(Request $request, Campaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return back()->withErrors(['campaign' => 'Cette campagne a déjà été lancée.']);
        }

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        if ($campaign->recipients()->count() === 0) {
            return back()->withErrors(['campaign' => 'Aucun destinataire pour cette campagne.']);
        }

        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $data['scheduled_at']]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Envoi programmé pour le ' . Carbon::parse($data['scheduled_at'])->format('d/m/Y à H:i') . '.');
    }

    /** Annule la programmation : la campagne redevient un brouillon. */
    public function unschedule(Campaign $campaign)
    {
        $updated = Campaign::where('id', $campaign->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'draft', 'scheduled_at' => null]);

        if (!$updated) {
            return back()->withErrors(['campaign' => 'Cette campagne n\'est pas programmée.']);
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Programmation annulée.');
    }

    /** Arrête une campagne en cours : les messages en attente ne seront pas envoyés. */
    public function cancel(Campaign $campaign)
    {
        $cancelled = Campaign::where('id', $campaign->id)
            ->where('status', 'sending')
            ->update(['status' => 'cancelled']);

        if (!$cancelled) {
            return back()->withErrors(['campaign' => 'Cette campagne n\'est pas en cours d\'envoi.']);
        }

        MessageLog::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $stats = $campaign->stats();
        $campaign->update([
            'sent_count'   => $stats['sent'],
            'failed_count' => $stats['failed'],
            'sent_at'      => now(),
        ]);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Envoi annulé.');
    }

    /** Envoie un email de test (immédiat, sans passer par la file d'attente). */
    public function test(Request $request, Campaign $campaign)
    {
        abort_unless($campaign->channel === 'email', 404);

        $data = $request->validate(['test_email' => 'required|email']);

        try {
            $fake = new Contact(['name' => 'Test', 'email' => $data['test_email']]);
            Mail::to($data['test_email'])->send(new CampaignMail($campaign, $fake));
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['test_email' => 'Échec de l\'envoi du test : ' . $e->getMessage()]);
        }

        return back()->with('success', 'Email de test envoyé à ' . $data['test_email'] . '.');
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->status === 'sending') {
            return back()->withErrors(['campaign' => 'Annulez d\'abord l\'envoi en cours.']);
        }

        $campaign->logs()->delete();
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campagne supprimée.');
    }
}