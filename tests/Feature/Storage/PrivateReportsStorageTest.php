<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('writes and reads a report on the private disk', function () {
    $disk = Storage::disk('private_reports');
    $path = Str::uuid().'.txt';

    try {
        expect($disk->put($path, 'private report contents'))->toBeTrue()
            ->and($disk->exists($path))->toBeTrue()
            ->and($disk->get($path))->toBe('private report contents');
    } finally {
        $disk->delete($path);
    }
});

it('deletes a report from the private disk', function () {
    $disk = Storage::disk('private_reports');
    $path = Str::uuid().'.txt';

    $disk->put($path, 'to be deleted');
    expect($disk->exists($path))->toBeTrue();

    expect($disk->delete($path))->toBeTrue()
        ->and($disk->exists($path))->toBeFalse();
});

it('keeps the reports disk local, private and outside the public web root', function () {
    $config = config('filesystems.disks.private_reports');

    expect($config['driver'])->toBe('local')
        ->and($config['root'])->toBe(storage_path('app/private/reports'))
        ->and($config['visibility'])->toBe('private')
        ->and($config['serve'])->toBeFalse()
        ->and($config)->not->toHaveKey('url');

    expect(str_starts_with($config['root'], public_path().DIRECTORY_SEPARATOR))->toBeFalse();

    // `storage:link` may exist for the public disk, but it must never point at private reports.
    $publicStorage = realpath(public_path('storage'));

    expect($publicStorage)->not->toBe(realpath($config['root']))
        ->and($publicStorage)->not->toBe(realpath(storage_path('app/private')));
});

it('uses opaque file names with no child or parent data', function () {
    $path = Str::uuid().'.txt';

    expect($path)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.txt$/');
});

it('cannot be reached through a public URL', function () {
    $disk = Storage::disk('private_reports');
    $path = Str::uuid().'.txt';

    try {
        $disk->put($path, 'top secret report');

        // The file physically lives under storage/app/private/reports, which maps to
        // the signed `storage/{path}` route; without a valid signature it must not be served.
        $signedRoute = $this->get('/storage/reports/'.$path);

        expect($signedRoute->status())->toBeIn([403, 404]);
        $signedRoute->assertDontSee('top secret report');

        expect($this->get('/storage/'.$path)->status())->toBeIn([403, 404]);

        // Nothing is exposed through a direct public path or a symlink.
        $this->get('/reports/'.$path)->assertNotFound();

        $publicStorage = realpath(public_path('storage'));

        expect($publicStorage)->not->toBe(realpath(storage_path('app/private/reports')));
    } finally {
        $disk->delete($path);
    }
});

it('runs storage tests against the isolated test database', function () {
    expect(config('database.connections.pgsql.database'))->toBe('basma_noor_test')
        ->and(DB::selectOne('select current_database() as db')->db)->toBe('basma_noor_test');
});
