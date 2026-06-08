<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ImportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Only try once. If the job fails, the import_run status is set to
     * 'failed' by our own catch block, so a queue-level retry would just
     * reset and re-process, which is not what we want.
     */
    public int $tries = 1;

    /**
     * Give the job up to 30 minutes to run before the supervisor kills it.
     * This MUST match the supervisor --timeout value for the queue worker.
     * With the bulk email-lookup approach the job typically completes in
     * under 30 seconds even for large CSV files, but we keep a large safety
     * margin here.
     */
    public int $timeout = 1800;

    /**
     * Maximum number of failed/skipped row details persisted to the DB.
     * Prevents the failed_rows JSON column from growing unbounded on files
     * where thousands of rows are invalid.
     */
    private const MAX_FAILED_ROWS_STORED = 500;

    public function __construct(public int $importRunId)
    {
        // Dispatch to the default queue on the default database connection so
        // the existing supervisor workers (which listen to 'default') pick it
        // up immediately.  With the N+1 query eliminated the job finishes in
        // ~3 s for 3 000 rows — well inside retry_after: 90, so no second
        // worker can ever steal and reset this job.
    }

    public function handle(): void
    {
        // ── Outer safety net ──────────────────────────────────────────────────
        // Wrap EVERYTHING so that no exception can ever escape handle() and
        // cause the queue to mark the job as FAIL.  A queue FAIL leaves the
        // import_run in 'processing' status with no way for the user to
        // recover via the UI.  We prefer to record the error ourselves.
        $importRun = null;
        $handle    = null;

        try {
            $importRun = ImportRun::find($this->importRunId);
            if (!$importRun) {
                return; // import record was deleted — nothing to do
            }

            $importRun->update([
                'status'        => 'processing',
                'started_at'    => now(),
                'error_message' => null,
            ]);

            // ── Locate the uploaded CSV ───────────────────────────────────────
            $relativePath = trim((string) $importRun->stored_path);
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativePath = ltrim($relativePath, '/');

            if (! Storage::disk('local')->exists($relativePath)) {
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'Uploaded CSV file not found on server. Path checked: ' . $relativePath,
                    'finished_at'   => now(),
                ]);
                return;
            }

            $absolutePath = Storage::disk('local')->path($relativePath);

            $handle = fopen($absolutePath, 'r');
            if (!$handle) {
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'Could not open uploaded CSV file.',
                    'finished_at'   => now(),
                ]);
                return;
            }

            // ── 1. Read headers ───────────────────────────────────────────────
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                $handle = null;
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'CSV file appears to be empty (no header row).',
                    'finished_at'   => now(),
                ]);
                return;
            }

            // Strip UTF-8 BOM from first header cell if present
            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
            $headers    = array_map(static fn($h) => trim((string) $h), $headers);

            // ── 2. Resolve column indices ─────────────────────────────────────
            $nameCol         = trim((string) ($importRun->name_column ?? ''));
            $firstNameCol    = trim((string) ($importRun->first_name_column ?? ''));
            $lastNameCol     = trim((string) ($importRun->last_name_column ?? ''));
            $emailCol        = trim((string) ($importRun->email_column ?? 'email'));
            $businessNameCol = trim((string) ($importRun->business_name_column ?? ''));
            $websiteCol      = trim((string) ($importRun->website_column ?? ''));
            $phoneCol        = trim((string) ($importRun->phone_column ?? ''));

            $nameIndex         = $nameCol !== '' ? array_search($nameCol, $headers, true) : false;
            $firstNameIndex    = $firstNameCol !== '' ? array_search($firstNameCol, $headers, true) : false;
            $lastNameIndex     = $lastNameCol !== '' ? array_search($lastNameCol, $headers, true) : false;
            $emailIndex        = array_search($emailCol, $headers, true);
            $businessNameIndex = $businessNameCol !== '' ? array_search($businessNameCol, $headers, true) : false;
            $websiteIndex      = $websiteCol !== '' ? array_search($websiteCol, $headers, true) : false;
            $phoneIndex        = $phoneCol !== '' ? array_search($phoneCol, $headers, true) : false;

            $hasName      = $nameIndex !== false;
            $hasFirstName = $firstNameIndex !== false;

            if (!$hasName && !$hasFirstName) {
                fclose($handle);
                $handle = null;
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'No name column found. Headers detected: ' . implode(', ', $headers),
                    'finished_at'   => now(),
                ]);
                return;
            }

            if ($emailIndex === false) {
                fclose($handle);
                $handle = null;
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'Email column "' . $emailCol . '" not found. Headers detected: ' . implode(', ', $headers),
                    'finished_at'   => now(),
                ]);
                return;
            }

            // ── 3. Read all data rows into memory ─────────────────────────────
            $allRows = [];
            while (($row = fgetcsv($handle)) !== false) {
                // Skip completely blank lines
                if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $allRows[] = $row;
            }

            fclose($handle);
            $handle = null;

            $totalRows = count($allRows);
            $importRun->update([
                'total_rows'     => $totalRows,
                'processed_rows' => 0,
                'imported_rows'  => 0,
                'skipped_rows'   => 0,
                'failed_rows'    => [],
            ]);

            // ── 4. Bulk-load existing emails (eliminates N+1 per-row query) ───
            $accountId = (int) ($importRun->account_id ?? 0);
            $groupIds  = collect($importRun->group_ids ?? [])
                ->map(fn($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            // Collect every non-empty email from the file in a single pass
            $allFileEmails = [];
            foreach ($allRows as $row) {
                $e = strtolower(trim((string) ($row[$emailIndex] ?? '')));
                if ($e !== '') {
                    $allFileEmails[] = $e;
                }
            }

            // One bulk SELECT instead of one query per row (kills N+1 problem)
            $existingEmails = [];
            foreach (array_chunk($allFileEmails, 500) as $chunk) {
                $found = Contact::where('account_id', $accountId)
                    ->whereIn('email', $chunk)
                    ->pluck('email')
                    ->all();
                foreach ($found as $e) {
                    $existingEmails[$e] = true;
                }
            }

            // ── 5. Process rows ───────────────────────────────────────────────
            $seenInFile = [];
            $failedRows = [];
            $processed  = 0;
            $imported   = 0;
            $skipped    = 0;
            $rowNumber  = 1; // header is row 1

            foreach ($allRows as $row) {
                $rowNumber++;
                $processed++;
                $reasons = [];

                // Resolve name
                if ($hasName) {
                    $name = trim((string) ($row[$nameIndex] ?? ''));
                } else {
                    $firstName = trim((string) ($row[$firstNameIndex] ?? ''));
                    $lastName  = $lastNameIndex !== false
                        ? trim((string) ($row[$lastNameIndex] ?? ''))
                        : '';
                    $name = trim($firstName . ' ' . $lastName);
                }

                $email        = strtolower(trim((string) ($row[$emailIndex] ?? '')));
                $businessName = $businessNameIndex !== false
                    ? trim((string) ($row[$businessNameIndex] ?? ''))
                    : null;
                $phone        = $phoneIndex !== false
                    ? trim((string) ($row[$phoneIndex] ?? ''))
                    : null;

                // Sanitise website: prepend scheme if missing; discard if
                // still invalid so that bad website data never rejects a row.
                $websiteRaw = $websiteIndex !== false
                    ? trim((string) ($row[$websiteIndex] ?? ''))
                    : '';

                $website = null;
                if ($websiteRaw !== '') {
                    $candidate = $websiteRaw;
                    if (!preg_match('/^https?:\/\//i', $candidate)) {
                        $candidate = 'https://' . $candidate;
                    }
                    $website = filter_var($candidate, FILTER_VALIDATE_URL) !== false
                        ? $candidate
                        : null;
                }

                // Validate name + email (website already sanitised above)
                $validator = Validator::make(
                    ['name' => $name, 'email' => $email],
                    [
                        'name'  => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255'],
                    ]
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $msg) {
                        $reasons[] = $msg;
                    }
                }

                // Duplicate within this file
                if ($email !== '' && isset($seenInFile[$email])) {
                    $reasons[] = 'Duplicate email in this file';
                }

                // Already exists in the database (checked via bulk pre-load)
                if ($email !== '' && empty($reasons) && isset($existingEmails[$email])) {
                    $reasons[] = 'Email already exists in contacts';
                }

                if (!empty($reasons)) {
                    $skipped++;
                    if (count($failedRows) < self::MAX_FAILED_ROWS_STORED) {
                        $failedRows[] = [
                            'row'     => $rowNumber,
                            'name'    => $name !== '' ? $name : '—',
                            'email'   => $email !== '' ? $email : '—',
                            'reasons' => $reasons,
                        ];
                    }
                } else {
                    $contact = Contact::create([
                        'account_id'    => $accountId,
                        'name'          => $name,
                        'business_name' => $businessName ?: null,
                        'email'         => $email,
                        'website'       => $website ?: null,
                        'phone'         => $phone ?: null,
                    ]);

                    if (!empty($groupIds)) {
                        $contact->groups()->sync($groupIds);
                    }

                    $seenInFile[$email]     = true;
                    $existingEmails[$email] = true;
                    $imported++;
                }

                // Persist progress every 50 rows to reduce DB writes
                if ($processed % 50 === 0 || $processed === $totalRows) {
                    $importRun->update([
                        'processed_rows' => $processed,
                        'imported_rows'  => $imported,
                        'skipped_rows'   => $skipped,
                        'failed_rows'    => $failedRows,
                    ]);
                }
            }

            // ── 6. Mark completed ─────────────────────────────────────────────
            $importRun->update([
                'status'         => 'completed',
                'processed_rows' => $processed,
                'imported_rows'  => $imported,
                'skipped_rows'   => $skipped,
                'failed_rows'    => $failedRows,
                'finished_at'    => now(),
            ]);

        } catch (\Throwable $e) {
            // ── Cleanup ───────────────────────────────────────────────────────
            if (is_resource($handle)) {
                fclose($handle);
            }

            // Attempt to record the error in the DB.
            // The error message is stripped to ASCII-safe text to avoid
            // DB encoding rejections (e.g. MySQL utf8mb3 rejecting 4-byte
            // characters in binary exception messages).
            if ($importRun !== null) {
                try {
                    $safeMessage = mb_substr(
                        preg_replace('/[^\x20-\x7E\n\r\t]/u', '?', $e->getMessage()) ?? $e->getMessage(),
                        0,
                        1000
                    );

                    $importRun->update([
                        'status'        => 'failed',
                        'error_message' => $safeMessage,
                        'finished_at'   => now(),
                    ]);
                } catch (\Throwable) {
                    // DB is unavailable — nothing more we can do.
                    // The job will return normally (not re-throw) so the
                    // queue does not mark this as a permanent FAIL, which
                    // would hide the real error from the UI.
                }
            }

            // Do NOT re-throw. Returning normally means the queue marks
            // this job as DONE (not FAIL).  The import_run.status='failed'
            // with an error_message tells the user what went wrong through
            // the UI.  A queue FAIL would leave import_run stuck at
            // 'processing' with no way to recover.
        }
    }
}
