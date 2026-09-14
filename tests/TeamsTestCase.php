<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Tests;

use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

/**
 * The same application with Spatie's teams on.
 *
 * The switch has to be set before the permission tables are made — Spatie's
 * migration reads it as it runs and adds the team column to both pivots — so it
 * is part of the environment rather than something a test flips.
 */
abstract class TeamsTestCase extends TestCase
{
    protected function setUp(): void
    {
        // Eloquent remembers which columns a model may mass-assign, per class and
        // for the whole process. A suite without teams runs first, where the
        // roles table has no team column, and would leave `team_id` silently
        // dropped from every Role created here.
        (new ReflectionProperty(Model::class, 'guardableColumns'))->setValue(null, []);

        parent::setUp();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('permission.teams', true);
    }
}
