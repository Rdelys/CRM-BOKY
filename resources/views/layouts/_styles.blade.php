<script src="https://cdn.tailwindcss.com"></script>
<style type="text/tailwindcss">
    @layer components {
        .card { @apply bg-white rounded-xl border border-slate-200 shadow-sm; }
        .label { @apply block text-sm font-medium text-slate-700 mb-1.5; }
        .input { @apply w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20; }
        .btn-primary { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/40; }
        .btn-dark { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-black focus:outline-none focus:ring-2 focus:ring-slate-900/40; }
        .btn-outline { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50; }
        .th { @apply px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500; }
        .td { @apply px-5 py-3.5 text-sm text-slate-700; }
    }
</style>
