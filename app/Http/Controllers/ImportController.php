<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportJob;
use App\Models\Group;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        $accountId = (int) ($request->user()?->account_id ?? 0);
        $groups = Group::where('account_id', $accountId)->orderBy('name')->get();

        return view('import.index', compact('groups'));
    }

    public function store(Request $request)
    {
        $accountId = (int) ($request->user()?->account_id ?? 0);

        $request->validate([
            'csv_file'             => ['required', 'file', 'mimes:csv,txt'],
            'name_column'          => ['nullable', 'string'],
            'first_name_column'    => ['nullable', 'string'],
            'last_name_column'     => ['nullable', 'string'],
            'email_column'         => ['required', 'string'],
            'business_name_column' => ['nullable', 'string'],
            'website_column'       => ['nullable', 'string'],
            'phone_column'         => ['nullable', 'string'],
            'groups'               => ['nullable', 'array'],
            'groups.*'             => ['integer', 'exists:groups,id'],
        ]);

        $storedPath = $request->file('csv_file')->store('imports');

        $importRun = ImportRun::create([
            'account_id' => $accountId,
            'user_id' => $request->user()?->id,
            'status' => 'queued',
            'original_filename' => $request->file('csv_file')->getClientOriginalName(),
            'stored_path' => $storedPath,
            'name_column' => trim((string) $request->name_column),
            'first_name_column' => trim((string) $request->first_name_column),
            'last_name_column' => trim((string) $request->last_name_column),
            'email_column' => trim((string) $request->email_column),
            'business_name_column' => trim((string) $request->business_name_column),
            'website_column' => trim((string) $request->website_column),
            'phone_column' => trim((string) $request->phone_column),
            'group_ids' => array_values(array_map('intval', $request->groups ?? [])),
        ]);

        ProcessImportJob::dispatch($importRun->id);

        return redirect()
            ->route('import.progress', $importRun)
            ->with('success', 'Import queued! Processing in background — progress updates below.');
    }

    public function progress(Request $request, ImportRun $importRun)
    {
        $this->authorizeImportRun($request, $importRun);

        return view('import.progress', compact('importRun'));
    }

    public function status(Request $request, ImportRun $importRun): JsonResponse
    {
        $this->authorizeImportRun($request, $importRun);

        $importRun->refresh();

        return response()->json([
            'id' => $importRun->id,
            'status' => $importRun->status,
            'total' => (int) $importRun->total_rows,
            'processed' => (int) $importRun->processed_rows,
            'imported' => (int) $importRun->imported_rows,
            'skipped' => (int) $importRun->skipped_rows,
            'remaining' => max(0, (int) $importRun->total_rows - (int) $importRun->processed_rows),
            'progress_percent' => $importRun->progressPercent(),
            'error_message' => $importRun->error_message,
            'finished' => $importRun->isFinished(),
            'result_url' => route('import.result.run', $importRun),
        ]);
    }

    public function result(Request $request, ImportRun $importRun)
    {
        $this->authorizeImportRun($request, $importRun);

        return view('import.result', [
            'total' => (int) $importRun->total_rows,
            'imported' => (int) $importRun->imported_rows,
            'skipped' => (int) $importRun->skipped_rows,
            'failedRows' => $importRun->failed_rows ?? [],
        ]);
    }

    public function reprocess(Request $request, ImportRun $importRun)
    {
        $this->authorizeImportRun($request, $importRun);

        // Also allow reprocessing 'processing' imports that got stuck mid-run
        // (e.g. the queue worker died before the job could mark itself done).
        if (!in_array($importRun->status, ['queued', 'failed', 'processing'], true)) {
            return redirect()->route('import.progress', $importRun)
                ->withErrors(['reprocess' => 'Only queued, processing, or failed imports can be reprocessed.']);
        }

        $importRun->update([
            'status'         => 'queued',
            'error_message'  => null,
            'started_at'     => null,
            'finished_at'    => null,
            'processed_rows' => 0,
            'imported_rows'  => 0,
            'skipped_rows'   => 0,
            'failed_rows'    => [],
        ]);

        ProcessImportJob::dispatch($importRun->id);

        return redirect()->route('import.progress', $importRun)
            ->with('success', 'Import re-queued! Processing in background — progress updates below.');
    }

    private function authorizeImportRun(Request $request, ImportRun $importRun): void
    {
        $accountId = (int) ($request->user()?->account_id ?? 0);
        if ((int) $importRun->account_id !== $accountId) {
            abort(403);
        }
    }
}
