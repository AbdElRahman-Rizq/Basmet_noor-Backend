<?php

use App\Models\Branch;
use App\Models\ConsentType;
use App\Models\FunctionalScale;
use App\Models\ReinforcerCatalog;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('scenario 1: migrate:fresh and db:seed succeed from scratch on the test database', function () {
    // RefreshDatabase already ran `migrate:fresh` for this process; seeding from
    // an empty schema proves the full install path. The run is transactional so
    // it cannot leak reference rows into other tests.
    expect(DB::selectOne('select current_database() as db')->db)->toBe('basma_noor_test');
    expect(DB::table('migrations')->count())->toBe(19);

    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(5)
        ->and(Branch::count())->toBe(2)
        ->and(ConsentType::count())->toBe(3)
        ->and(FunctionalScale::count())->toBe(10)
        ->and(ReinforcerCatalog::count())->toBe(8);
});
