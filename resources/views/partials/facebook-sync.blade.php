{{-- Bouton de synchro Facebook + toast dynamique en haut de l'écran --}}
<button type="button" id="fb-sync-btn" class="btn-outline inline-flex items-center gap-2">
    <svg id="fb-sync-spin" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <path d="M21 12a9 9 0 11-6.2-8.55"/>
    </svg>
    <svg id="fb-sync-icon" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12a9 9 0 01-15.5 6.2M3 12A9 9 0 0118.5 5.8"/><path d="M18.5 2v4h-4M5.5 22v-4h4"/>
    </svg>
    <span id="fb-sync-label">Synchroniser Facebook</span>
</button>

<div id="fb-toast"
     class="pointer-events-none fixed inset-x-0 top-4 z-50 flex -translate-y-24 justify-center px-4 opacity-0 transition duration-300"
     role="status" aria-live="polite">
    <div id="fb-toast-box" class="pointer-events-auto w-full max-w-md rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-900 shadow-xl">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div id="fb-toast-title" class="text-sm font-bold"></div>
                <div id="fb-toast-main" class="mt-0.5 text-2xl font-bold leading-tight"></div>
                <div id="fb-toast-sub" class="mt-1 text-xs opacity-80"></div>
                <button type="button" id="fb-toast-reload" class="mt-2 hidden text-xs font-semibold underline underline-offset-2">
                    Actualiser la page
                </button>
            </div>
            <button type="button" id="fb-toast-close" class="shrink-0 text-lg leading-none opacity-50 transition hover:opacity-100" aria-label="Fermer">&times;</button>
        </div>
    </div>
</div>

<script>
(function () {
    const START_URL  = @json(route('facebook.sync'));
    const STATUS_URL = @json(route('facebook.sync.status'));
    const TOKEN      = @json(csrf_token());

    const $ = id => document.getElementById(id);
    const btn = $('fb-sync-btn'), label = $('fb-sync-label'), spin = $('fb-sync-spin'), icon = $('fb-sync-icon');
    const toast = $('fb-toast'), box = $('fb-toast-box');
    const title = $('fb-toast-title'), main = $('fb-toast-main'), sub = $('fb-toast-sub');
    const reloadBtn = $('fb-toast-reload'), closeBtn = $('fb-toast-close');

    const BASE = 'pointer-events-auto w-full max-w-md rounded-xl border p-4 shadow-xl ';
    const THEME = {
        running: 'border-blue-200 bg-blue-50 text-blue-900',
        done:    'border-emerald-200 bg-emerald-50 text-emerald-900',
        failed:  'border-red-200 bg-red-50 text-red-900',
    };

    const nf = new Intl.NumberFormat('fr-FR');
    const plural = (n, one, many) => nf.format(n) + ' ' + (n > 1 ? many : one);

    let timer = null, hideTimer = null, startedPolling = 0;

    function showToast() {
        clearTimeout(hideTimer);
        toast.classList.remove('-translate-y-24', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    }

    function hideToast() {
        toast.classList.add('-translate-y-24', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }

    function setBusy(busy) {
        btn.disabled = busy;
        btn.classList.toggle('opacity-60', busy);
        btn.classList.toggle('cursor-not-allowed', busy);
        spin.classList.toggle('hidden', !busy);
        icon.classList.toggle('hidden', busy);
        label.textContent = busy ? 'Synchronisation…' : 'Synchroniser Facebook';
    }

    function render(s) {
        const ok = (s.created || 0) + (s.updated || 0);
        const details = plural(s.created || 0, 'nouveau contact', 'nouveaux contacts')
            + ' · ' + plural(s.updated || 0, 'mis à jour', 'mis à jour');

        reloadBtn.classList.add('hidden');

        if (s.state === 'queued' || s.state === 'running') {
            box.className = BASE + THEME.running;
            title.textContent = 'Synchronisation Facebook en cours…';
            main.textContent = s.state === 'queued' ? 'En attente du démarrage' : plural(ok, 'contact traité', 'contacts traités');
            sub.textContent = s.state === 'queued'
                ? 'La tâche est dans la file d’attente.'
                : details + ' · page ' + (s.pages || 0);
            setBusy(true);
            showToast();
            return;
        }

        setBusy(false);

        if (s.state === 'done') {
            box.className = BASE + THEME.done;
            title.textContent = s.complete === false ? 'Synchronisation Facebook partielle' : 'Synchronisation Facebook terminée';
            main.textContent = plural(ok, 'contact synchronisé', 'contacts synchronisés');
            let extra = details;
            if (s.errors) extra += ' · ' + plural(s.errors, 'erreur', 'erreurs');
            if (s.complete === false) extra += ' · arrêt de sécurité, relancez pour continuer';
            sub.textContent = extra;
            reloadBtn.classList.remove('hidden');
            showToast();
            hideTimer = setTimeout(hideToast, 15000);
            return;
        }

        if (s.state === 'failed') {
            box.className = BASE + THEME.failed;
            title.textContent = 'Échec de la synchronisation Facebook';
            main.textContent = ok > 0 ? plural(ok, 'contact synchronisé avant l’arrêt', 'contacts synchronisés avant l’arrêt') : 'Aucun contact synchronisé';
            sub.textContent = s.message || 'Une erreur est survenue.';
            showToast();
        }
    }

    function stopPolling() {
        clearInterval(timer);
        timer = null;
    }

    function startPolling() {
        if (timer) return;
        startedPolling = Date.now();

        timer = setInterval(async () => {
            // Sécurité : on arrête de questionner le serveur après 12 minutes
            if (Date.now() - startedPolling > 12 * 60 * 1000) {
                stopPolling();
                render({ state: 'failed', message: 'Pas de réponse du serveur. Vérifiez que le worker de file d’attente tourne.' });
                return;
            }

            try {
                const res = await fetch(STATUS_URL, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const s = await res.json();
                if (s.state === 'idle') return;

                render(s);
                if (s.state === 'done' || s.state === 'failed') stopPolling();
            } catch (e) { /* réseau instable : on réessaie au prochain tour */ }
        }, 2000);
    }

    btn.addEventListener('click', async () => {
        setBusy(true);

        try {
            const res = await fetch(START_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            // 202 = lancée ; 409 = une synchro tourne déjà : on s'y rattache
            if (res.status === 202 || res.status === 409) {
                render(await res.json());
                startPolling();
                return;
            }

            throw new Error('HTTP ' + res.status);
        } catch (e) {
            render({ state: 'failed', message: 'Impossible de lancer la synchronisation (' + e.message + ').' });
        }
    });

    closeBtn.addEventListener('click', hideToast);
    reloadBtn.addEventListener('click', () => location.reload());

    // Si une synchro est déjà en cours à l'ouverture de la page, on affiche son avancement
    (async function resume() {
        try {
            const res = await fetch(STATUS_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const s = await res.json();
            const fresh = s.updated_at && (Date.now() - new Date(s.updated_at).getTime()) < 10 * 60 * 1000;
            if ((s.state === 'queued' || s.state === 'running') && fresh) {
                render(s);
                startPolling();
            }
        } catch (e) { /* ignoré */ }
    })();
})();
</script>