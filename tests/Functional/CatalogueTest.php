<?php

namespace App\Tests\Functional;

use App\Catalogue\Synchronizer;
use App\Catalogue\Tier;
use App\Entity\Follow;
use App\Entity\Package;
use App\Tests\Mock\NoNetwork;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The catalogue: read from the repositories, searched by name, family and
 * kind; a renamed family's old address kept; a page per family - never for
 * a name that is only announced; a page per package with
 * its sheet, its versions and its manual; a member follows a package; the command asks
 * the registries, and keeps what they said when they stop answering.
 */
class CatalogueTest extends SiteTestCase
{
    public function testTheSearchFindsEveryPackageByNameFamilyAndKind(): void
    {
        $visible = fn ($page): array => $page->filter('.om-hit:not([hidden]) .om-hit-name')->each(fn ($a) => $a->text());

        // Nothing typed: every package, in the order of the floors - the core, the families, the prepared structures, the providers, the libraries.
        $page = $this->client->request('GET', '/packages');
        self::assertResponseIsSuccessful();
        self::assertSame('Chercher un paquet', $page->filter('h1')->text());
        self::assertSame(['glitchr/omnibase', 'glitchr/omnibus', 'glitchr/omnitrade', 'omnibase/blog', 'omnibus/offline', 'omnitrade/paypal', 'omnitrade/stripe', '@glitchr/flowjs'], $visible($page));
        self::assertSame('8 paquets', trim($page->filter('.om-finder-count')->text()));
        self::assertStringNotContainsString('workspace', $page->filter('#content')->text());
        // The filters: a family, a kind.
        self::assertSame(['', 'omnibase', 'omnibus', 'omnitrade'], $page->filter('select[name="family"] option')->each(fn ($o) => $o->attr('value')));
        self::assertSame(['', 'core', 'family', 'bridge', 'structure', 'provider', 'library'], $page->filter('input[name="kind"]')->each(fn ($i) => $i->attr('value')));

        // A provider found by its name: the exact short name first; the rows that do not answer are sent hidden, for the script.
        $page = $this->client->request('GET', '/packages?q=Stripe');
        self::assertSame(['omnitrade/stripe'], $visible($page));
        self::assertCount(7, $page->filter('.om-hit[hidden]'));
        self::assertSame('1 paquet', trim($page->filter('.om-finder-count')->text()));
        // ...and by its family: the family's word, or the family filter.
        $page = $this->client->request('GET', '/packages?q=omnitrade');
        self::assertSame(['glitchr/omnitrade', 'omnitrade/paypal', 'omnitrade/stripe'], $visible($page));
        $page = $this->client->request('GET', '/packages?family=omnitrade&kind=provider');
        self::assertSame(['omnitrade/paypal', 'omnitrade/stripe'], $visible($page));
        self::assertSame('omnitrade', $page->filter('select[name="family"] option[selected]')->attr('value'));
        self::assertSame('provider', $page->filter('input[name="kind"]:checked')->attr('value'));
        // Every word has to be found; accents do not count.
        self::assertSame(['omnitrade/stripe'], $visible($this->client->request('GET', '/packages?q=stripe+r%C3%A9fund')));
        self::assertSame([], $visible($page = $this->client->request('GET', '/packages?q=stripe+parcel')));
        self::assertStringContainsString('Aucun paquet ne répond', $page->filter('.om-finder-count')->text());
        // A prepared structure is a kind of its own.
        self::assertSame(['omnibase/blog'], $visible($this->client->request('GET', '/packages?kind=structure')));
        // An unknown filter is no filter.
        self::assertCount(8, $this->client->request('GET', '/packages?family=omninope&kind=nope')->filter('.om-hit:not([hidden])'));

        // A row: its name, its kind, its family, what its README says when it says it was not tested live, its links.
        $page = $this->client->request('GET', '/packages?q=paypal');
        $row = $page->filter('.om-hit:not([hidden])');
        self::assertSame('Fournisseur', $row->filter('.om-kind')->text());
        self::assertSame('omnitrade', $row->filter('.om-hit-family')->text());
        self::assertSame('Non testé en réel', $row->filter('.om-fact.is-unverified')->text());
        self::assertSame('/packages/omnitrade/paypal', $row->filter('.om-hit-name')->attr('href'));
        self::assertSame(['Manuel'], $row->filter('.om-hit-links a')->each(fn ($a) => $a->text()), 'the fixtures never asked Packagist, and paypal has no repository url');
        $stripe = $this->client->request('GET', '/packages?q=stripe')->filter('.om-hit:not([hidden])');
        self::assertCount(0, $stripe->filter('.om-fact.is-unverified'), 'its README says it was verified');
        $trade = $this->client->request('GET', '/packages?q=omnitrade&kind=family')->filter('.om-hit:not([hidden])');
        self::assertSame(['GitHub', 'Manuel'], $trade->filter('.om-hit-links a')->each(fn ($a) => $a->text()));
        self::assertSame('https://github.com/glitchr-studio/omnitrade', $trade->filter('.om-hit-links a')->first()->attr('href'));

        // The same search as data, for the install selector's provider pick.
        $this->client->request('GET', '/packages.json?family=omnitrade&kind=provider&q=pa');
        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['omnitrade/paypal'], array_column($json, 'name'));
        self::assertSame(['paypal', 'omnitrade', 'provider'], [$json[0]['short'], $json[0]['family'], $json[0]['kind']]);
        self::assertSame([], NoNetwork::$requests);
    }

    /** omniscient/ takes over from glitchr/: the old address follows the package once only the new name is in the catalogue. */
    public function testARenamedFamilyKeepsItsOldAddress(): void
    {
        $new = new Package('omniscient/omnibus', Tier::Family, 'omnibus');
        $this->em->persist($new);
        $this->em->flush();

        // Both names in the catalogue: the old page is still the old package's.
        $this->client->request('GET', '/packages/glitchr/omnibus');
        self::assertResponseIsSuccessful();

        $old = $this->em->getRepository(Package::class)->findOneBy(['name' => 'glitchr/omnibus']);
        $old->setMissing(true);
        $this->em->flush();
        $this->client->request('GET', '/packages/glitchr/omnibus?from=here');
        self::assertResponseRedirects('/packages/omniscient/omnibus?from=here', 301);
        $this->client->request('GET', '/packages/omniscient/omnibus');
        self::assertResponseIsSuccessful();
        // A name never written stays a 404; a manual still under its old name is not moved.
        $this->client->request('GET', '/packages/glitchr/omninope');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/docs/glitchr/omnitrade');
        self::assertResponseRedirects('/docs/glitchr/omnitrade/current');

        $repository = $this->em->getRepository(Package::class);
        $repository->findOneBy(['name' => 'glitchr/omnibus'])->setMissing(false);
        $this->em->remove($repository->findOneBy(['name' => 'omniscient/omnibus']));
        $this->em->flush();
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
        self::assertSame('/install?framework=bare&need=family&family=omnitrade&providers=', $versions->filter('a.om-more')->attr('href'));

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
        $ghost = new Package('omnitrade/ghost', Tier::Provider, 'omnitrade');
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
