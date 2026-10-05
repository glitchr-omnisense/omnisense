<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Mock\FakeTypesense;
use App\Tests\Mock\NoNetwork;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What the functional tests share: a browser on https://localhost, no
 * network, Typesense replaced by its double, and the fixtures' accounts.
 * The test database holds the fixtures (SiteFixtures): the catalogue read
 * from tests/Fixtures/repos, three announcements, three accounts.
 */
abstract class SiteTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'localhost', 'HTTPS' => true]);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        NoNetwork::reset();
        FakeTypesense::reset();
    }

    protected function user(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertNotNull($user, 'the fixtures\' account "'.$username.'"');

        return $user;
    }

    protected function signIn(string $username): void
    {
        $this->client->loginUser($this->user($username), 'common');
    }
}
