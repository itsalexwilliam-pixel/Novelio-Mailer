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

        {{-- Waiting in queue notice --}}
        <div id="queuedNotice" class="{{ in_array($importRun->status, ['queued']) ? '' : 'hidden' }} mt-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 p-3 text-sm text-amber-800 dark:text-amber-200 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            Waiting in queue… The background worker will start processing shortly.
        </div>

        {{-- Stale / stuck notice — shown by JS when no progress for 90 s --}}
        <div id="staleNotice" class="hidden mt-4 rounded-xl border border-orange-300 bg-orange-50 dark:bg-orange-950/30 dark:border-orange-700 p-3 text-sm text-orange-800 dark:text-orange-200">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                    <p class="font-semibold">Import appears to be stuck</p>
                    <p class="mt-0.5">No progress has been made for over 90 seconds. The background worker may have crashed. Click <strong>Force Retry</strong> to reset and re-queue this import.</p>
                </div>
            </div>
        </div>

        {{-- Error box (shown on failure) --}}
        <div id="errorBox" class="hidden mt-4 rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-800 p-3 text-sm text-rose-700 dark:text-rose-300"></div>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
        <a href="{{ route('import.index') }}"
           class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition">
            Back to Import
        </a>

        {{-- Shown by JS on failure OR when stuck --}}
        <form id="retryForm" method="POST" action="{{ route('import.reprocess', $importRun) }}" class="hidden">
            @csrf
            <button type="submit"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                </svg>
                <span id="retryLabel">Reprocess Now</span>
            </button>
        </form>

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
    const statusUrl          = @json(route('import.status', $importRun));
    const resultLink         = document.getElementById('resultLink');
    const progressBar        = document.getElementById('progressBar');
    const progressPercentText= document.getElementById('progressPercentText');
    const totalRows          = document.getElementById('totalRows');
    const processedRows      = document.getElementById('processedRows');
    const importedRows       = document.getElementById('importedRows');
    const remainingRows      = document.getElementById('remainingRows');
    const skippedRows        = document.getElementById('skippedRows');
    const statusText         = document.getElementById('statusText');
    const errorBox           = document.getElementById('errorBox');
    const queuedNotice       = document.getElementById('queuedNotice');
    const staleNotice        = document.getElementById('staleNotice');
    const retryForm          = document.getElementById('retryForm');
    const retryLabel         = document.getElementById('retryLabel');

    let done = false;

    // Stale detection — track the last time the processed count changed
    let lastProcessedCount   = -1;
    let lastProgressAt       = Date.now();
    const STALE_THRESHOLD_MS = 90_000; // 90 seconds with no progress → show warning

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

            const pct = data.progress_percent || 0;
            progressBar.style.width        = `${pct}%`;
            progressPercentText.textContent = `${pct}%`;
            totalRows.textContent           = String(data.total     ?? 0);
            processedRows.textContent       = String(data.processed ?? 0);
            importedRows.textContent        = String(data.imported  ?? 0);
            remainingRows.textContent       = String(data.remaining ?? 0);
            skippedRows.textContent         = String(data.skipped   ?? 0);
            statusText.textContent          = String(data.status    ?? 'queued');

            // Hide "waiting in queue" notice once processing starts
            if (data.status !== 'queued' && queuedNotice) {
                queuedNotice.classList.add('hidden');
            }

            // ── Stale detection ───────────────────────────────────────────────
            const currentProcessed = data.processed ?? 0;
            if (currentProcessed !== lastProcessedCount) {
                // Progress moved — reset the stale timer
                lastProcessedCount = currentProcessed;
                lastProgressAt     = Date.now();
                staleNotice.classList.add('hidden');
            } else if (
                data.status === 'processing' &&
                (Date.now() - lastProgressAt) > STALE_THRESHOLD_MS
            ) {
                // No change for 90 s while supposedly processing → stuck
                staleNotice.classList.remove('hidden');
                retryLabel.textContent = 'Force Retry';
                retryForm.classList.remove('hidden');
            }
            // ─────────────────────────────────────────────────────────────────

            if (data.status === 'failed') {
                done = true;
                errorBox.classList.remove('hidden');
                errorBox.textContent = data.error_message || 'Import failed due to an unexpected error.';
                resultLink.classList.remove('hidden');
                resultLink.href = data.result_url;
                retryLabel.textContent = 'Reprocess Now';
                retryForm.classList.remove('hidden');
                staleNotice.classList.add('hidden');
                return;
            }

            if (data.finished) {
                done = true;
                progressBar.style.width        = '100%';
                progressPercentText.textContent = '100%';
                staleNotice.classList.add('hidden');
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
