<?php

namespace Tests\Unit;

use App\Services\StudentCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentCsvTest extends TestCase
{
    public function test_utf8_headers_can_be_reordered_and_quoted_names_and_email_normalization_work(): void
    {
        $rows = (new StudentCsv)->parse(UploadedFile::fake()->createWithContent('students.csv', "\xEF\xBB\xBFemail, block, Fullname\r\n JUAN@Example.com ,BSCE 2 A,\"Dela Cruz, Juan\"\r\n"));
        $this->assertSame('juan@example.com', $rows[0]['email']);
        $this->assertSame('Dela Cruz, Juan', $rows[0]['fullname']);
        $this->assertNull($rows[0]['error']);
    }

    public function test_invalid_rows_and_duplicate_emails_are_reported(): void
    {
        $rows = (new StudentCsv)->parse(UploadedFile::fake()->createWithContent('students.csv', "Fullname,email,block\nJuan,JUAN@example.com,BSCE 2 A\nOther,juan@example.com,BSCE 2 A\nName,invalid,\nToo,few\n"));
        $this->assertStringContainsString('more than once', $rows[0]['error']);
        $this->assertStringContainsString('more than once', $rows[1]['error']);
        $this->assertNotEmpty($rows[2]['error']);
        $this->assertStringContainsString('three columns', $rows[3]['error']);
    }

    public function test_bad_headers_empty_files_and_oversized_batches_are_rejected(): void
    {
        foreach (["username,email,block\nJuan,juan@example.com,A", 'Fullname,email,block', "Fullname,email,block\n".str_repeat("Juan,juan@example.com,A\n", 101)] as $content) {
            try {
                (new StudentCsv)->parse(UploadedFile::fake()->createWithContent('students.csv', $content));
                $this->fail('Invalid CSV accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('csv', $exception->errors());
            }
        }
    }
}
