<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A sign-in takes the same time for an address with and without an account (documentation/architecture/backend.md): a
 * failed password check that has no hash to compare (no account has the address, or the account has no password) checks
 * the password against this hash, at the cost of the application. No password of an account has it: it is the hash of a
 * random text.
 *
 * The hash is a constant of the class for each cost of bcrypt from 4 to 15 (production: 12; the tests: 4) and for the two
 * drivers of argon with the options of the framework: no query and no hash is made. Only other options (an argon with other
 * costs) use a hash made once with the options of the application and kept in the cache under a key of those options.
 * Never one hash a request: an unknown address would cost two hashes.
 */
final class DummyPassword
{
    /**
     * The hash of a random text, bcrypt with cost 12: the cost of production
     */
    public const string HASH = '$2y$12$CR0ufxKCDjQJIF.n2emPzOjaVYCqFdFDooYwhvK3c6uusTx/u2nw.';

    /**
     * The hashes of random texts, one for each cost of bcrypt and for argon with the options of the framework
     *
     * @var list<string>
     */
    public const array HASHES = [
        '$2y$04$cCDWvrXKZdGferkYtKApqesLEFM//jBJbjbvYjgWIyBtN6Dk1uAsO',
        '$2y$05$wIG2kr6OLuetaYgNKZ/MUuME7UUlAv.be./vL0BB5yqm8vlC5c1Ve',
        '$2y$06$3hIZH4I3DL7Afbsy.KWgA.J10tWUxPHfxK1wFwr7b4o0G0w3wJyKy',
        '$2y$07$cAKm7ENo.csmHK1/63Hdr.VfeHxAuzmMLERt2km0KwoLN6nsGCXv.',
        '$2y$08$cNnfXcEJzmaN9YTWsg6ZeekU.Uet7I.EaJptQ/hQSb99vl4NcZNLC',
        '$2y$09$vuCWCJcbdq6D2Nukrw.fk.qzcuP3e/Yk297FbWRqAHuljDcFEaUeS',
        '$2y$10$TViPwuUFvuZI0fyVcigU5OW4T87kwfyKQEzNrZ3cG9w4xdcXc6wZK',
        '$2y$11$NH3lCBg2eYUXtl8ItMGUxOJBejzr3VKHS9HXibDOFm8dtHPzSxAP2',
        self::HASH,
        '$2y$13$N.E7IpzsKrYKNW.Ty2TnGOBhtKfYONyEyFIz/iKdLWJQ4CXMP9FWa',
        '$2y$14$2sJ70xeK2VQjH/KZLT6Ai.C3.rMFnJ0AqbWQ1wjIsSHXrgmreD0xi',
        '$2y$15$qH6fjGMszGRv1wAKNYEYoO4SOw9D/LaWaGXj8BPpd4e.JZRXXfHQ6',
        '$argon2i$v=19$m=65536,t=4,p=1$RFJadVlhTDJ5YzZRMW1OUw$rvP6vTHYufX4PSJhpv3E6sH00TmABzVD2fKMAPpCcJc',
        '$argon2id$v=19$m=65536,t=4,p=1$cmRCTTZ3bDVwZ244aERxWg$tw+ylJvnUg58gWu8tY7Ms/YS+9wmpakYV/qCHfXZ1ss',
    ];

    /**
     * One hash check at the cost of the application. The result is always false and is not used
     */
    public static function check(#[\SensitiveParameter] string $password): void
    {
        Hash::check($password, self::hash());
    }

    /**
     * The hash that check() compares: the constant, or the hash of the options of the application
     */
    public static function hash(): string
    {
        foreach (self::HASHES as $hash) {
            if (password_get_info($hash)['algoName'] === self::algorithm() && ! Hash::needsRehash($hash)) {
                return $hash;
            }
        }

        return Cache::rememberForever(self::cacheKey(), static fn (): string => Hash::make(Str::random(64)));
    }

    /**
     * The name of the algorithm of the driver of the application, as password_get_info() gives it
     */
    private static function algorithm(): string
    {
        return match (config('hashing.driver', 'bcrypt')) {
            'argon' => 'argon2i',
            'argon2id' => 'argon2id',
            default => 'bcrypt',
        };
    }

    /**
     * The key of the options of the hashing: another cost or another driver is another hash
     */
    private static function cacheKey(): string
    {
        $options = [
            config('hashing.driver', 'bcrypt'),
            config('hashing.bcrypt.rounds'),
            config('hashing.argon.memory'),
            config('hashing.argon.threads'),
            config('hashing.argon.time'),
        ];

        return 'dummy-password:'.hash('sha256', (string) json_encode($options));
    }
}
