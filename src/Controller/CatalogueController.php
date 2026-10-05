<?php

namespace App\Controller;

use App\Catalogue\Families;
use App\Catalogue\Sites;
use App\Catalogue\Tier;
use App\Entity\Follow;
use App\Entity\Package;
use App\Entity\User;
use App\Repository\FollowRepository;
use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\ManualRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The catalogue: every package `app:packages:sync` found, by floor; a page
 * per family (its own package, its providers); a page per package, with
 * what its repository says of it and the contents of its manual.
 */
class CatalogueController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packages,
        private readonly ManualRegistry $manuals,
    ) {
    }

    #[Route('/packages', name: 'app_packages')]
    public function index(): Response
    {
        $families = Families::ordered($this->packages->findFamilies());
        unset($families['omnibase']);

        $providers = [];
        foreach ($this->packages->findByTier(Tier::Provider) as $provider) {
            $providers[(string) $provider->getFamily()][] = $provider;
        }

        return $this->render('client/catalogue/index.html.twig', [
            'foundation' => $this->packages->findByTier(Tier::Foundation),
            'components' => $this->packages->findByTier(Tier::Component),
            'families' => $families,
            'providers' => $providers,
            'libraries' => $this->packages->findByTier(Tier::Library),
            'announced' => Families::announced(array_keys($this->packages->findFamilies())),
            'counts' => $this->packages->counts(),
            'synced' => $this->packages->lastSync(),
        ]);
    }

    #[Route('/families/{family}', name: 'app_family', requirements: ['family' => 'omni[a-z]+'])]
    public function family(string $family): Response
    {
        $members = $this->packages->findFamily($family);
        // A family exists when its own package was found - never because its name is announced.
        $own = array_values(array_filter($members, static fn (Package $p): bool => \in_array($p->getTier(), [Tier::Family, Tier::Foundation], true)))[0] ?? null;
        if (null === $own) {
            throw $this->createNotFoundException(sprintf('No family "%s".', $family));
        }

        $sites = [];
        foreach ($members as $member) {
            foreach ($member->getUsedBy() as $site) {
                if (null !== Sites::get($site)) {
                    $sites[$site] = Sites::get($site);
                }
            }
        }
        ksort($sites);

        return $this->render('client/catalogue/family.html.twig', [
            'family' => $family,
            'suffix' => Families::suffix($family),
            'own' => $own,
            'members' => array_values(array_filter($members, static fn (Package $p): bool => $p !== $own)),
            'sites' => $sites,
            'manual' => $this->manuals->get($own->getHandle()),
        ]);
    }

    #[Route('/packages/{vendor}/{name}', name: 'app_package', requirements: ['vendor' => '[a-z0-9][a-z0-9_.-]*', 'name' => '[a-z0-9][a-z0-9_.-]*'], methods: ['GET'])]
    public function package(string $vendor, string $name, FollowRepository $follows): Response
    {
        $package = $this->packages->findOneByHandle($vendor.'/'.$name);
        if (null === $package) {
            throw $this->createNotFoundException(sprintf('No package "%s/%s".', $vendor, $name));
        }

        $manual = $this->manuals->get($package->getHandle());
        $version = $manual?->getDefaultVersion();
        $pages = null !== $manual && null !== $version ? $this->manuals->pages($manual, $version) : null;

        $requires = array_values(array_filter(array_map(fn (string $required): ?Package => $this->packages->findOneByHandle($required), $package->getRequires())));
        $requiredBy = array_values(array_filter($this->packages->findCatalogue(), static fn (Package $other): bool => \in_array($package->getName(), $other->getRequires(), true)));
        $user = $this->getUser();

        return $this->render('client/catalogue/package.html.twig', [
            'package' => $package,
            'own' => null !== $package->getFamily() ? ($this->packages->findFamilies()[$package->getFamily()] ?? null) : null,
            'manual' => $manual,
            'version' => $version,
            'tree' => $pages?->getTree() ?? [],
            'home' => $pages?->get(''),
            'pages' => null !== $pages ? \count($pages->getAllPages()) : 0,
            'requires' => $requires,
            'required_by' => $requiredBy,
            'sites' => array_values(array_filter(array_map(static fn (string $site): ?array => Sites::get($site), $package->getUsedBy()))),
            'following' => $user instanceof User && null !== $follows->findOneFor($user, $package),
            'followers' => $follows->countFor($package),
        ]);
    }

    /** Follow a package, or stop: the button of its page. */
    #[Route('/packages/{vendor}/{name}/follow', name: 'app_package_follow', requirements: ['vendor' => '[a-z0-9][a-z0-9_.-]*', 'name' => '[a-z0-9][a-z0-9_.-]*'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function follow(string $vendor, string $name, Request $request, FollowRepository $follows, EntityManagerInterface $em): Response
    {
        $package = $this->packages->findOneByHandle($vendor.'/'.$name);
        if (null === $package) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('follow-'.$package->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $follow = $follows->findOneFor($user, $package);
        if (null !== $follow) {
            $em->remove($follow);
        } else {
            $em->persist(new Follow($user, $package));
        }
        $em->flush();

        return $this->redirectToRoute('account' === $request->request->get('back') ? 'app_account' : 'app_package', 'account' === $request->request->get('back') ? [] : ['vendor' => $vendor, 'name' => $name], Response::HTTP_SEE_OTHER);
    }
}
