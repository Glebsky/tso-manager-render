<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Sources;

use App\Services\Game\Production\Sources\BuffGroupRecipeSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BuffGroupRecipeSourceTest extends TestCase
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $buffPool = [
        'Group5Buff1' => ['name' => 'Group5Buff1', 'group' => 5],
        'Group5Buff2' => ['name' => 'Group5Buff2', 'group' => 5],
        'Group11Buff1' => ['name' => 'Group11Buff1', 'group' => 11],
        'Group11Buff2' => ['name' => 'Group11Buff2', 'group' => 11],
        'OtherBuff' => ['name' => 'OtherBuff', 'group' => 20],
    ];

    public function test_requires_integer_group_option(): void
    {
        $source = new BuffGroupRecipeSource($this->buffPool);
        $this->expectException(InvalidArgumentException::class);
        $source->recipesFor(6, []);
    }

    public function test_filters_buffs_by_group(): void
    {
        $source = new BuffGroupRecipeSource($this->buffPool);

        $group5Recipes = $source->recipesFor(6, ['group' => 5]);
        $this->assertCount(2, $group5Recipes);
        $this->assertSame(['Group5Buff1', 'Group5Buff2'], array_column($group5Recipes, 'name'));

        $group11Recipes = $source->recipesFor(11, ['group' => 11]);
        $this->assertCount(2, $group11Recipes);
        $this->assertSame(['Group11Buff1', 'Group11Buff2'], array_column($group11Recipes, 'name'));
    }
}
