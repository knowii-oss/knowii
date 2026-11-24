<?php

use App\Enums\KnowiiInvitationImportRefusal;
use App\Support\InvitationImports\InvitationCsv;
use App\Support\InvitationImports\InvitationCsvRecord;

/*
 * The reader of a CSV file of invitations (#1390, ADR 0061): rules 37 to 45 of the brief, one by one. No query, no
 * community.
 */

/**
 * The records of a content that the reader accepts
 *
 * @return list<InvitationCsvRecord>
 */
function csvRecords(string $content): array
{
    $result = InvitationCsv::parse($content);
    expect($result->error)->toBeNull();

    return $result->records;
}

test('a file of the template is read: the address, the role, the groups and the tags, without the name', function () {
    $records = csvRecords("email,name,role,access_groups,tags\ngrace@example.com,Grace Hopper,moderator,Paid|Alumni,Founder\n");

    expect($records)->toHaveCount(1)
        ->and($records[0]->rowNumber)->toBe(2)
        ->and($records[0]->email)->toBe('grace@example.com')
        ->and($records[0]->role)->toBe('moderator')
        ->and($records[0]->accessGroups)->toBe('Paid|Alumni')
        ->and($records[0]->tags)->toBe('Founder')
        ->and($records[0]->problem)->toBeNull()
        ->and(serialize($records))->not->toContain('Grace Hopper');
});

test('a byte order mark at the start is removed', function () {
    expect(csvRecords("\xEF\xBB\xBFemail\nada@example.com\n")[0]->email)->toBe('ada@example.com');
});

test('CRLF, LF and a lone CR all end a record', function (string $break) {
    $records = csvRecords('email'.$break.'ada@example.com'.$break.'grace@example.com');

    expect(array_map(fn (InvitationCsvRecord $record) => [$record->rowNumber, $record->email], $records))
        ->toBe([[2, 'ada@example.com'], [3, 'grace@example.com']]);
})->with(['CRLF' => "\r\n", 'LF' => "\n", 'a lone CR' => "\r"]);

test('a file with semicolons is read when its header holds no comma', function () {
    $records = csvRecords("email;role;tags\nada@example.com;admin;\"Founder;Paid\"\n");

    expect($records[0]->email)->toBe('ada@example.com')
        ->and($records[0]->role)->toBe('admin')
        ->and($records[0]->tags)->toBe('Founder;Paid');
});

test('a header with a comma keeps the comma as the delimiter, even with a semicolon in it', function () {
    $records = csvRecords("email,role;x\nada@example.com,member\n");

    expect($records[0]->email)->toBe('ada@example.com');
});

test('a quoted cell holds a comma, a line break and a quote', function () {
    $records = csvRecords("email,tags,note\n\"ada@example.com\",\"Founder, early\",\"two\nlines and \"\"quotes\"\"\"\nb@example.com,,\n");

    expect($records)->toHaveCount(2)
        ->and($records[0]->tags)->toBe('Founder, early')
        // The record of the quoted line break is one record: the next one is row 3
        ->and($records[1]->rowNumber)->toBe(3)
        ->and($records[1]->email)->toBe('b@example.com');
});

test('a quote inside a cell that does not start with one is a plain character, and the next rows stay rows', function () {
    $records = csvRecords("email,name\na@example.com,Bob \"B\nc@example.com,Carol\n");

    expect(array_map(fn (InvitationCsvRecord $record) => [$record->rowNumber, $record->email], $records))
        ->toBe([[2, 'a@example.com'], [3, 'c@example.com']]);
});

test('a quote after the text of a cell or after a closed quote is kept in the cell', function () {
    $records = csvRecords("email,tags\nada@example.com,Founder \"early\"\ngrace@example.com,\"Paid\" x\n");

    expect($records[0]->tags)->toBe('Founder "early"')
        ->and($records[1]->tags)->toBe('Paid x');
});

test('a quote that is still open at the end of the file refuses the file', function (string $content) {
    expect(InvitationCsv::parse($content)->error)->toBe('A cell of the file opens a quote that is never closed. Close the quote, then upload the file again.');
})->with([
    'in a row' => ["email,name\na@example.com,\"Bob\nc@example.com,Carol\n"],
    'in a file with semicolons' => ["email;name\na@example.com;\"Bob\n"],
    'in the header' => ["\"email,name\na@example.com,Bob\n"],
]);

test('a backslash is no escape character', function () {
    expect(csvRecords("email,tags\nada@example.com,\"a\\\\\",b\n")[0]->tags)->toBe('a\\\\');
});

test('a file in Windows-1252, a byte that is not UTF-8, or a NUL byte is refused', function (string $content) {
    expect(InvitationCsv::parse($content)->error)->toBe('Save the file as CSV UTF-8, then upload it again');
})->with([
    'Windows-1252' => ["email,name\nada@example.com,Ren\xE9e\n"],
    'a NUL byte' => ["email\nada@example.com\0\n"],
]);

