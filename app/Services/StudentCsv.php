<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentCsv
{
    public function parse(UploadedFile $file): array
    {
        if (! mb_check_encoding(file_get_contents($file->getRealPath()), 'UTF-8')) {
            $this->invalid('Save your file as UTF-8 CSV before uploading.');
        }
        $stream = fopen($file->getRealPath(), 'r');
        try {
            $header = fgetcsv($stream, 0, ',', '"', '');
            if (! $header) {
                $this->invalid('The CSV is empty.');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map(fn ($value) => strtolower(trim($value)), $header);
            $sorted = $header;
            sort($sorted);
            if ($sorted !== ['block', 'email', 'fullname']) {
                $this->invalid('Use exactly these CSV headers: Fullname,email,block (in any order). Save as UTF-8 CSV.');
            }
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || (count($values) === 1 && trim($values[0] ?? '') === '')) {
                    continue;
                }
                if (count($rows) >= 100) {
                    $this->invalid('Import up to 100 students at a time. Split larger files into smaller CSV files.');
                }
                if (count($values) !== 3) {
                    $rows[] = ['row' => $line, 'fullname' => '', 'email' => '', 'block' => '', 'error' => 'Expected three columns. Put names containing commas inside quotes.'];

                    continue;
                }
                $row = array_map(fn ($value) => trim($value ?? ''), array_combine($header, $values));
                $row['email'] = strtolower($row['email']);
                $validation = Validator::make($row, ['fullname' => 'required|string|max:100', 'email' => 'required|email|max:254', 'block' => 'required|string|max:100']);
                $rows[] = $row + ['row' => $line, 'error' => $validation->fails() ? implode(' ', $validation->errors()->all()) : null];
            }
        } finally {
            fclose($stream);
        }
        if (! $rows) {
            $this->invalid('Add at least one student below the header.');
        }
        $counts = array_count_values(array_column($rows, 'email'));
        foreach ($rows as &$row) {
            if ($row['email'] !== '' && $counts[$row['email']] > 1) {
                $row['error'] = 'Email appears more than once in this CSV. Keep one row per email.';
            }
        }

        return $rows;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['csv' => $message]);
    }
}
