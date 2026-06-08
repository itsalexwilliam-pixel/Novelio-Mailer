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
     * How long the job may run before the queue driver considers it lost.
     * Must be longer than the longest possible import to prevent mid-job restarts.
     */
    public int $timeout = 1800;

    /**
     * Tell the database queue driver NOT to re-queue this job until 30 minutes
     * have passed — overrides the global retry_after: 90 that was causing the
     * job to be picked up a second time while still running.
     */
    public int $retryAfter = 1800;

    /**
     * Maximum number of failed/skipped row details to store in the DB.
     * Prevents the failed_rows JSON from growing unbounded on large bad files.
     */
    private const MAX_FAILED_ROWS_STORED = 500;

    public function __construct(public int $importRunId)
    {
    }

    public function handle(): void
    {
        $importRun = ImportRun::find($this->importRunId);
        if (!$importRun) {
            return;
        }

        $importRun->update([
            'status'        => 'processing',
            'started_at'    => now(),
            'error_message' => null,
        ]);

        $relativePath = trim((string) $importRun->stored_path);
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        if (! Storage::disk('local')->exists($relativePath)) {
            $importRun->update([
                'status'        => 'failed',
                'error_message' => 'Uploaded CSV file not found. Path: ' . $relativePath,
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

        try {
            // ── 1. Read headers ───────────────────────────────────────────────
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'CSV file appears to be empty.',
                    'finished_at'   => now(),
                ]);
                return;
            }

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
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'No valid name column found in CSV headers. Headers found: ' . implode(', ', $headers),
                    'finished_at'   => now(),
                ]);
                return;
            }

            if ($emailIndex === false) {
                fclose($handle);
                $importRun->update([
                    'status'        => 'failed',
                    'error_message' => 'Email column "' . $emailCol . '" not found in CSV headers. Headers found: ' . implode(', ', $headers),
                    'finished_at'   => now(),
                ]);
                return;
            }

            // ── 3. Read all data rows into memory ─────────────────────────────
            $allRows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                    continue; // skip blank lines
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

            // Extract every email from the file in one pass so we can do a
            // single WHERE IN lookup instead of one query per row.
            $allFileEmails = [];
            foreach ($allRows as $row) {
                $e = strtolower(trim((string) ($row[$emailIndex] ?? '')));
                if ($e !== '') {
                    $allFileEmails[] = $e;
                }
            }

            // Fetch all already-existing emails in one query, chunk to stay
            // within MySQL's max_allowed_packet limits on very large imports.
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
            $seenInFile = [];   // tracks duplicates within the file
            $failedRows = [];
            $processed  = 0;
            $imported   = 0;
            $skipped    = 0;
            $rowNumber  = 1;    // header = row 1

            foreach ($allRows as $row) {
                $rowNumber++;
                $processed++;
                $reasons = [];

                // Build name
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

                // ── Website: sanitise before validation ───────────────────────
                // Prepend scheme if missing, then discard the value (don't fail
                // the whole row) if it still isn't a valid URL.
                $websiteRaw = $websiteIndex !== false
                    ? trim((string) ($row[$websiteIndex] ?? ''))
                    : '';

                $website = null;
                if ($websiteRaw !== '') {
                    $candidate = $websiteRaw;
                    if (!preg_match('/^https?:\/\//i', $candidate)) {
                        $candidate = 'https://' . $candidate;
                    }
                    // Only keep the value if PHP itself considers it a valid URL.
                    // Invalid websites (N/A, commas, plain text …) are silently
                    // cleared so the row is NOT skipped because of bad website data.
                    $website = filter_var($candidate, FILTER_VALIDATE_URL) !== false
                        ? $candidate
                        : null;
                }

                // ── Validate name + email ─────────────────────────────────────
                $validator = Validator::make(
                    ['name' => $name, 'email' => $email],
                    [
                        'name'  => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email:rfc', 'max:255'],
                    ]
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $msg) {
                        $reasons[] = $msg;
                    }
                }

                // ── Duplicate within this file ────────────────────────────────
                if ($email !== '' && isset($seenInFile[$email])) {
                    $reasons[] = 'Duplicate email in this file';
                }

                // ── Already in database (uses the pre-loaded set) ─────────────
                if ($email !== '' && empty($reasons) && isset($existingEmails[$email])) {
                    $reasons[] = 'Email already exists in contacts';
                }

                if (!empty($reasons)) {
                    $skipped++;
                    // Cap stored details to avoid unbounded JSON growth
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

                    // Mark this email as "seen" so later duplicates in the file
                    // are caught, and add to the in-memory existing set so the
                    // same address cannot be inserted twice in one run.
                    $seenInFile[$email]      = true;
                    $existingEmails[$email]  = true;
                    $imported++;
                }

                // Persist progress every 50 rows (reduced write frequency)
                if ($processed % 50 === 0 || $processed === $totalRows) {
                    $importRun->update([
                        'processed_rows' => $processed,
                        'imported_rows'  => $imported,
                        'skipped_rows'   => $skipped,
                        'failed_rows'    => $failedRows,
                    ]);
                }
            }

            // ── 6. Final status update ────────────────────────────────────────
            $importRun->update([
                'status'         => 'completed',
                'processed_rows' => $processed,
                'imported_rows'  => $imported,
                'skipped_rows'   => $skipped,
                'failed_rows'    => $failedRows,
                'finished_at'    => now(),
            ]);

        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $importRun->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }
    }
}
