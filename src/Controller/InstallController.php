<?php

namespace App\Controller;

use App\Catalogue\Families;
use App\Catalogue\Installer;
use App\Catalogue\PackageVersions;
use App\Catalogue\Tier;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The install selector, as on pytorch.org: rows of choices - the framework,
 * the channel, the families and their providers, omnibase's components -
 * and under them the composer.json block and the commands. A plain GET form:
 * without JavaScript the page is sent again; with it, install_controller.js
 * asks for the answer alone (?fragment=1) at each change.
 */
class InstallController extends AbstractController
{
    /** What the page shows before anything is chosen. */
    private const EXAMPLE = ['glitchr/omnitrade', 'omnitrade/stripe'];

    #[Route('/install', name: 'app_install', methods: ['GET'])]
    public function index(Request $request, PackageRepository $packages, Installer $installer): Response
    {
        $framework = (string) $request->query->get('framework', Installer::BARE);
        $channel = (string) $request->query->get('channel', PackageVersions::STABLE);
        $chosen = $request->query->has('p') ? array_values(array_filter(array_map('strval', $request->query->all('p')))) : self::EXAMPLE;

        $plan = $installer->plan($framework, $channel, $chosen);

        if ($request->query->getBoolean('fragment')) {
            return $this->render('client/install/_answer.html.twig', ['plan' => $plan]);
        }

        $families = Families::ordered($packages->findFamilies());
        unset($families['omnibase']);
        $providers = [];
        foreach ($packages->findByTier(Tier::Provider) as $provider) {
            $providers[(string) $provider->getFamily()][] = $provider;
        }

        return $this->render('client/install/index.html.twig', [
            'plan' => $plan,
            'chosen' => $chosen,
            'families' => $families,
            'providers' => $providers,
            'foundation' => $packages->findByTier(Tier::Foundation),
            'components' => $packages->findByTier(Tier::Component),
        ]);
    }
}
