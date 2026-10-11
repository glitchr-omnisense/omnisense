<?php

namespace App\Tests\Unit;

use App\Catalogue\InstallPlan;
use App\Catalogue\PackageVersions;
use PHPUnit\Framework\TestCase;

/**
 * What a repository's tags and branches become in a composer.json: the minor
 * version of the latest release (^1.4), a branch's development version
 * (1.x-dev, 2.0.x-dev, dev-main), the stability a project then needs; and
 * the plan's two outputs, the commands and the JSON block.
 */
class VersionsTest extends TestCase
{
    public function testVersionNumbersAndConstraints(): void
    {
        self::assertSame('1.4.2', PackageVersions::normalize('v1.4.2'));
        self::assertSame('2.0.0-beta.1', PackageVersions::normalize('2.0.0-beta.1'));
        self::assertNull(PackageVersions::normalize('release-candidate'));
        self::assertTrue(PackageVersions::isStable('v1.4.2'));
        self::assertFalse(PackageVersions::isStable('2.0.0-beta.1'));
        self::assertSame('^1.4', PackageVersions::caret('v1.4.2'));
        self::assertSame('^2.0', PackageVersions::caret('2'));

        self::assertSame('1.x-dev', PackageVersions::branchVersion('1.x'));
        self::assertSame('3.0.x-dev', PackageVersions::branchVersion('3.0'));
        self::assertSame('2.x-dev', PackageVersions::branchVersion('2'));
        self::assertSame('dev-main', PackageVersions::branchVersion('main'));
        self::assertSame('dev-feature/x', PackageVersions::branchVersion('feature/x'));

        self::assertTrue(PackageVersions::isDev('1.x-dev'));
        self::assertTrue(PackageVersions::isDev('dev-main'));
        self::assertFalse(PackageVersions::isDev('^1.4'));
        self::assertFalse(PackageVersions::isDev(null));
    }

    public function testTheConstraintByChannel(): void
    {
        $tag = fn (string $name, bool $stable = true): array => ['name' => $name, 'version' => (string) PackageVersions::normalize($name), 'sha' => 'x', 'date' => null, 'stable' => $stable];
        $branch = fn (string $name, bool $default): array => ['name' => $name, 'version' => PackageVersions::branchVersion($name), 'sha' => 'x', 'date' => null, 'default' => $default, 'commits' => []];

        // Released: the minor of the latest stable release; the beta does not count; dev follows the branch developed on.
        $versions = new PackageVersions([$tag('v2.0.0-beta.1', false), $tag('v1.4.2'), $tag('v1.3.0')], [$branch('2.x', false), $branch('1.x', true)], true);
        self::assertSame('^1.4', $versions->constraint('stable'));
        self::assertSame('1.x-dev', $versions->constraint('dev'));
        self::assertSame('v1.4.2', $versions->latestStable()['name']);

        // Never released: both channels take the branch.
        $versions = new PackageVersions([], [$branch('main', true)], true);
        self::assertSame('dev-main', $versions->constraint('stable'));
        self::assertSame('dev-main', $versions->constraint('dev'));

        // Unread repository: the registry's version stands in, when it is a release.
        $versions = new PackageVersions(publishedVersion: 'v0.9.1');
        self::assertSame('^0.9', $versions->constraint('stable'));
        self::assertNull($versions->constraint('dev'), 'no branch known');
        self::assertNull((new PackageVersions(publishedVersion: '1.x-dev'))->constraint('stable'));
        self::assertNull((new PackageVersions())->constraint('stable'));
    }

    public function testThePlanWritesTheCommandsAndTheJson(): void
    {
        $plan = new InstallPlan(
            framework: 'symfony',
            channel: 'stable',
            packages: [],
            require: ['glitchr/omnibase' => '3.x-dev as 3.0.x-dev', 'glitchr/omnitrade' => '^1.4'],
            repositories: ['glitchr/omnibase' => ['type' => 'vcs', 'url' => 'https://github.com/glitchr-studio/omnibase']],
            dev: true,
            plugins: ['glitchr/base-plugin'],
            refused: [],
        );

        self::assertSame([
            'composer config repositories.glitchr-omnibase vcs https://github.com/glitchr-studio/omnibase',
            'composer config minimum-stability dev',
            'composer config prefer-stable true',
            'composer config allow-plugins.glitchr/base-plugin true',
            'composer require "glitchr/omnibase:3.x-dev as 3.0.x-dev" glitchr/omnitrade:^1.4',
        ], $plan->commands());

        $json = json_decode($plan->composerJson(), true);
        self::assertSame([['type' => 'vcs', 'url' => 'https://github.com/glitchr-studio/omnibase']], $json['repositories']);
        self::assertSame(['glitchr/omnibase' => '3.x-dev as 3.0.x-dev', 'glitchr/omnitrade' => '^1.4'], $json['require']);
        self::assertSame('dev', $json['minimum-stability']);
        self::assertTrue($json['prefer-stable']);
        self::assertSame(['glitchr/base-plugin' => true], $json['config']['allow-plugins']);
        self::assertStringContainsString('https://github.com', $plan->composerJson(), 'slashes are not escaped');

        $empty = new InstallPlan('bare', 'stable', [], [], [], false, [], ['glitchr/omnibase']);
        self::assertTrue($empty->isEmpty());
        self::assertSame([], $empty->commands());
        self::assertSame(['require' => []], json_decode($empty->composerJson(), true));
    }
}
