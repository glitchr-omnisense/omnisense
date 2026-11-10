<?php

namespace App\Controller;

use App\Catalogue\Api\Directory;
use App\Catalogue\Catalogue;
use App\Catalogue\Api\Policy;
use App\Catalogue\Api\Status;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The families and their APIs as cards (/apis), filtered by tags in the
 * reader's browser, and the call for maintainers and API providers
 * (/contribute) with what is wanted - both from config/apis.yaml joined to
 * the catalogue (App\Catalogue\Api\Directory).
 */
class ApiController extends AbstractController
{
    /** The issues of the site's repository a proposal goes to: .github/ISSUE_TEMPLATE. */
    public const TEMPLATES = ['provider' => 'provider.yml', 'company' => 'api-provider.yml'];

    public function __construct(
        private readonly Directory $directory,
        private readonly Catalogue $catalogue,
    ) {
    }

    /** @return list<string> the families with a page of their own (a link to one without would be a 404) */
    private function familyPages(): array
    {
        return array_keys(array_filter($this->catalogue->families(), static fn ($f): bool => $f->hasPage()));
    }

    #[Route(['en' => '/apis', 'fr' => '/fr/api'], name: 'app_apis', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $families = $this->directory->families();

        return $this->render('client/apis/index.html.twig', [
            'directory' => $this->directory,
            'families' => $families,
            'providers' => $this->directory->providers(),
            'capabilities' => $this->directory->capabilities(Locale::of($request)),
            'statuses' => array_map(static fn (Status $s): string => $s->value, Status::cases()),
            'auths' => [...Policy::ACCESS, 'undeclared'],
            'commercials' => [...Policy::PERMISSIONS, 'undeclared'],
            'counts' => $this->directory->counts(),
            'templates' => self::TEMPLATES,
            'family_pages' => $this->familyPages(),
        ]);
    }

    /**
     * Every provider and its usage policy in one table (/apis/policies), filtered by family and by commercial use in
     * the reader's browser: the public face of "each provider declares its rules".
     */
    #[Route(['en' => '/apis/policies', 'fr' => '/fr/api/politiques'], name: 'app_api_policies', methods: ['GET'])]
    public function policies(): Response
    {
        return $this->render('client/apis/policies.html.twig', [
            'families' => $this->directory->families(),
            'providers' => $this->directory->providers(),
            'commercials' => [...Policy::PERMISSIONS, 'undeclared'],
            'counts' => $this->directory->counts(),
            'family_pages' => $this->familyPages(),
        ]);
    }

    #[Route(['en' => '/contribute', 'fr' => '/fr/contribuer'], name: 'app_contribute', methods: ['GET'])]
    public function contribute(): Response
    {
        $families = array_merge($this->directory->byStatus(Status::Wanted), $this->directory->byStatus(Status::Reserved));
        $extended = array_values(array_filter(
            $this->directory->byStatus(Status::Available),
            static fn ($family): bool => [] !== $family->getWanted(),
        ));

        return $this->render('client/apis/contribute.html.twig', [
            'directory' => $this->directory,
            'new_families' => $families,
            'extended' => $extended,
            'counts' => $this->directory->counts(),
            'templates' => self::TEMPLATES,
            'family_pages' => $this->familyPages(),
        ]);
    }
}