test('a file without a column email is refused', function (string $content) {
    expect(InvitationCsv::parse($content)->error)->toBe('The file needs a column named email');
})->with(['no email column' => ["address,name\nada@example.com,Ada\n"], 'an empty file' => [''], 'only empty lines' => ["\n\n,,\n"]]);

test('a known column twice is refused', function () {
    expect(InvitationCsv::parse("email,Tags,tags\nada@example.com,a,b\n")->error)->toBe('The column tags is in the file twice');
});

test('a file holds at most 20 columns', function () {
    $twenty = 'email,'.implode(',', array_map(fn (int $n) => 'c'.$n, range(1, 19)));

    expect(InvitationCsv::parse($twenty."\nada@example.com\n")->error)->toBeNull()
        ->and(InvitationCsv::parse($twenty.",c20\nada@example.com\n")->error)->toBe('A file holds at most 20 columns');
});

test('the ignored columns are kept by name for the preview', function () {
    $result = InvitationCsv::parse("email,name,Phone,Member since\nada@example.com,Ada,123,2020\n");

    expect($result->ignoredColumns)->toBe(['Phone', 'Member since']);
});

test('a file holds at most 1000 rows; empty records are skipped and not counted', function () {
    $rows = implode("\n", array_map(fn (int $n) => 'p'.$n.'@example.com', range(1, 1000)));

    $thousand = InvitationCsv::parse("email\n".$rows."\n,\n\n");
    expect($thousand->error)->toBeNull()
        ->and($thousand->records)->toHaveCount(1000);

    expect(InvitationCsv::parse("email\n".$rows."\np1001@example.com\n")->error)->toBe('A file holds at most 1000 rows. Split it into several files.');
});

test('empty records are skipped, and the numbers stay those of the file', function () {
    $records = csvRecords("\nemail,role\n\nada@example.com,\n , \ngrace@example.com,admin\n");

    expect(array_map(fn (InvitationCsvRecord $record) => $record->rowNumber, $records))->toBe([3, 5]);
});

test('extra cells are ignored when empty, else the row is refused', function () {
    $records = csvRecords("email,role\nada@example.com,member,,\ngrace@example.com,member,surprise\n");

    expect($records[0]->problem)->toBeNull()
        ->and($records[1]->problem)->toBe(KnowiiInvitationImportRefusal::ExtraCells)
        ->and($records[1]->email)->toBe('grace@example.com');
});

test('a cell is trimmed, and a cell over 1000 characters refuses its row', function () {
    $long = str_repeat('é', 1001);
    $records = csvRecords("email,tags\n  ada@example.com  ,  Founder \ngrace@example.com,".str_repeat('é', 1000)."\nlin@example.com,".$long."\n");

    expect($records[0]->email)->toBe('ada@example.com')
        ->and($records[0]->tags)->toBe('Founder')
        ->and($records[1]->problem)->toBeNull()
        ->and($records[2]->problem)->toBe(KnowiiInvitationImportRefusal::CellTooLong);
});

test('a header in another case, with spaces or dashes, names the known columns', function () {
    $records = csvRecords(" EMAIL , Access Groups ,access-groups-x, TAGS \nada@example.com,Paid,x,Founder\n");

    expect($records[0]->accessGroups)->toBe('Paid')
        ->and($records[0]->tags)->toBe('Founder')
        ->and(InvitationCsv::parse(" EMAIL , Access Groups ,access-groups-x, TAGS \nada@example.com,Paid,x,Founder\n")->ignoredColumns)->toBe(['access-groups-x']);
});

test('a file larger than 1 MB is refused before it is read', function () {
    $path = tempnam(sys_get_temp_dir(), 'qxcsv');
    file_put_contents($path, "email\n".str_repeat('a', 1024 * 1024));

    expect(InvitationCsv::read($path)->error)->toBe('The file is larger than 1 MB');
    unlink($path);
});

test('a file of empty lines or of one long line is read with little memory', function (string $content, ?string $error) {
    memory_reset_peak_usage();
    $before = memory_get_usage();

    $result = InvitationCsv::parse($content);

    expect($result->error)->toBe($error)
        ->and(memory_get_peak_usage() - $before)->toBeLessThan(24 * 1024 * 1024);
})->with([
    'one million line breaks' => [str_repeat("\n", 1024 * 1024), 'The file needs a column named email'],
    'one million cells in the header' => ['email'.str_repeat(',', 1024 * 1024)."\n", 'A file holds at most 20 columns'],
    'one million cells in a row' => ["email\nada@example.com".str_repeat(',', 1024 * 1024)."\n", null],
    'many short rows' => ["email\n".str_repeat("a\n", 500 * 1024), 'A file holds at most 1000 rows. Split it into several files.'],
]);
