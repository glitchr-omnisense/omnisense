<?php

namespace App\Controller;

use App\Content\SearchIndex;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The search, on the reader's side: a JSON index written with the site
 * (every guide, piece of news, package and manual page of one language) and
 * the page that loads it and answers as one types. No search service.
 */
class SearchController extends AbstractController
{
    #[Route(['en' => '/search', 'fr' => '/fr/recherche'], name: 'app_search', methods: ['GET'])]
    public function search(): Response
    {
        return $this->render('client/search.html.twig');
    }

    #[Route(['en' => '/search.json', 'fr' => '/fr/search.json'], name: 'app_search_index', methods: ['GET'], priority: 10)]
    public function index(Request $request, SearchIndex $index): JsonResponse
    {
        $response = new JsonResponse($index->build(Locale::of($request)));
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
