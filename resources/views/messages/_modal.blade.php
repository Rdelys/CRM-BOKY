<div id="sms-modal" data-avis-url="{{ route('satisfactions.public', ['src' => 'sms']) }}"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
    <div class="card max-h-[90vh] w-full max-w-lg overflow-y-auto p-5 sm:p-6">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Envoyer un SMS</h2>
                <p class="text-sm text-slate-500">
                    À <span id="sms-to" class="font-semibold text-slate-700"></span>
                    · <span id="sms-phone"></span>
                </p>
            </div>
            <button type="button" data-sms-close class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fermer">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <form id="sms-form" method="POST" action="{{ route('messages.send') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="contact_id" id="sms-contact">
            <input type="hidden" name="origine" id="sms-origine">

            <div>
                <label for="sms-text" class="label">Message</label>
                <textarea id="sms-text" name="message" rows="6" maxlength="640" required class="input"></textarea>
                <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span id="sms-count"></span>
                    <button type="button" id="sms-avis" class="font-medium text-blue-600 hover:text-blue-800">+ Ajouter le lien d'avis</button>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                <button type="button" data-sms-close class="btn-outline">Annuler</button>
                <button type="submit" id="sms-submit" class="btn-primary">Envoyer le SMS</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('sms-modal');
    const form = document.getElementById('sms-form');
    const ta = document.getElementById('sms-text');
    const count = document.getElementById('sms-count');
    const submit = document.getElementById('sms-submit');
    const nonGsm = /[^\x20-\x7E\n\r€£¥èéùìòÇØøÅåÆæßÉÄÖÑÜ§¿äöñüà]/u;

    function updateCount() {
        const t = ta.value, len = [...t].length, uni = nonGsm.test(t);
        const single = uni ? 70 : 160, multi = uni ? 67 : 153;
        const n = len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi));
        count.textContent = len + ' caractère(s) · ' + n + ' SMS' + (uni ? ' (accents ou emoji spéciaux)' : '');
    }
    function openModal(btn) {
        document.getElementById('sms-contact').value = btn.dataset.contactId;
        document.getElementById('sms-origine').value = btn.dataset.origine;
        document.getElementById('sms-to').textContent = btn.dataset.name;
        document.getElementById('sms-phone').textContent = btn.dataset.phone;
        ta.value = btn.dataset.message || '';
        updateCount();
        modal.classList.remove('hidden'); modal.classList.add('flex');
        ta.focus();
    }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    document.addEventListener('click', function (e) {
        const open = e.target.closest('[data-sms-open]');
        if (open) { openModal(open); return; }
        if (e.target.closest('[data-sms-close]') || e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    ta.addEventListener('input', updateCount);

    document.getElementById('sms-avis').addEventListener('click', function () {
        ta.value = ta.value.trim() + (ta.value.trim() ? '\n' : '') + "Donnez-nous votre avis : " + modal.dataset.avisUrl;
        updateCount(); ta.focus();
    });

    // Évite le double envoi
    form.addEventListener('submit', function () { submit.disabled = true; submit.textContent = 'Envoi…'; });
    window.addEventListener('pageshow', function () { submit.disabled = false; submit.textContent = 'Envoyer le SMS'; });
})();
</script>