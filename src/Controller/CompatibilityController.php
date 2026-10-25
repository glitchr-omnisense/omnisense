<?php

namespace App\Controller;

use App\Catalogue\Compatibility;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** The compatibility dashboard: every package's CI jobs, its conformance suite, its live check - all generated. */
class CompatibilityController extends AbstractController
{
    #[Route(['en' => '/compatibility', 'fr' => '/fr/compatibilite'], name: 'app_compatibility', methods: ['GET'])]
    public function index(Compatibility $compatibility): Response
    {
        return $this->render('client/catalogue/compatibility.html.twig', [
            'rows' => $compatibility->rows(),
            'labels' => $compatibility->labels(),
            'generated' => $compatibility->generated(),
        ]);
    }
}
