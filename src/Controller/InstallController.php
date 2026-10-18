<?php

namespace App\Controller;

use App\Catalogue\InstallChoice;
use App\Catalogue\Installer;
use App\Catalogue\Tier;
use App\Dto\InstallQuery;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Get started (as pytorch.org/get-started): rows of choices - the framework,
 * the channel, what is needed (the core, families and their providers, a
 * prepared structure, a bridge) - and under them the commands, the
 * composer.json block and, in Symfony, the bundles to declare. The chosen
 * families' providers are chips, family by family, counted. A plain GET form:
 * without JavaScript the page is sent again; with it, install_controller.js
 * shows another family's providers at once, narrows them as one types, and
 * asks for the answer alone (?fragment=1) at each change.
 */
class InstallController extends AbstractController
{
    #[Route('/install', name: 'app_install', methods: ['GET'])]
    public function index(Request $request, PackageRepository $packages, Installer $installer, InstallChoice $chooser, #[MapQueryString] InstallQuery $query = new InstallQuery()): Response
    {
        $choice = $chooser->resolve($query, [] !== $request->query->all());
        $plan = $installer->plan($query->framework, $query->channel, $choice->names);

        if ($query->fragment) {
            return $this->render('client/install/_answer.html.twig', ['plan' => $plan, 'choice' => $choice]);
        }

        $families = $chooser->families();
        $providers = [];
        foreach (array_keys($families) as $name) {
            $providers[$name] = $chooser->providersOf($name);
        }

        return $this->render('client/install/index.html.twig', [
            'plan' => $plan,
            'choice' => $choice,
            'families' => $families,
            'providers' => $providers,
            'foundation' => $packages->findByTier(Tier::Foundation),
            'structures' => $packages->findByTier(Tier::Component),
            'bridge_packages' => $packages->findByTier(Tier::Bridge),
        ]);
    }
}
