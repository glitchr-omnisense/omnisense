<?php

namespace App\Controller;

use App\Catalogue\Families;
use App\Catalogue\PackageSearch;
use App\Catalogue\Tier;
use App\Catalogue\Versions;
use App\Dto\PackageQuery;
use App\Entity\Follow;
use App\Entity\Package;
use App\Entity\User;
use App\Repository\FollowRepository;
use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\ManualRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The catalogue: the search over every package `app:packages:sync` found; a page
 * per family (its own package, its providers); a page per package, with
 * what its repository says of it - its versions too, read through
 * git/git-bundle - and the contents of its manual. Which sites use a
 * package is not shown (Marco, 2026-10-10).
 */
class CatalogueController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packages,
        private readonly ManualRegistry $manuals,
        private readonly PackageSearch $search,
    ) {
    }

    /**
     * The search (as conda-forge.org/packages): one box over every package,
     * a family and a kind to narrow it, a compact row each. A plain GET form;
     * search_controller.js narrows the same rows as one types.
     */
    #[Route('/packages', name: 'app_packages', methods: ['GET'])]
    public function index(#[MapQueryString] PackageQuery $query = new PackageQuery()): Response
    {
        $kind = null !== Tier::fromKind($query->kind) ? $query->kind : '';
        $families = Families::ordered($this->packages->findFamilies());
        $family = isset($families[$query->family]) ? $query->family : '';
        $all = $this->search->all();
        $found = $this->search->search($query->q, $family ?: null, $kind ?: null);

        return $this->render('client/catalogue/index.html.twig', [
            'query' => $query,
            'family' => $family,
            'kind' => $kind,
            // Every row is sent, the ones that do not answer hidden: the script narrows them again as one types.
            'rows' => [...$found, ...array_values(array_filter($all, static fn (Package $p): bool => !\in_array($p, $found, true)))],
            'order' => array_flip(array_map(static fn (Package $p): string => $p->getName(), $all)),
            'found' => array_flip(array_map(static fn (Package $p): string => $p->getName(), $found)),
            'families' => $families,
            'kinds' => array_map(static fn (Tier $t): string => $t->kind(), PackageSearch::ORDER),
            'counts' => $this->packages->counts(),
            'synced' => $this->packages->lastSync(),
        ]);
    }

    /** The same search as data: what the install selector's provider pick asks as one types. */
    #[Route('/packages.json', name: 'app_packages_json', methods: ['GET'])]
    public function data(#[MapQueryString] PackageQuery $query = new PackageQuery()): JsonResponse
    {
        $found = \array_slice($this->search->search($query->q, $query->family ?: null, $query->kind ?: null), 0, 12);

        return $this->json(array_map(static fn (Package $p): array => [
            'name' => $p->getName(),
            'short' => $p->getShortName(),
            'family' => $p->getFamily(),
            'kind' => $p->getTier()->kind(),
            'description' => $p->getDescription(),
        ], $found));
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

        return $this->render('client/catalogue/family.html.twig', [
            'family' => $family,
            'suffix' => Families::suffix($family),
            'own' => $own,
            'members' => array_values(array_filter($members, static fn (Package $p): bool => $p !== $own)),
            'manual' => $this->manuals->get($own->getHandle()),
        ]);
    }

    #[Route('/packages/{vendor}/{name}', name: 'app_package', requirements: ['vendor' => '[a-z0-9][a-z0-9_.-]*', 'name' => '[a-z0-9][a-z0-9_.-]*'], methods: ['GET'])]
    public function package(string $vendor, string $name, FollowRepository $follows, Versions $versions): Response
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
            'versions' => $versions->of($package),
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
