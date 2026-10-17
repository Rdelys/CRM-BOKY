<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $stats = [
            'contacts'  => Contact::count(),
            'prospects' => Contact::where('status', 'prospect')->count(),
            'clients'   => Contact::where('status', 'client')->count(),
            'campaigns' => Campaign::where('status', 'sent')->count(),
        ];

        $bySource = Contact::select('source', DB::raw('count(*) as total'))
            ->groupBy('source')->orderByDesc('total')->pluck('total', 'source');

        $latestContacts  = Contact::latest()->limit(6)->get();
        $latestCampaigns = Campaign::latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'bySource', 'latestContacts', 'latestCampaigns'));
    }
}