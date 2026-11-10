<?php

namespace App\Tests\Functional;

use App\Twig\CatalogueExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** A manual's link to another package of its family, written as the family's workspace lays them out. */
final class WorkspaceLinksTest extends KernelTestCase
{
    public function testALinkToTheFamilysCoreGoesToItsManual(): void
    {
        self::bootKernel();
        $links = self::getContainer()->get(CatalogueExtension::class);

        // omnitrade/stripe's "../../core/docs/index.md": omnitrade/core beside it, the family's own package.
        // (the page's language: /docs or /fr/docs, as the router's context says)
        self::assertMatchesRegularExpression('#^<a href="(?:/fr)?/docs/omnitrade/1\.x\#webhooks">$#', $links->workspaceLinks('<a href="../../core/docs/index.md#webhooks">', 'omnitrade/stripe'));
        // A sibling the catalogue does not document, or a package of no family: as written.
        self::assertSame('<a href="../../paypal/docs/index.md">', $links->workspaceLinks('<a href="../../paypal/docs/index.md">', 'omnitrade/stripe'));
        self::assertSame('<a href="../../core/docs/index.md">', $links->workspaceLinks('<a href="../../core/docs/index.md">', 'nobody/nothing'));
    }
}
