<?php

namespace App\Http\Controllers;

use App\Models\DeliveryFee;
use App\Models\DeliveryShareSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryFeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $fees = DeliveryFee::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('quartier', 'like', "%{$q}%")
                  ->orWhere('lieu_precis', 'like', "%{$q}%");
            }))
            ->orderBy('quartier')->orderBy('lieu_precis')
            ->paginate(20)->withQueryString();

        $share = DeliveryShareSetting::current();

        return view('livraisons.index', [
            'fees'     => $fees,
            'q'        => $q,
            'share'    => $share,
            'shareUrl' => route('livraisons.public', $share->token),
            'editing'  => null,
        ]);
    }

    public function edit(DeliveryFee $livraison, Request $request)
    {
        $share = DeliveryShareSetting::current();

        return view('livraisons.index', [
            'fees'     => DeliveryFee::orderBy('quartier')->orderBy('lieu_precis')->paginate(20),
            'q'        => '',
            'share'    => $share,
            'shareUrl' => route('livraisons.public', $share->token),
            'editing'  => $livraison,
        ]);
    }

    public function store(Request $request)
    {
        DeliveryFee::create($this->validated($request));

        return redirect()->route('livraisons.index')->with('success', 'Frais de livraison ajouté.');
    }

    public function update(Request $request, DeliveryFee $livraison)
    {
        $livraison->update($this->validated($request));

        return redirect()->route('livraisons.index')->with('success', 'Frais de livraison modifié.');
    }

    public function destroy(DeliveryFee $livraison)
    {
        $livraison->delete();

        return back()->with('success', 'Frais de livraison supprimé.');
    }

    // Active / désactive le lien public
    public function toggleShare()
    {
        $share = DeliveryShareSetting::current();
        $share->update(['actif' => ! $share->actif]);

        return back()->with('success', $share->actif ? 'Lien public activé.' : 'Lien public désactivé.');
    }

    // Génère un nouveau lien (l'ancien ne fonctionne plus)
    public function regenerateShare()
    {
        DeliveryShareSetting::current()->update(['token' => Str::random(40), 'actif' => true]);

        return back()->with('success', 'Nouveau lien généré. L\'ancien lien ne fonctionne plus.');
    }

    // Page PUBLIQUE (sans connexion) pour les commerciaux
    public function publicPage(string $token)
    {
        $share = DeliveryShareSetting::where('token', $token)->where('actif', true)->first();
        abort_if(! $share, 404);

        $fees = DeliveryFee::where('actif', true)
            ->orderBy('quartier')->orderBy('lieu_precis')
            ->get(['quartier', 'lieu_precis', 'frais', 'note']);

        return view('livraisons.public', compact('fees'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'quartier'    => ['required', 'string', 'max:150'],
            'lieu_precis' => ['nullable', 'string', 'max:255'],
            'frais'       => ['required', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);
        $data['actif'] = $request->boolean('actif');

        return $data;
    }
}