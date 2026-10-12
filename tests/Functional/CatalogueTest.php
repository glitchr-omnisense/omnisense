<?php

namespace App\Tests\Functional;

use App\Catalogue\Synchronizer;
use App\Entity\Follow;
use App\Entity\Package;
use App\Tests\Mock\NoNetwork;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The catalogue: read from the repositories, floor by floor; a page per
 * family - never for a name that is only announced; a page per package with
 * its sheet, its versions and its manual; a member follows a package; the command asks
 * the registries, and keeps what they said when they stop answering.
 */
class CatalogueTest extends SiteTestCase
{
    public function testTheCatalogueFloorByFloor(): void
    {
        $page = $this->client->request('GET', '/packages');

        self::assertSame(['glitchr/omnibase'], $page->filter('#foundation .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['omnibase/blog'], $page->filter('#component .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['glitchr/omnitrade', 'omnitrade/paypal', 'omnitrade/stripe'], $page->filter('#omnitrade .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['@glitchr/flowjs'], $page->filter('#library .om-row-name')->each(fn ($a) => $a->text()));
        self::assertStringNotContainsString('workspace', $page->filter('#content')->text());

        // What each README says of itself, and nothing where it says nothing.
        $facts = fn (string $name) => $page->filter('.om-row')->reduce(fn ($row) => $row->filter('.om-row-name')->text() === $name)->filter('.om-fact')->each(fn ($f) => trim($f->text()));
        self::assertContains('Vérifié en réel', $facts('omnitrade/stripe'));
        self::assertContains('Non vérifié en réel', $facts('omnitrade/paypal'));
        self::assertNotContains('Vérifié en réel', $facts('glitchr/omnitrade'));
        self::assertNotContains('Non vérifié en réel', $facts('glitchr/omnitrade'), 'the table of its providers is not about itself');
        // The fixtures did not ask the registries: the page says so rather than "absent".
        self::assertContains('Packagist n’a pas été interrogé', $facts('glitchr/omnibus'));

        // The names that are only announced are apart, and said to be so.
        $announced = $page->filter('#announced');
        self::assertStringContainsString('Annoncées, pas encore écrites', $announced->text());
        self::assertStringContainsString('Omnidns', $announced->text());
        self::assertStringContainsString('nom réservé', $announced->text());
        self::assertCount(0, $announced->filter('a'), 'nothing to open: there is no package');
    }

    public function testAFamilyAndItsProviders(): void
    {
        $page = $this->client->request('GET', '/families/omnitrade');

        self::assertSame('Omnitrade', $page->filter('h1')->text());
        self::assertStringContainsString('Encaisser et vendre', $page->filter('.om-lead')->text());
        self::assertSame(['glitchr/omnitrade', 'omnitrade/paypal', 'omnitrade/stripe'], $page->filter('.om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame('/docs/glitchr/omnitrade/current', $page->filter('.om-actions a')->first()->attr('href'));
        self::assertCount(2, $page->filter('.om-tier'), 'the family, its providers - and nothing of the sites that use it');
    }

    public function testAFamilyInReserveIsNotAFamily(): void
    {
        foreach (['/families/omnidns', '/families/omnisign', '/families/omniticket', '/families/omnilex'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path.': no repository, no page');
        }
        $this->client->request('GET', '/packages/glitchr/omnisign');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAPackageItsSheetAndItsManual(): void
    {
        $page = $this->client->request('GET', '/packages/glitchr/omnitrade');

        self::assertSame('glitchr/omnitrade', $page->filter('h1')->text());
        self::assertSame('composer require glitchr/omnitrade', $page->filter('.om-install code')->text());
        $sheet = $page->filter('.om-sheet')->text();
        self::assertStringContainsString('LGPL-3.0-or-later', $sheet);
        self::assertStringContainsString('github.com/glitchr-studio/omnitrade', $sheet);
        self::assertStringContainsString('Son README ne le dit pas', $sheet);

        // Its manual: the README as the overview, then the pages of docs/, each linked to its page.
        self::assertSame(['Présentation', 'Installation', 'Webhooks'], $page->filter('#manual .om-contents a')->each(fn ($a) => $a->text()));
        self::assertSame('/docs/glitchr/omnitrade/current/webhooks', $page->filter('#manual .om-contents a')->last()->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade/current', $page->filter('.om-actions a')->first()->attr('href'));
        self::assertStringContainsString('3 pages', $page->filter('#manual')->text(), 'the README and the two pages of docs/');

        // Who requires it.
        self::assertSame(['omnitrade/paypal', 'omnitrade/stripe'], $page->filter('.om-tier .om-names a')->each(fn ($a) => $a->text()));

        // Its versions, read in its repository: the constraints each channel takes, the releases, the branches and their last commits.
        $versions = $page->filter('#versions');
        self::assertSame(['^1.4', '1.x-dev'], $versions->filter('.om-constraints code')->each(fn ($c) => $c->text()));
        self::assertSame(['v2.0.0-beta.1', 'v1.4.2', 'v1.4.1', 'v1.3.0'], $versions->filter('.om-tags code')->each(fn ($c) => $c->text()), 'newest first');
        self::assertSame(['1.x', '2.x'], $versions->filter('.om-branch-head > code:first-child')->each(fn ($c) => $c->text()), 'the branch developed on first');
        self::assertSame(['1.x-dev', '2.x-dev'], $versions->filter('.om-branch-version')->each(fn ($c) => $c->text()));
        self::assertStringContainsString('branche suivie', $versions->filter('.om-branches > li')->first()->text());
        self::assertCount(3, $versions->filter('.om-branches > li')->first()->filter('.om-commits li'));
        self::assertSame('/install?framework=bare&p%5B0%5D=glitchr/omnitrade', $versions->filter('a.om-more')->attr('href'));

        // A provider: the sentence its README says, quoted as such.
        $page = $this->client->request('GET', '/packages/omnitrade/paypal');
        self::assertStringContainsString('Non vérifié en réel', $page->filter('.om-sheet')->text());
        self::assertStringContainsString('it has not yet been run against a real sandbox', $page->filter('.om-quote')->text());
        self::assertStringContainsString('Extrait de son README', $page->filter('.om-quote')->text());
        self::assertStringContainsString('son manuel est son README', $page->filter('#manual')->text());

        // An npm library, under its name without the "@"; a package with nothing to read says so.
        $page = $this->client->request('GET', '/packages/glitchr/flowjs');
        self::assertSame('npm install @glitchr/flowjs', $page->filter('.om-install code')->text());
        self::assertCount(0, $page->filter('#versions a.om-more'), 'an npm library is not installed by Composer');
        $page = $this->client->request('GET', '/packages/omnibus/offline');
        self::assertStringContainsString('ni dossier docs/ ni README', $page->filter('#manual')->text());
        // A package whose repository could not be read says so, and nothing is made up.
        self::assertStringContainsString('n’a pas pu être lu', $page->filter('#versions')->text());
        self::assertCount(0, $page->filter('#versions .om-tags, #versions .om-branches'));
    }

    public function testAMemberFollowsAPackage(): void
    {
        // Signed out: the button leads to the sign-in, the account is closed.
        $page = $this->client->request('GET', '/packages/glitchr/omnibus');
        self::assertSame('/login', $page->filter('.om-actions a')->last()->attr('href'));
        $this->client->request('GET', '/account');
        self::assertResponseRedirects();

        $this->signIn('lecteur');
        $package = $this->em->getRepository(Package::class)->findOneBy(['name' => 'glitchr/omnibus']);
        $this->em->getRepository(Follow::class)->createQueryBuilder('f')->delete()->where('f.package = :p')->setParameter('p', $package)->getQuery()->execute();

        $page = $this->client->request('GET', '/packages/glitchr/omnibus');
        self::assertSame('false', $page->filter('.om-follow button')->attr('aria-pressed'));
        $this->client->submit($page->filter('.om-follow')->form());
        self::assertResponseRedirects('/packages/glitchr/omnibus');
        $page = $this->client->followRedirect();
        self::assertSame('true', $page->filter('.om-follow button')->attr('aria-pressed'));

        // The account lists what is followed: the fixtures' omnitrade and this one.
        $page = $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade'], $page->filter('.om-follows .om-row-name')->each(fn ($a) => $a->text()));

        // Stop following, from the account.
        $this->client->submit($page->filter('.om-follows li')->first()->filter('form')->form());
        self::assertResponseRedirects('/account');
        self::assertSame(['glitchr/omnitrade'], $this->client->followRedirect()->filter('.om-follows .om-row-name')->each(fn ($a) => $a->text()));

        // Without its token, the request is refused.
        $this->client->request('POST', '/packages/glitchr/omnibus/follow', ['_token' => 'nope']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheCommandAsksTheRegistriesAndKeepsWhatTheySaidWhenTheyAreSilent(): void
    {
        $command = new CommandTester((new Application(static::$kernel))->find('app:packages:sync'));
        $package = fn (string $name): Package => $this->em->getRepository(Package::class)->findOneBy(['name' => $name]);

        // Packagist knows omnitrade, not its PayPal provider; npm knows flowjs; the rest gets no answer.
        NoNetwork::$answers = [
            'https://packagist.org/packages/glitchr/omnitrade.json' => [['package' => ['versions' => ['1.x-dev' => []], 'downloads' => ['total' => 13]]]],
            'https://packagist.org/packages/omnitrade/paypal.json' => [['status' => 'error'], 404],
            'https://registry.npmjs.org/@glitchr%2Fflowjs' => [['dist-tags' => ['latest' => '1.0.0']]],
            'https://api.npmjs.org/downloads/point/last-month/@glitchr/flowjs' => [['downloads' => 750]],
        ];
        self::assertSame(0, $command->execute(['--fresh' => true]));
        self::assertStringContainsString('3 answered, 5 unreachable', $command->getDisplay());
        self::assertContains('GET https://packagist.org/packages/omnitrade/stripe.json', NoNetwork::$requests);
        $this->em->clear();

        self::assertTrue($package('glitchr/omnitrade')->isPublished());
        self::assertSame(['1.x-dev', 13], [$package('glitchr/omnitrade')->getPublishedVersion(), $package('glitchr/omnitrade')->getDownloads()]);
        self::assertFalse($package('omnitrade/paypal')->isPublished());
        self::assertSame('1.0.0', $package('@glitchr/flowjs')->getPublishedVersion());
        self::assertNull($package('glitchr/omnibus')->isPublished(), 'no answer: unknown, not absent');

        // The pages say the three states apart.
        $page = $this->client->request('GET', '/packages/omnitrade/paypal');
        self::assertStringContainsString('n’est pas encore sur Packagist', $page->filter('.om-install-note')->text());
        self::assertStringContainsString('13 téléchargements', $this->client->request('GET', '/packages/glitchr/omnitrade')->filter('.om-sheet')->text());

        // The network goes: what was known stays.
        NoNetwork::reset();
        self::assertSame(0, $command->execute(['--fresh' => true]));
        self::assertStringContainsString('0 answered, 8 unreachable', $command->getDisplay());
        $this->em->clear();
        self::assertTrue($package('glitchr/omnitrade')->isPublished());
        self::assertFalse($package('omnitrade/paypal')->isPublished());

        // --offline asks nobody.
        NoNetwork::reset();
        self::assertSame(0, $command->execute(['--offline' => true]));
        self::assertSame([], NoNetwork::$requests);

        // Back to what the fixtures left: the registries never asked.
        $this->em->createQuery('UPDATE '.Package::class.' p SET p.published = NULL, p.publishedVersion = NULL, p.downloads = NULL, p.registryCheckedAt = NULL')->execute();
    }

    public function testAPackageWhoseRepositoryIsGoneLeavesTheCatalogue(): void
    {
        $ghost = new Package('omnitrade/ghost', \App\Catalogue\Tier::Provider, 'omnitrade');
        $this->em->persist($ghost);
        $this->em->flush();
        $this->client->request('GET', '/packages/omnitrade/ghost');
        self::assertResponseIsSuccessful();

        $report = static::getContainer()->get(Synchronizer::class)->sync(registries: false);

        self::assertSame(['omnitrade/ghost'], $report->missing);
        self::assertSame(8, $report->total());
        $this->client->request('GET', '/packages/omnitrade/ghost');
        self::assertResponseStatusCodeSame(404);

        $this->em->remove($this->em->getRepository(Package::class)->findOneBy(['name' => 'omnitrade/ghost']));
        $this->em->flush();
    }
}
