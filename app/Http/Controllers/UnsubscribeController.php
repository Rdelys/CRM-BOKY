<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /** Lien signé reçu dans les emails. GET = page de confirmation, POST = "one-click" des clients mail. */
    public function __invoke(Request $request, int $id)
    {
        $contact = Contact::find($id);

        if ($contact && !$contact->unsubscribed) {
            $contact->update(['unsubscribed' => true]);
        }

        if ($request->isMethod('post')) {
            return response('', 200);
        }

        return view('unsubscribe', ['name' => $contact?->name]);
    }
}