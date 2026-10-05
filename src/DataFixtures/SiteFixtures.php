<?php

namespace App\DataFixtures;

use App\Catalogue\Synchronizer;
use App\Entity\Follow;
use App\Entity\Package;
use App\Entity\User;
use Base\Blog\Entity\Post;
use Base\Enum\ThreadState;
use Base\Service\SettingBagInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The site as it starts - development and tests only:
 *
 *   glitchr     / glitchr      Glitch Art, who runs the site (super-admin)
 *   omniscient  / omniscient   who writes the announcements (admin)
 *   lecteur     / lecteur      a member, who follows a package
 *
 * THE CATALOGUE IS NOT WRITTEN HERE: it is read from the repositories, as
 * `app:packages:sync --offline` reads it (the mounted repositories in
 * development, tests/Fixtures/repos in the tests). The registries are not
 * asked: run `app:packages:sync` for that.
 *
 * The announcements are the site's own texts, about what the repositories
 * hold on the day they are dated.
 *
 * Loaded again (--append), nothing is doubled.
 */
class SiteFixtures extends Fixture
{
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly SettingBagInterface $settings,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $glitchr = $this->user($manager, 'glitchr', 'support@glitchr.io', ['ROLE_SUPERADMIN']);
        $this->user($manager, 'omniscient', 'omniscient@example.org', ['ROLE_ADMIN']);
        $reader = $this->user($manager, 'lecteur', 'lecteur@example.org', ['ROLE_USER']);
        $manager->flush();

        $this->synchronizer->sync(registries: false);

        $this->announcements($manager, $glitchr);
        $this->follow($manager, $reader, 'glitchr/omnitrade');
        $manager->flush();

