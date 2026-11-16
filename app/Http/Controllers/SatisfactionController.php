<?php

namespace App\Http\Controllers;

use App\Models\Satisfaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SatisfactionController extends Controller
{
    private function cleanSource(?string $value): string
    {
        $v = Str::of((string) $value)->lower()
            ->replaceMatches('/[^a-z0-9_-]/', '')
            ->limit(30, '')
            ->toString();

        return $v !== '' ? $v : 'direct';
    }

    /* ---------- Public ---------- */

    public function form(Request $request)
    {
        return view('satisfactions.public', [
            'source' => $this->cleanSource($request->query('src')),
            'notes'  => Satisfaction::NOTES,
        ]);
    }

    public function store(Request $request)
    {
        // Champ piège anti-robots
        if ($request->filled('website')) {
            return redirect()->route('satisfactions.thanks');
        }

        $data = $request->validate([
            'nom'         => ['required', 'string', 'max:120'],
            'livre'       => ['nullable', 'string', 'max:190'],
            'note'        => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'source'      => ['nullable', 'string', 'max:30'],
        ], [
            'nom.required'  => 'Merci d\'indiquer votre nom.',
            'note.required' => 'Merci de choisir une note.',
        ]);

        Satisfaction::create([
            'nom'         => $data['nom'],
            'livre'       => $data['livre'] ?? null,
            'note'        => $data['note'],
            'commentaire' => $data['commentaire'] ?? null,
            'source'      => $this->cleanSource($data['source'] ?? null),
        ]);

        return redirect()->route('satisfactions.thanks');
    }

    public function thanks()
    {
        return view('satisfactions.thanks');
    }

    /* ---------- Admin (CRM) ---------- */

    public function index(Request $request)
    {
        $total      = Satisfaction::count();
        $moyenne    = $total ? round((float) Satisfaction::avg('note'), 1) : 0;
        $satisfaits = Satisfaction::where('note', '>=', 4)->count();
        $taux       = $total ? round($satisfaits / $total * 100) : 0;

        $distribution = Satisfaction::select('note', DB::raw('COUNT(*) as total'))
            ->groupBy('note')->pluck('total', 'note');

        $parSource = Satisfaction::select('source', DB::raw('COUNT(*) as total'), DB::raw('AVG(note) as moyenne'))
            ->groupBy('source')->orderByDesc('total')->get();

        $liste = Satisfaction::query()
            ->when($request->filled('note'), fn ($q) => $q->where('note', (int) $request->note))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('nom', 'like', $t)
                    ->orWhere('livre', 'like', $t)
                    ->orWhere('commentaire', 'like', $t));
            })
            ->latest()->paginate(15)->withQueryString();

        return view('satisfactions.index', [
            'total'        => $total,
            'moyenne'      => $moyenne,
            'taux'         => $taux,
            'distribution' => $distribution,
            'parSource'    => $parSource,
            'liste'        => $liste,
            'notes'        => Satisfaction::NOTES,
            'sources'      => Satisfaction::SOURCES,
        ]);
    }

    public function destroy(Satisfaction $satisfaction)
    {
        $satisfaction->delete();

        return back()->with('success', 'Avis supprimé.');
    }
}