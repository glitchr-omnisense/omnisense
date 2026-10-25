<?php

namespace App\Controller;

use App\Catalogue\Catalogue;
use App\Catalogue\PackageSearch;
use App\Catalogue\Tier;
use App\Content\Documentation;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The catalogue, read from catalog/*.yaml: every package in one list that
 * the page's script narrows as one types (a family and a kind to narrow it
 * further, kept in the address's fragment - a static page has no query); a
 * page per family; a page per package.
 */
class CatalogueController extends AbstractController
{
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly PackageSearch $search,
        private readonly Documentation $documentation,
    ) {
    }

    #[Route(['en' => '/packages', 'fr' => '/fr/paquets'], name: 'app_packages', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('client/catalogue/index.html.twig', [
            'rows' => $this->search->all(),
            'families' => $this->catalogue->families(),
            'family_pages' => array_keys(array_filter($this->catalogue->families(), static fn ($f): bool => $f->hasPage())),
            'kinds' => array_map(static fn (Tier $t): string => $t->kind(), PackageSearch::ORDER),
            'counts' => $this->catalogue->counts(),
        ]);
    }

    /**
     * A family: no page between the reader and its documentation - its own package's manual (the
     * family's core, where the providers are listed, each linked to its own manual). The JavaScript
     * libraries, a family with no core, have a page that lists them, each linked to its manual.
     */
    #[Route(['en' => '/families/{family}', 'fr' => '/fr/familles/{family}'], name: 'app_family', requirements: ['family' => 'omni[a-z]+|libraries'], methods: ['GET'])]
    public function family(Request $request, string $family): Response
    {
        $entry = $this->catalogue->family($family) ?? throw $this->createNotFoundException(sprintf('No family "%s".', $family));
        $own = $entry->getOwn();
        $manual = null !== $own ? $this->documentation->manualOf($own->getName()) : null;
        if (null !== $manual) {
            return $this->redirectToRoute('app_manual', ['key' => $manual->key, 'series' => $manual->default], Response::HTTP_MOVED_PERMANENTLY);
        }
        if (null === $own ? 'libraries' !== $family : !$entry->hasPage()) {
            throw $this->createNotFoundException(sprintf('No family "%s".', $family));
        }

        return $this->render('client/catalogue/family.html.twig', [
            'family' => $entry,
            'own' => $own,
            'members' => $entry->getMembers(),
            'manual' => null,
            'phrase' => $entry->phrase(Locale::of($request)),
            'text' => $entry->text(Locale::of($request)),
        ]);
    }

    /** A package: its manual when it has one (the header there says what this page said); else what the catalogue knows. */
    #[Route(['en' => '/packages/{vendor}/{name}', 'fr' => '/fr/paquets/{vendor}/{name}'], name: 'app_package', requirements: ['vendor' => '[a-z0-9][a-z0-9_.-]*', 'name' => '[a-z0-9][a-z0-9_.-]*'], methods: ['GET'])]
    public function package(string $vendor, string $name): Response
    {
        // A JavaScript library (@glitchr/flowjs) is at /packages/glitchr/flowjs.
        $package = $this->catalogue->find($vendor.'/'.$name) ?? $this->catalogue->find('@'.$vendor.'/'.$name);
        if (null === $package) {
            throw $this->createNotFoundException(sprintf('No package "%s/%s".', $vendor, $name));
        }
        $manual = $this->documentation->manualOf($package->getName());
        if (null !== $manual) {
            return $this->redirectToRoute('app_manual', ['key' => $manual->key, 'series' => $manual->default], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('client/catalogue/package.html.twig', [
            'package' => $package,
            'family' => null !== $package->getFamily() ? $this->catalogue->family($package->getFamily()) : null,
            'manual' => null,
            'tree' => [],
            'requires' => array_values(array_filter(array_map($this->catalogue->find(...), $package->getRequires()))),
            'required_by' => $this->catalogue->requiredBy($package),
        ]);
    }
}