        try {
            $this->settings->set('base.settings.title', 'Omniscient');
        } catch (\Throwable) {
            // No settings table yet: the title comes from the configuration.
        }
    }

    private function announcements(ObjectManager $manager, User $author): void
    {
        foreach ([
            [
                'les-manuels-se-lisent-dans-les-depots', 'Les manuels se lisent dans les dépôts',
                'Un manuel par paquet, une version par branche, et un lien vers le fichier sur chaque page.',
                'La documentation de chaque paquet est le dossier docs/ de son dépôt, tel qu’il y est écrit. Rien n’est recopié ici.',
                0, true,
                [
                    ['paragraph', 'Chaque paquet du catalogue a son manuel : les fichiers Markdown du dossier <code>docs/</code> de son dépôt, et son README en page d’accueil. Ce site les rend, il ne les garde pas : une page corrigée dans un dépôt est corrigée ici à la lecture suivante.'],
                    ['header', 'Une version par branche'],
                    ['paragraph', 'La branche en cours d’un dépôt est lue sur place. Les autres branches de version (<code>1.x</code>, <code>2.x</code>) sont copiées par la commande <code>wikidoc:sync</code>, et le menu des versions mène à la même page dans chacune.'],
                    ['header', 'Chercher'],
                    ['paragraph', 'La recherche couvre tous les manuels. Quand le moteur Typesense tourne, il répond, fautes de frappe comprises ; quand il est éteint, l’index des pages répond à sa place, et la page le dit.'],
                    ['header', 'Corriger'],
                    ['paragraph', 'Il n’y a pas d’éditeur en ligne. Chaque page porte un lien « Modifier cette page » vers son fichier sur GitHub : la correction passe par le dépôt, comme le code.'],
                ],
            ],
            [
                'les-familles-tournent-sans-cadre', 'Les familles tournent sans cadre',
                'Une famille omni* ne requiert plus rien d’un framework : elle s’installe dans un script PHP nu.',
                'Les familles ne dépendent ni de Symfony ni du socle. Leur pont Symfony reste dans le paquet, en option.',
                1, false,
                [
                    ['paragraph', 'Une famille pose une question à plusieurs services. Rien de cela ne demande un framework : le paquet du cœur requiert seulement les contrats du client HTTP, un fournisseur qui appelle une API requiert le client HTTP et accepte celui qu’on lui passe.'],
                    ['header', 'Ce qui change dans un dépôt'],
                    ['list', ['Le fichier <code>docs/installation.md</code> s’ouvre sur l’usage en PHP nu ; <code>docs/symfony.md</code> vient après.', 'Un test « sans cadre » monte le registre à la main et vérifie qu’aucune classe du framework n’est chargée.', 'Le pont Symfony reste dans le paquet du cœur, sous <code>Bridge/Symfony</code> ; ses composants ne sont pas requis.']],
                    ['paragraph', 'Le socle omnibase, lui, reste fait pour Symfony : c’est ce qui sépare les deux bâtiments de la page d’accueil.'],
                ],
            ],
            [
                'omnimeet-la-visio-a-sa-famille', 'Omnimeet : la visio a sa famille',
                'Ouvrir une salle et y faire entrer quelqu’un : un contrat, deux passerelles pour commencer.',
                'glitchr/omnimeet, omnimeet/direct et omnimeet/jitsi sont sur GitHub, branche 1.x.',
                1, false,
                [
                    ['paragraph', 'Le moteur de visio d’<code>omnibase/office</code> est sorti du bundle : il est devenu une famille. <code>glitchr/omnimeet</code> porte le contrat — ouvrir une salle, y faire entrer un participant, la fermer, lire son état —, et chaque passerelle dit ce qu’elle sait faire.'],
                    ['header', 'Deux passerelles'],
                    ['list', ['<code>omnimeet/direct</code> : de navigateur à navigateur, pour deux personnes, sans service tiers ni enregistrement. C’est la passerelle par défaut et celle des tests.', '<code>omnimeet/jitsi</code> : une instance Jitsi, à plusieurs, dans un cadre intégré.']],
                    ['paragraph', 'Ce que chaque passerelle a de vérifié contre un vrai service est écrit dans son README, et repris tel quel sur sa fiche du catalogue.'],
                ],
            ],
        ] as [$slug, $title, $headline, $excerpt, $daysAgo, $featured, $blocks]) {
            if ($manager->getRepository(Post::class)->findOneBy(['slug' => $slug])) {
                continue;
            }
            $post = new Post($author, null, $title);
            $post->setSlug($slug);
            $post->setHeadline($headline);
            $post->setExcerpt($excerpt);
            $post->setContent($this->editor($blocks));
            $post->setFeatured($featured);
            $post->setCommentsOpen(false);
            $post->setState(ThreadState::PUBLISH);
            $post->setPublishedAt(new \DateTime(\sprintf('-%d days', $daysAgo)));
            $manager->persist($post);
        }
    }

    private function follow(ObjectManager $manager, User $user, string $name): void
    {
        $package = $manager->getRepository(Package::class)->findOneBy(['name' => $name]);
        if (null === $package || null !== $manager->getRepository(Follow::class)->findOneBy(['user' => $user, 'package' => $package])) {
            return;
        }
        $manager->persist(new Follow($user, $package));
    }

    /** @param list<array{0: string, 1: string|list<string>}> $blocks */
    private function editor(array $blocks): string
    {
        return json_encode(['time' => 1790000000000, 'version' => '2.30.0', 'blocks' => array_map(static fn (array $block, int $i) => ['id' => 'fx'.$i, 'type' => $block[0], 'data' => match ($block[0]) {
            'header' => ['text' => $block[1], 'level' => 2],
            'list' => ['style' => 'unordered', 'items' => $block[1]],
            'quote' => ['text' => $block[1], 'caption' => ''],
            default => ['text' => $block[1]],
        }], $blocks, array_keys($blocks))], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $roles */
    private function user(ObjectManager $manager, string $username, string $email, array $roles): User
    {
        $user = $manager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($user) {
            return $user;
        }
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        // Development only: change it at first sign-in (/settings).
        $user->setPlainPassword($username);
        $user->setRoles($roles);
        $user->verify();
        $manager->persist($user);

        return $user;
    }
}
