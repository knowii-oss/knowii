<?php

use App\Support\LegalPages;
use Illuminate\Support\Facades\File;

/**
 * The templates of the legal texts (#1653, ADR 0055): neutral texts in resources/legal-templates, for an operator who
 * starts its own texts. Nothing serves them: an operator copies them to storage/app/legal, replaces every mark, and
 * removes the draft notice (SELF_HOSTING.md, "Legal pages").
 */
function legalTemplatesFolder(): string
{
    return resource_path('legal-templates');
}

dataset('legal templates', array_map(static fn (string $file): array => [$file.'.md'], array_values(LegalPages::PAGES)));

test('the repository holds seven templates: one text for each legal page, and an example of the sub-processors', function () {
    $files = array_map(static fn (SplFileInfo $file): string => $file->getFilename(), File::files(legalTemplatesFolder()));
    sort($files);

    expect($files)->toBe(['content-policy.md', 'dpa.md', 'imprint.md', 'policy.md', 'security.md', 'subprocessors.example.json', 'terms.md'])
        ->and(File::directories(legalTemplatesFolder()))->toBe([]);
});

test('a template is a draft, with a title and no date', function (string $file) {
    $contents = File::get(legalTemplatesFolder().'/'.$file);
    $frontMatter = explode("\n---\n", $contents, 2)[0];

    expect(str_starts_with($contents, "---\ntitle: "))->toBeTrue()
        ->and($frontMatter)->toContain("\ndraft: true")
        ->and($frontMatter)->not->toContain('updated');
})->with('legal templates');

test('a template that an operator copies shows the draft notice, and has no version', function (string $file) {
    $slug = (string) array_search(basename($file, '.md'), LegalPages::PAGES, true);
    $this->useLegalFolder([$file => File::get(legalTemplatesFolder().'/'.$file)]);

    expect(LegalPages::updated($slug))->toBeNull();
    $this->get('/'.$slug)->assertOk()->assertInertia(fn ($page) => $page
        ->where('draft', true)
        ->where('updated', null)
        ->where('html', fn (string $html) => str_contains($html, '<h1>') && str_contains($html, '[TODO:') && ! str_contains($html, '{{')));
})->with('legal templates');

test('a template uses only the placeholders that the code fills', function (string $file) {
    $contents = File::get(legalTemplatesFolder().'/'.$file);

    expect(LegalPages::fillPlaceholders($contents))->not->toContain('{{')->not->toContain('}}');
})->with('legal templates');

test('a template marks what only the operator knows', function (string $file) {
    $contents = File::get(legalTemplatesFolder().'/'.$file);

    expect($contents)->toContain('[TODO: ');
    // A mark is closed on its line, and says what to write
    foreach (explode('[TODO:', $contents) as $index => $part) {
        if ($index > 0) {
            $mark = strstr($part, ']', true);
            expect($mark)->toBeString()->and(str_contains((string) $mark, "\n"))->toBeFalse()->and(strlen(trim((string) $mark)))->toBeGreaterThan(5);
        }
    }
})->with('legal templates');

test('a template names the operator, its contact address and its site only through a placeholder', function () {
    $all = '';
    foreach (array_values(LegalPages::PAGES) as $file) {
        $all .= File::get(legalTemplatesFolder().'/'.$file.'.md');
    }

    expect($all)->toContain('{{operator}}')->toContain('{{contact}}')->toContain('(mailto:{{contact}})')->toContain('{{site}}')
        // The placeholders of the platform stay
        ->toContain('{{subprocessors}}')->toContain('{{subprocessors.community}}')->toContain('{{cookies.session}}')->toContain('{{sign_in_provider}}')->toContain('{{retention.session}}')
        // No address is typed: every mailto link holds the placeholder, and no text holds an "@"
        ->and(substr_count($all, 'mailto:'))->toBe(substr_count($all, 'mailto:{{contact}}'))
        ->and($all)->not->toContain('@');
});

