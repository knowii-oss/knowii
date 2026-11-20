<?php

namespace Tests;

use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * The temporary folders of legal texts of this test: tearDown() deletes them
     *
     * @var list<string>
     */
    private array $legalFolders = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never depend on the DNS: every host is a public address unless a test says otherwise (#1231)
        SafeUrl::resolveUsing(static fn (string $host): array => ['93.184.215.14']);

        // No bot check unless a test turns it on: the local .env may hold the Cloudflare test keys (#737)
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        // The legal texts of the tests are neutral fixtures (#1653): no test reads or writes storage/app/legal
        config(['legal.path' => base_path('tests/Fixtures/legal')]);
    }

    protected function tearDown(): void
    {
        foreach ($this->legalFolders as $folder) {
            File::deleteDirectory($folder);
        }
        $this->legalFolders = [];

        SafeUrl::resolveUsing(null);

        parent::tearDown();
    }

    /**
     * Gives this test its own folder of legal texts, with only the given files (name => contents), and points the
     * configuration at it. Without a file the instance has no legal text. The folder is deleted after the test.
     *
     * @param  array<string, string>  $files
     */
    public function useLegalFolder(array $files = []): string
    {
        $folder = sys_get_temp_dir().'/knowii-legal-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($folder);
        foreach ($files as $name => $contents) {
            File::put($folder.'/'.$name, $contents);
        }
        $this->legalFolders[] = $folder;
        config(['legal.path' => $folder]);

        return $folder;
    }
}
