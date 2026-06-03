@extends('layouts.app')

@section('page_title', 'Import Progress')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">CSV Import in Progress</h2>
        <p class="text-sm text-slate-500 mt-1">
            File: <span class="font-medium">{{ $importRun->original_filename ?: 'uploaded.csv' }}</span>
        </p>
    </div>

    <div class="rounded-2xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/70 dark:bg-indigo-950/30 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-indigo-900 dark:text-indigo-200">Processing status</span>
            <span id="progressPercentText" class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">0%</span>
        </div>

        <div class="w-full h-3 rounded-full bg-indigo-100 dark:bg-indigo-900/40 overflow-hidden">
            <div id="progressBar" class="h-3 bg-indigo-600 transition-all duration-300" style="width:0%"></div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
            <div class="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 border border-slate-200 dark:border-slate-700">
                <div class="text-slate-500">Total</div>
                <div id="totalRows" class="text-lg font-semibold text-slate-900 dark:text-white">0</div>
            </div>
            <div class="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 border border-slate-200 dark:border-slate-700">
                <div class="text-slate-500">Processed</div>
                <div id="processedRows" class="text-lg font-semibold text-slate-900 dark:text-white">0</div>
            </div>
            <div class="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 border border-slate-200 dark:border-slate-700">
                <div class="text-slate-500">Imported</div>
                <div id="importedRows" class="text-lg font-semibold text-emerald-600">0</div>
            </div>
            <div class="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 border border-slate-200 dark:border-slate-700">
                <div class="text-slate-500">Remaining</div>
                <div id="remainingRows" class="text-lg font-semibold text-amber-600">0</div>
            </div>
        </div>

        <div class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            <span class="font-medium">Skipped:</span> <span id="skippedRows">0</span>
            <span class="mx-2">•</span>
            <span class="font-medium">Status:</span> <span id="statusText">{{ $importRun->status }}</span>
        </div>

        <div id="errorBox" class="hidden mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></div>
    </div>

    <div class="flex items-center gap-3">
        <a href="{{ route('import.index') }}"
           class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition">
            Back to Import
        </a>

        @if (in_array($importRun->status, ['queued', 'failed']))
        <form id="retryForm" method="POST" action="{{ route('import.reprocess', $importRun) }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                </svg>
                Reprocess Now
            </button>
        </form>
        @endif

        <a id="resultLink" href="{{ route('import.result.run', $importRun) }}"
           class="hidden inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
            View Result
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const statusUrl = @json(route('import.status', $importRun));
    const resultLink = document.getElementById('resultLink');
    const progressBar = document.getElementById('progressBar');
    const progressPercentText = document.getElementById('progressPercentText');
    const totalRows = document.getElementById('totalRows');
    const processedRows = document.getElementById('processedRows');
    const importedRows = document.getElementById('importedRows');
    const remainingRows = document.getElementById('remainingRows');
    const skippedRows = document.getElementById('skippedRows');
    const statusText = document.getElementById('statusText');
    const errorBox = document.getElementById('errorBox');

    let done = false;

    async function pollStatus() {
        if (done) return;

        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch import status.');
            }

            const data = await response.json();

            progressBar.style.width = `${data.progress_percent || 0}%`;
            progressPercentText.textContent = `${data.progress_percent || 0}%`;
            totalRows.textContent = String(data.total ?? 0);
            processedRows.textContent = String(data.processed ?? 0);
            importedRows.textContent = String(data.imported ?? 0);
            remainingRows.textContent = String(data.remaining ?? 0);
            skippedRows.textContent = String(data.skipped ?? 0);
            statusText.textContent = String(data.status ?? 'queued');

            if (data.status === 'failed') {
                done = true;
                errorBox.classList.remove('hidden');
                errorBox.textContent = data.error_message || 'Import failed due to an unexpected error.';
                resultLink.classList.remove('hidden');
                resultLink.href = data.result_url;
                return;
            }

            if (data.finished) {
                done = true;
                progressBar.style.width = '100%';
                progressPercentText.textContent = '100%';
                resultLink.classList.remove('hidden');
                resultLink.href = data.result_url;
                window.location.href = data.result_url;
                return;
            }

            setTimeout(pollStatus, 1200);
        } catch (error) {
            setTimeout(pollStatus, 2000);
        }
    }

    pollStatus();
})();
</script>
@endpush