test('the template of the privacy policy tells about the answers to events, with the period of the configuration (#1515)', function () {
    $policy = File::get(legalTemplatesFolder().'/policy.md');

    expect($policy)
        ->toContain('Your answers to events (yes, replay only or no), until {{retention.event_answers}} after the event or until you leave the community. The host and the team of an event see who answered yes or replay only. They do not see when you open the meeting. We do not record who attends a meeting.')
        ->toContain('Answers to events: {{retention.event_answers}} after the event, or until you leave the community.');
});

test('the template of the privacy policy tells who sees the member list of a community, and what an entry holds (#148)', function () {
    $policy = File::get(legalTemplatesFolder().'/policy.md');

    expect($policy)
        ->toContain('The members of a community see its member list: your name, your username, your picture, your headline, your role and the date you joined.')
        ->not->toContain('The owner and the administrators of a community see its member list.');
});

test('the template of the privacy policy tells about the member tags, who sees them and how long they stay (#1389)', function () {
    $policy = File::get(legalTemplatesFolder().'/policy.md');

    expect($policy)
        ->toContain('your access groups, the tags that the managers of a community put on you, the lessons')
        ->toContain('The managers of a community can put tags on you, for example "Founder". A tag is a short name and holds no other text.')
        ->toContain('The members of a community see the tags that its managers chose to show, on your profile and in the member list there. The owner, the administrators and the moderators who manage members see all your tags in that community. Your data export lists all of them.')
        ->toContain('15. Tags: until a manager removes the tag, you leave the community, or you delete your account.');
});

test('the imprint template keeps only its headings and the source code of the project', function () {
    $body = explode("\n---\n", File::get(legalTemplatesFolder().'/imprint.md'), 2)[1] ?? '';
    $lines = array_values(array_filter(array_map(trim(...), explode("\n", $body)), static fn (string $line): bool => $line !== ''));

    foreach ($lines as $line) {
        expect(str_starts_with($line, '#') || str_starts_with($line, '[TODO: ') || str_contains($line, 'github.com/knowii-oss/knowii'))->toBeTrue();
    }
    expect($lines)->toContain('## Publisher', '## Responsible person', '## Contact', '## Hosting', '## Source code');
});

test('the example of the sub-processors is a valid list that the code reads', function () {
    $example = File::get(legalTemplatesFolder().'/subprocessors.example.json');
    $decoded = json_decode($example, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()->toHaveCount(2)
        ->and(array_is_list($decoded))->toBeTrue();
    foreach ($decoded as $entry) {
        expect(array_keys($entry))->toBe(['name', 'country', 'purpose', 'safeguard', 'scope'])
            ->and($entry['name'])->toStartWith('Example ');
    }

    // Under its real name, the file gives the list
    $this->useLegalFolder([LegalPages::SUBPROCESSORS_FILE => $example]);

    expect(array_column(LegalPages::subprocessors(), 'name'))->toBe(array_column($decoded, 'name'));
});

test('nothing serves the templates: no file of the application, the configuration or the routes names their folder', function () {
    $name = 'legal-templates';
    $naming = [];
    $read = 0;

    foreach (['app', 'config', 'routes'] as $folder) {
        foreach (File::allFiles(base_path($folder)) as $file) {
            $read++;
            if (str_contains($file->getContents(), $name)) {
                $naming[] = $folder.'/'.$file->getRelativePathname();
            }
        }
    }

    expect($read)->toBeGreaterThan(300)->and($naming)->toBe([]);
});

test('with an empty folder no legal page is served, although the templates exist', function (string $file) {
    $slug = (string) array_search(basename($file, '.md'), LegalPages::PAGES, true);
    $this->useLegalFolder();

    expect(File::isFile(legalTemplatesFolder().'/'.$file))->toBeTrue()
        ->and(LegalPages::exists($slug))->toBeFalse()
        ->and(LegalPages::available())->toBe([]);
    $this->get('/'.$slug)->assertNotFound();
})->with('legal templates');

test('the example file of the sub-processors is not read under its example name', function () {
    $this->useLegalFolder(['subprocessors.example.json' => File::get(legalTemplatesFolder().'/subprocessors.example.json')]);

    expect(LegalPages::subprocessors())->toBe([]);
});
