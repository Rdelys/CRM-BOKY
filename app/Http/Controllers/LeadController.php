<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reçoit les prospects depuis le siteweb (formulaire HTML ou fetch),
 * ou depuis Facebook via Zapier/Make (même endpoint).
 */
class LeadController extends Controller
{
    public function store(Request $request)
    {
        $expected = (string) config('crm.lead_token');
        abort_unless($expected !== '' && hash_equals($expected, (string) $request->input('token')), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'phone'    => 'nullable|string|max:30|required_without:email',
            'email'    => 'nullable|email|max:150|required_without:phone',
            'source'   => ['nullable', Rule::in(array_keys(Contact::SOURCES))],
            'message'  => 'nullable|string|max:1000',
            'redirect' => 'nullable|url',
        ]);

        $phone = Contact::normalizePhone($data['phone'] ?? null);
        $email = !empty($data['email']) ? strtolower($data['email']) : null;

        $contact = Contact::query()
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        $note = !empty($data['message'])
            ? '[' . now()->format('d/m/Y H:i') . '] ' . $data['message']
            : null;

        if ($contact) {
            $contact->update([
                'phone' => $contact->phone ?: $phone,
                'email' => $contact->email ?: $email,
                'notes' => trim($contact->notes . "\n" . $note),
            ]);
        } else {
            Contact::create([
                'name'   => $data['name'],
                'phone'  => $phone,
                'email'  => $email,
                'source' => $data['source'] ?? 'website',
                'status' => 'prospect',
                'notes'  => $note,
            ]);
        }

        if (!empty($data['redirect'])) {
            return redirect()->away($data['redirect']);
        }

        return response()
            ->json(['ok' => true])
            ->header('Access-Control-Allow-Origin', '*');
    }
}
