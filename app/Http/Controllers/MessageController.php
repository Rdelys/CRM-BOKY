<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\SmsMessage;
use App\Services\MapiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MessageController extends Controller
{
    public function index(Request $request, MapiService $mapi)
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $stats = SmsMessage::selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN statut = 'sent' THEN 1 ELSE 0 END) AS envoyes,
            SUM(CASE WHEN statut = 'failed' THEN 1 ELSE 0 END) AS echecs,
            SUM(CASE WHEN statut = 'sent' THEN nb_sms ELSE 0 END) AS unites
        ")->first();

        $aujourdhui = SmsMessage::whereDate('created_at', today())->count();
        $solde      = Cache::remember('mapi.solde', 60, fn () => $mapi->balance());

        $messages = SmsMessage::with(['contact:id,name', 'user:id,name'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('origine'), fn ($q) => $q->where('origine', $request->origine))
            ->when($request->filled('du'), fn ($q) => $q->whereDate('created_at', '>=', $request->du))
            ->when($request->filled('au'), fn ($q) => $q->whereDate('created_at', '<=', $request->au))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('nom', 'like', $t)
                    ->orWhere('telephone', 'like', $t)
                    ->orWhere('message', 'like', $t));
            })
            ->latest()->paginate(20)->withQueryString();

        return view('messages.index', compact('messages', 'stats', 'aujourdhui', 'solde'));
    }

    public function send(Request $request, MapiService $mapi)
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'message'    => ['required', 'string', 'max:640'],
            'origine'    => ['required', 'in:contacts,classement'],
        ], [
            'message.required' => 'Le message est vide.',
            'message.max'      => 'Le message est trop long (640 caractères maximum).',
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        if ($contact->unsubscribed) {
            return back()->withErrors(['sms' => 'Ce contact s\'est désinscrit : aucun message envoyé.']);
        }

        $phone = MapiService::normalize($contact->phone);
        if (! $phone) {
            return back()->withErrors(['sms' => 'Numéro de téléphone absent ou invalide pour ' . $contact->name . '.']);
        }

        $message = trim($data['message']);
        $result  = $mapi->send($phone, $message);

        SmsMessage::create([
            'contact_id' => $contact->id,
            'user_id'    => $request->user()?->id,
            'nom'        => $contact->name,
            'telephone'  => $phone,
            'message'    => $message,
            'origine'    => $data['origine'],
            'statut'     => $result['ok'] ? 'sent' : 'failed',
            'erreur'     => $result['error'],
            'nb_sms'     => max(1, MapiService::countSms($message)),
        ]);

        Cache::forget('mapi.solde');

        return $result['ok']
            ? back()->with('success', 'SMS envoyé à ' . $contact->name . '.')
            : back()->withErrors(['sms' => 'Échec de l\'envoi : ' . $result['error']]);
    }

    public function resend(SmsMessage $message, MapiService $mapi)
    {
        abort_unless($message->statut === 'failed', 404);

        if ($message->contact?->unsubscribed) {
            return back()->withErrors(['sms' => 'Ce contact s\'est désinscrit : message non renvoyé.']);
        }

        $result = $mapi->send($message->telephone, $message->message);

        $message->update([
            'statut' => $result['ok'] ? 'sent' : 'failed',
            'erreur' => $result['error'],
        ]);

        Cache::forget('mapi.solde');

        return $result['ok']
            ? back()->with('success', 'SMS renvoyé.')
            : back()->withErrors(['sms' => 'Échec du renvoi : ' . $result['error']]);
    }
}