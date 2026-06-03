@extends('layouts.app')

@section('page_title', 'Live Logs')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2 shadow-sm">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.single-email') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Single Email Report
            </a>
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Campaign Report
            </a>
            <a href="{{ route('reports.warmup') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Warmup Report
            </a>
            <a href="{{ route('reports.smtp') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                SMTP Report
            </a>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white border border-indigo-600">
                Live Logs
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.live-logs') }}" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 xl:grid-cols-8 gap-3">
            <div>
                <label for="date_range" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="today" {{ ($filters['date_range'] ?? '7d') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="7d" {{ ($filters['date_range'] ?? '7d') === '7d' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30d" {{ ($filters['date_range'] ?? '7d') === '30d' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="custom" {{ ($filters['date_range'] ?? '7d') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="queued" {{ ($filters['status'] ?? '') === 'queued' ? 'selected' : '' }}>Queued</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div>
                <label for="type" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Email Type</label>
                <select id="type" name="type" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all" {{ ($filters['type'] ?? 'all') === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="campaign" {{ ($filters['type'] ?? '') === 'campaign' ? 'selected' : '' }}>Campaign</option>
                    <option value="single" {{ ($filters['type'] ?? '') === 'single' ? 'selected' : '' }}>Single Email</option>
                    <option value="test" {{ ($filters['type'] ?? '') === 'test' ? 'selected' : '' }}>Test Email</option>
                </select>
            </div>
            <div>
                <label for="campaign_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Campaign</label>
                <select id="campaign_id" name="campaign_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All campaigns</option>
                    @foreach($campaignOptions as $campaign)
                        <option value="{{ $campaign->id }}" {{ (int) ($filters['campaign_id'] ?? 0) === (int) $campaign->id ? 'selected' : '' }}>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="smtp_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">SMTP</label>
                <select id="smtp_id" name="smtp_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All SMTP</option>
                    @foreach($smtpOptions as $smtp)
                        <option value="{{ $smtp->id }}" {{ (int) ($filters['smtp_id'] ?? 0) === (int) $smtp->id ? 'selected' : '' }}>
                            {{ $smtp->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="recipient" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Recipient</label>
                <input id="recipient" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="search@email.com" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2">Apply Filters</button>
            <a href="{{ route('reports.live-logs') }}" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm px-4 py-2">Reset</a>
            <a href="{{ route('reports.export', array_filter(['type' => 'live-logs', 'date_range' => $filters['date_range'] ?? '7d', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'status' => $filters['status'] ?? null, 'email_type' => $filters['type'] ?? null, 'campaign_id' => $filters['campaign_id'] ?? null, 'smtp_id' => $filters['smtp_id'] ?? null, 'recipient' => $filters['recipient'] ?? null])) }}"
               class="inline-flex items-center rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                ↓ Export CSV (Detailed)
            </a>
            <button type="button" id="toggle-refresh" class="inline-flex items-center rounded-lg border border-sky-300 text-sky-700 hover:bg-sky-50 dark:border-sky-700 dark:text-sky-300 dark:hover:bg-sky-900/30 text-sm px-4 py-2">
                Auto Refresh: ON (10s)
            </button>
        </div>
    </form>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-saas-stat-card title="Total Logs" :value="$summary['total'] ?? 0" />
        <x-saas-stat-card title="Queued / Pending" :value="$summary['queued'] ?? 0" />
        <x-saas-stat-card title="Sent" :value="$summary['sent'] ?? 0" />
        <x-saas-stat-card title="Failed" :value="$summary['failed'] ?? 0" />
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Sending Live Logs</h3>
            <span class="text-xs text-slate-400 dark:text-slate-500">Showing all email types: Campaign, Single, Test</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase tracking-wide">
                        <th class="py-2 pr-3 whitespace-nowrap">Time</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Recipient</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Subject</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Type</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Campaign</th>
                        <th class="py-2 pr-3 whitespace-nowrap">SMTP Used</th>
                        <th class="py-2 pr-3 whitespace-nowrap">From</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Att.</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Status</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Error</th>
                        <th class="py-2 pr-3 whitespace-nowrap">View</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="py-2 pr-3 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ $log->sent_at ? $log->sent_at->format('Y-m-d H:i:s') : ($log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '—') }}
                            </td>
                            <td class="py-2 pr-3 whitespace-nowrap font-medium text-slate-800 dark:text-slate-200">{{ $log->email }}</td>
                            <td class="py-2 pr-3 max-w-[220px] truncate text-slate-700 dark:text-slate-300" title="{{ $log->subject }}">{{ $log->subject ?: '—' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                @if($log->type === 'campaign')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Campaign</span>
                                @elseif($log->type === 'single')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">Single</span>
                                @elseif($log->type === 'test')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Test</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($log->type ?? 'N/A') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-slate-600 dark:text-slate-400">{{ $log->campaign_name ?: '—' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                @if($log->smtp_name)
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $log->smtp_name }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 block">{{ $log->smtp_host }} · ID #{{ $log->smtp_id }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 max-w-[200px] truncate text-xs text-slate-600 dark:text-slate-400"
                                title="{{ trim(($log->from_name ?? '') . ' <' . ($log->from_email ?? '') . '>') }}">
                                {{ $log->from_name || $log->from_email ? trim(($log->from_name ?? '') . ' <' . ($log->from_email ?? '') . '>') : '—' }}
                            </td>
                            <td class="py-2 pr-3 text-center">{{ (int) ($log->attempts ?? 0) }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                @if($log->status === 'sent')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">✓ Sent</span>
                                @elseif(in_array($log->status, ['queued', 'pending']))
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ ucfirst($log->status) }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">✗ Failed</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($log->status ?? 'unknown') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 max-w-[300px] truncate text-xs text-rose-600 dark:text-rose-400" title="{{ $log->last_error }}">{{ $log->last_error ?: '—' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                <button type="button"
                                        data-email-id="{{ $log->id }}"
                                        data-subject="{{ e($log->subject) }}"
                                        data-recipient="{{ e($log->email) }}"
                                        onclick="openEmailPreview(this)"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300 transition"
                                        title="View email sent to {{ $log->email }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                        <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-10 text-center text-slate-400 dark:text-slate-500">No logs found for selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>

{{-- Email Preview Modal --}}
<div id="emailPreviewModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target===this) closeEmailPreview()">
    <div class="relative w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-700">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-200 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide font-semibold mb-1">Email Preview</p>
                <p id="previewSubject" class="text-base font-semibold text-slate-900 dark:text-white truncate">—</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    To: <span id="previewRecipient" class="font-medium text-slate-700 dark:text-slate-300">—</span>
                    &nbsp;·&nbsp;
                    From: <span id="previewFrom" class="font-medium text-slate-700 dark:text-slate-300">—</span>
                    &nbsp;·&nbsp;
                    Sent: <span id="previewSentAt" class="font-medium text-slate-700 dark:text-slate-300">—</span>
                </p>
            </div>
            <button onclick="closeEmailPreview()" class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
        </div>
        {{-- Loading state --}}
        <div id="previewLoading" class="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            Loading email content…
        </div>
        {{-- Error state --}}
        <div id="previewError" class="hidden px-6 py-8 text-center text-sm text-rose-600 dark:text-rose-400"></div>
        {{-- Body iframe --}}
        <div id="previewBodyWrap" class="hidden flex-1 overflow-hidden rounded-b-2xl">
            <iframe id="previewIframe" class="w-full h-full min-h-[500px] border-0 rounded-b-2xl bg-white" sandbox="allow-same-origin"></iframe>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Email Preview Modal ──────────────────────────────────────────────────────
const emailPreviewModal   = document.getElementById('emailPreviewModal');
const previewSubject      = document.getElementById('previewSubject');
const previewRecipient    = document.getElementById('previewRecipient');
const previewFrom         = document.getElementById('previewFrom');
const previewSentAt       = document.getElementById('previewSentAt');
const previewLoading      = document.getElementById('previewLoading');
const previewError        = document.getElementById('previewError');
const previewBodyWrap     = document.getElementById('previewBodyWrap');
const previewIframe       = document.getElementById('previewIframe');

function openEmailPreview(btn) {
    const id = btn.dataset.emailId;

    // Reset modal state
    previewSubject.textContent   = btn.dataset.subject || '—';
    previewRecipient.textContent = btn.dataset.recipient || '—';
    previewFrom.textContent      = '—';
    previewSentAt.textContent    = '—';
    previewLoading.classList.remove('hidden');
    previewError.classList.add('hidden');
    previewBodyWrap.classList.add('hidden');
    previewIframe.srcdoc          = '';

    // Show modal
    emailPreviewModal.classList.remove('hidden');
    emailPreviewModal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    // Fetch from showEmail endpoint
    fetch(`/reports/email/${id}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
    .then(data => {
        previewSubject.textContent   = data.subject || '(no subject)';
        previewRecipient.textContent = data.to || '—';
        previewFrom.textContent      = data.from_name
            ? `${data.from_name} <${data.from_email}>`
            : (data.from_email || '—');
        previewSentAt.textContent    = data.sent_at || data.created_at || '—';

        const bodyHtml = data.body_snapshot || data.body || '<p style="padding:24px;color:#888;">No email content stored.</p>';
        previewIframe.srcdoc = bodyHtml;

        previewLoading.classList.add('hidden');
        previewBodyWrap.classList.remove('hidden');
    })
    .catch(err => {
        previewLoading.classList.add('hidden');
        previewError.textContent = 'Could not load email content: ' + err;
        previewError.classList.remove('hidden');
    });
}

function closeEmailPreview() {
    emailPreviewModal.classList.add('hidden');
    emailPreviewModal.classList.remove('flex');
    document.body.style.overflow = '';
    previewIframe.srcdoc = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEmailPreview(); });

// ── Filters + Auto Refresh ───────────────────────────────────────────────────
(function () {
    const dateRange = document.getElementById('date_range');
    const fromInput = document.getElementById('from');
    const toInput = document.getElementById('to');
    const toggleBtn = document.getElementById('toggle-refresh');

    function syncCustomDate() {
        const isCustom = dateRange?.value === 'custom';
        if (fromInput) fromInput.disabled = !isCustom;
        if (toInput) toInput.disabled = !isCustom;
    }

    let autoRefresh = true;
    let timer = setInterval(() => {
        if (autoRefresh) {
            window.location.reload();
        }
    }, 10000);

    toggleBtn?.addEventListener('click', function () {
        autoRefresh = !autoRefresh;
        this.textContent = autoRefresh ? 'Auto Refresh: ON (10s)' : 'Auto Refresh: OFF';
        this.classList.toggle('border-sky-300', autoRefresh);
        this.classList.toggle('text-sky-700', autoRefresh);
        this.classList.toggle('border-slate-300', !autoRefresh);
        this.classList.toggle('text-slate-600', !autoRefresh);
    });

    dateRange?.addEventListener('change', syncCustomDate);
    syncCustomDate();
})(); // end filters IIFE
</script>
@endpush
