<?php

use App\Models\Concerns\HasVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class VisibilityFixture extends Model
{
    use HasVisibility;

    protected $table = 'visibility_fixtures';

    public $timestamps = false;

    public function visibilityOwnerColumn(): ?string
    {
        return 'account_manager_id';
    }
}

beforeEach(function () {
    Schema::create('visibility_fixtures', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('office_id')->nullable();
        $table->unsignedBigInteger('account_manager_id')->nullable();
    });

    DB::table('visibility_fixtures')->insert([
        ['name' => 'office 1 / manager A', 'office_id' => 1, 'account_manager_id' => null],
        ['name' => 'office 1 / manager B', 'office_id' => 1, 'account_manager_id' => null],
        ['name' => 'office 2 / manager A', 'office_id' => 2, 'account_manager_id' => null],
        ['name' => 'office 2 / manager B', 'office_id' => 2, 'account_manager_id' => null],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('visibility_fixtures');
});

function buildVisibilityFixtures(): array
{
    $managerA = User::factory()->accountManager()->create();
    $managerB = User::factory()->accountManager()->create();

    DB::table('visibility_fixtures')->where('id', 1)->update(['account_manager_id' => $managerA->id]);
    DB::table('visibility_fixtures')->where('id', 2)->update(['account_manager_id' => $managerB->id]);
    DB::table('visibility_fixtures')->where('id', 3)->update(['account_manager_id' => $managerA->id]);
    DB::table('visibility_fixtures')->where('id', 4)->update(['account_manager_id' => $managerB->id]);

    return [$managerA, $managerB];
}

function visibleNames(User $user): array
{
    return VisibilityFixture::query()->visibleTo($user)->orderBy('id')->pluck('name')->all();
}

test('the owner and unrestricted finance see every office', function () {
    [$managerA, $managerB] = buildVisibilityFixtures();

    $owner = User::factory()->owner()->create();
    $finance = User::factory()->finance()->create();

    expect(visibleNames($owner))->toHaveCount(4)
        ->and(visibleNames($finance))->toHaveCount(4);

    expect($managerA)->not->toBeNull()
        ->and($managerB)->not->toBeNull();
});

test('an office restriction filters every model that uses the scope', function () {
    buildVisibilityFixtures();

    $restrictedFinance = User::factory()->finance()->forOffice(1)->create();

    expect(visibleNames($restrictedFinance))->toBe([
        'office 1 / manager A',
        'office 1 / manager B',
    ]);
});

test('an account manager only sees their own rows', function () {
    [$managerA, $managerB] = buildVisibilityFixtures();

    expect(visibleNames($managerA))->toBe([
        'office 1 / manager A',
        'office 2 / manager A',
    ])->and(visibleNames($managerB))->toBe([
        'office 1 / manager B',
        'office 2 / manager B',
    ]);
});

test('an account manager restricted to an office sees the intersection', function () {
    [$managerA] = buildVisibilityFixtures();
    $managerA->forceFill(['office_id' => 1])->save();

    expect(visibleNames($managerA))->toBe(['office 1 / manager A']);
});

test('the user model itself uses the office scope', function () {
    $restricted = User::factory()->finance()->forOffice(1)->create();
    User::factory()->finance()->forOffice(2)->create();

    $ids = User::query()->visibleTo($restricted)->pluck('id')->all();

    expect($ids)->toContain($restricted->id)
        ->and($ids)->toHaveCount(1);
});
