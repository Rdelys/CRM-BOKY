<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Http\Request;
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
        ]);

        $campaign = Campaign::create($data);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campagne créée. Vérifiez les destinataires avant l\'envoi.');
    }

    public function show(Campaign $campaign)
    {
        $recipientCount = $campaign->recipients()->count();
        $logs = $campaign->logs()->with('contact')->latest()->limit(50)->get();

        return view('campaigns.show', compact('campaign', 'recipientCount', 'logs'));
    }

    public function send(Campaign $campaign)
    {
        if ($campaign->status !== 'draft') {
            return back()->withErrors(['campaign' => 'Cette campagne a déjà été envoyée.']);
        }

        if ($campaign->recipients()->count() === 0) {
            return back()->withErrors(['campaign' => 'Aucun destinataire pour cette campagne.']);
        }

        SendCampaignJob::dispatch($campaign);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Envoi lancé.');
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campagne supprimée.');
    }
}
