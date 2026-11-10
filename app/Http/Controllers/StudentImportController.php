<?php

namespace App\Http\Controllers;

use App\Services\StudentCsv;
use App\Services\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudentImportController extends Controller
{
    public function index(Request $request, StudentEnrollment $enrollment)
    {
        return view('teacher.import-students', ['blocks' => $enrollment->blocksForTeacher($request->user()->uid), 'rows' => null, 'preview' => null, 'report' => false]);
    }

    public function template(Request $request, StudentEnrollment $enrollment)
    {
        $blocks = $enrollment->blocksForTeacher($request->user()->uid);

        return response()->streamDownload(function () use ($blocks) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Fullname', 'email', 'block'], ',', '"', '');
            $block = reset($blocks) ?: 'YOUR ASSIGNED BLOCK';
            if (preg_match('/^\s*[=+@-]/u', $block)) {
                $block = "'".$block;
            }
            fputcsv($stream, ['Juan Dela Cruz', 'juan@example.com', $block], ',', '"', '');
            fclose($stream);
        }, 'students-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, StudentCsv $csv, StudentEnrollment $enrollment)
    {
        $request->validate(['csv' => ['required', 'file', 'extensions:csv', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:1024']]);
        $rows = $csv->parse($request->file('csv'));
        $id = (string) Str::uuid();
        Cache::put($this->key($request, $id), ['source' => $rows, 'report' => false], now()->addMinutes(15));

        return redirect()->route('teacher.students.import.show', $id);
    }

    public function show(Request $request, StudentEnrollment $enrollment, string $preview)
    {
        $cached = Cache::get($this->key($request, $preview));
        if (! $cached) {
            return redirect()->route('teacher.students.import')->withErrors(['csv' => 'This preview expired. Please upload your CSV again.']);
        }
        $rows = $cached['report'] ? $cached['rows'] : $enrollment->preview($cached['source'], $request->user()->uid);

        return view('teacher.import-students', ['blocks' => $enrollment->blocksForTeacher($request->user()->uid), 'rows' => $rows, 'preview' => $preview, 'report' => $cached['report']]);
    }

    public function store(Request $request, StudentEnrollment $enrollment, string $preview)
    {
        $request->validate(['confirmation' => ['accepted']]);
        $key = $this->key($request, $preview);
        $cached = Cache::get($key);
        abort_unless($cached && ! $cached['report'], 409, 'Upload the CSV again to get a fresh preview.');
        $rows = $enrollment->import($cached['source'], $request->user()->uid);
        Cache::put($key, ['rows' => $rows, 'report' => true], now()->addHour());

        return redirect()->route('teacher.students.import.show', $preview)->with('success', 'Import finished. Review the results below.');
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
        return 'teacher-student-import:'.hash('sha256', $request->user()->uid).':'.$id;
    }
}
