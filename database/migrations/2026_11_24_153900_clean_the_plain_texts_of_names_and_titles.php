<?php

use App\Support\PlainText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A name is one line of plain text (ADR 0069): the stored names, titles, places and alternative texts get the cleaning
 * that every new value gets (App\Support\PlainText::oneLine()), and the stored descriptions and bios the cleaning of a
 * text of several lines (PlainText::lines()). The rows are read in chunks, and only a value that changes is written.
 * Cleaning a clean value changes nothing, so the migration can run again.
 *
 * A required value with nothing left gets a safe value: the username for the name of an account or of a profile, the
 * slug for the name of a community or of a space, and a fixed word for the others (the list below). A name that must be
 * unique and that another row has after the cleaning gets a number at its end ("Notes 2").
 */
return new class extends Migration
{
    private const int CHUNK = 500;

    /**
     * The one-line columns, by table. The value says what a required column gets when nothing is left: "@column" is the
     * value of another column of the row (then the fixed word after "|" when that one is empty too), any other text is a
     * fixed word. Null: the column may be empty, and it becomes null.
     *
     * @var array<string, array<string, string|null>>
     */
    private const array ONE_LINE = [
        'users' => ['name' => '@username|Member'],
        'user_profiles' => ['name' => '@username|Member', 'location' => null, 'headline' => null],
        'communities' => ['name' => '@slug|Community'],
        'space_groups' => ['name' => 'Group'],
        'spaces' => ['name' => '@slug|Space', 'emoji' => null],
        'access_groups' => ['name' => 'Group'],
        'posts' => ['title' => 'Untitled'],
        'pages' => ['title' => 'Untitled'],
        'events' => ['title' => 'Untitled', 'place' => null, 'cover_alt' => null],
        'course_materials' => ['title' => 'Untitled', 'file_name' => 'file'],
        'community_resources' => ['name' => 'Untitled'],
        'resources' => ['name' => 'Untitled', 'description' => null, 'cover_image_alt' => null],
        'uploads' => ['alt' => 'Image'],
        'personal_access_tokens' => ['name' => 'Token'],
    ];

    /**
     * The other columns that the cleaning of a table reads: the safe value of an empty name, and what makes a name unique
     *
     * @var array<string, list<string>>
     */
    private const array READ_TOO = [
        'users' => ['username'],
        'user_profiles' => ['username'],
        'communities' => ['slug', 'visibility'],
        'spaces' => ['slug'],
        'access_groups' => ['community_id'],
    ];

    /**
     * The columns of several lines, by table: each one may be empty, and then it becomes null
     *
     * @var array<string, list<string>>
     */
    private const array LINES = [
        'user_profiles' => ['bio'],
        'communities' => ['description'],
        'spaces' => ['description'],
        'access_groups' => ['description'],
        'community_resources' => ['description'],
    ];

    public function up(): void
    {
        $changed = [];

        foreach (self::ONE_LINE as $table => $columns) {
            foreach ($columns as $column => $whenEmpty) {
                $changed[$table.'.'.$column] = $this->clean($table, $column, self::READ_TOO[$table] ?? [], function (array $row, string $stored) use ($table, $column, $whenEmpty): ?string {
                    $line = PlainText::oneLine($stored);
                    if ($line === null && $whenEmpty !== null) {
                        $line = self::whenEmpty($row, $whenEmpty);
                    }

                    return $line === null ? null : self::free($table, $column, $row, $stored, $line);
                });
            }
        }

        foreach (self::LINES as $table => $columns) {
            foreach ($columns as $column) {
                $changed[$table.'.'.$column] = $this->clean($table, $column, [], static function (array $row, string $stored): ?string {
                    $text = PlainText::lines($stored);

                    return trim($text) === '' ? null : $text;
                });
            }
        }

        Log::info('The stored names, titles and descriptions are plain text now', ['changed_rows' => array_filter($changed)]);
    }

    public function down(): void
    {
        // The characters that the cleaning removed are stored nowhere: nothing to put back
    }

    /**
     * Cleans one column, chunk by chunk. Returns the number of rows that changed
     *
     * @param  list<string>  $readToo  The other columns that the cleaning of the table reads
     * @param  Closure(array<string, mixed>, string): (string|null)  $cleaned  From the row and the stored text
     */
    private function clean(string $table, string $column, array $readToo, Closure $cleaned): int
    {
        $changed = 0;

        DB::table($table)->select(['id', $column, ...$readToo])->whereNotNull($column)->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($table, $column, $cleaned, &$changed): void {
            foreach ($rows as $row) {
                /** @var array<string, mixed> $values */
                $values = (array) $row;
                $stored = $values[$column] ?? null;
                if (! is_string($stored)) {
                    continue;
                }

                $value = $cleaned($values, $stored);
                if ($value !== $stored) {
                    DB::table($table)->where('id', $values['id'] ?? null)->update([$column => $value]);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /**
     * The safe value of a required column with nothing left
     *
     * @param  array<string, mixed>  $row
     */
    private static function whenEmpty(array $row, string $whenEmpty): string
    {
        if (! str_starts_with($whenEmpty, '@')) {
            return $whenEmpty;
        }

        [$other, $word] = explode('|', substr($whenEmpty, 1), 2);
        $value = $row[$other] ?? null;

        return (is_string($value) ? PlainText::oneLine($value) : null) ?? $word;
    }

    /**
     * The name, with a number at its end when another row has it and the two must differ: the name of a shared community
     * (whatever the case), and the name of an access group in its community
     *
     * @param  array<string, mixed>  $row
     */
    private static function free(string $table, string $column, array $row, string $stored, string $name): string
    {
        $id = $row['id'] ?? null;
        $taken = match (true) {
            $table === 'communities' && $column === 'name' && ($row['visibility'] ?? null) !== 'personal' => static fn (string $candidate): bool => DB::table('communities')
                ->where('id', '!=', $id)->where('visibility', '!=', 'personal')->whereRaw('lower(name) = lower(?)', [$candidate])->exists(),
            $table === 'access_groups' && $column === 'name' => static fn (string $candidate): bool => DB::table('access_groups')
                ->where('id', '!=', $id)->where('community_id', $row['community_id'] ?? null)->where('name', $candidate)->exists(),
            default => null,
        };
        if ($taken === null || $name === $stored || ! $taken($name)) {
            return $name;
        }

        for ($number = 2; ; $number++) {
            $candidate = mb_substr($name, 0, 255 - mb_strlen(' '.$number)).' '.$number;
            if (! $taken($candidate)) {
                return $candidate;
            }
        }
    }
};
