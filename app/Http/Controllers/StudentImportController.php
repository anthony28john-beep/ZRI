<?php

namespace App\Http\Controllers;

use App\Services\StudentCsv;
use App\Services\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudentImportController extends Controller
{
    public function index(StudentEnrollment $enrollment)
    {
        return view('admin.users.import', ['pending' => $enrollment->pending(), 'rows' => null, 'preview' => null, 'report' => false]);
    }

    public function template()
    {
        return response("Fullname,email,block\r\nJuan Dela Cruz,juan@example.com,BSCE 2 A\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="students-template.csv"',
        ]);
    }

    public function preview(Request $request, StudentCsv $csv, StudentEnrollment $enrollment)
    {
        $request->validate(['csv' => ['required', 'file', 'extensions:csv', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:1024']]);
        $rows = $csv->parse($request->file('csv'));
        $id = (string) Str::uuid();
        Cache::put($this->key($request, $id), ['source' => $rows, 'report' => false], now()->addMinutes(15));

        return redirect()->route('admin.students.import.show', $id);
    }

    public function show(Request $request, StudentEnrollment $enrollment, string $preview)
    {
        $cached = Cache::get($this->key($request, $preview));
        if (! $cached) {
            return redirect()->route('admin.students.import')->withErrors(['csv' => 'This preview expired. Please upload your CSV again.']);
        }
        $rows = $cached['report'] ? $cached['rows'] : $enrollment->preview($cached['source']);

        return view('admin.users.import', ['pending' => [], 'rows' => $rows, 'preview' => $preview, 'report' => $cached['report']]);
    }

    public function store(Request $request, StudentEnrollment $enrollment, string $preview)
    {
        $request->validate(['confirmation' => ['accepted']]);
        $key = $this->key($request, $preview);
        $cached = Cache::get($key);
        abort_unless($cached && ! $cached['report'], 409, 'Upload the CSV again to get a fresh preview.');
        $rows = $enrollment->import($cached['source'], $request->user()->uid);
        Cache::put($key, ['rows' => $rows, 'report' => true], now()->addHour());

        return redirect()->route('admin.students.import.show', $preview)->with('success', 'Import finished. Review the results below.');
    }

    public function revoke(Request $request, StudentEnrollment $enrollment, string $invitation)
    {
        $request->validate(['confirmation' => ['accepted']]);
        $enrollment->revoke($invitation, $request->user()->uid);

        return redirect()->route('admin.students.import')->with('success', 'Pending invitation removed. You can now import corrected details. Existing accounts and scores were retained.');
    }

    public function report(Request $request, string $preview)
    {
        $cached = Cache::get($this->key($request, $preview));
        abort_unless($cached && $cached['report'], 404);

        return response()->streamDownload(function () use ($cached) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Row', 'Fullname', 'email', 'block', 'Status', 'Message'], ',', '"', '');
            foreach ($cached['rows'] as $row) {
                $values = [$row['row'], $row['fullname'], $row['email'], $row['block'], $row['status'], $row['message']];
                // Spreadsheet exports must not interpret imported text as formulas.
                $values = array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $values);
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, 'student-import-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function key(Request $request, string $id): string
    {
        return 'student-import:'.hash('sha256', $request->user()->uid).':'.$id;
    }
}
