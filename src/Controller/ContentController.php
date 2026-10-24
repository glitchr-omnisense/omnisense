<?php

namespace App\Controller;

use App\Catalogue\Catalogue;
use App\Content\Documentation;
use App\Content\Guides;
use App\Content\News;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The written pages: the documentation's entry (the guides, then a manual
 * per package), each guide (docs/<locale>/*.md) and the news
 * (news/*.<locale>.md) with their Atom and RSS feeds.
 */
class ContentController extends AbstractController
{
    public function __construct(
        private readonly Guides $guides,
        private readonly News $news,
    ) {
    }

    #[Route(['en' => '/docs', 'fr' => '/fr/docs'], name: 'app_docs', methods: ['GET'])]
    public function docs(Request $request, Documentation $documentation, Catalogue $catalogue): Response
    {
        return $this->render('client/docs/index.html.twig', [
            'guides' => $this->guides->all(Locale::of($request)),
            'manuals' => $documentation->manuals(),
            'catalogue' => $catalogue,
            'documentation' => $documentation,
        ]);
    }

    #[Route(['en' => '/guides/{slug}', 'fr' => '/fr/guides/{slug}'], name: 'app_guide', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function guide(Request $request, string $slug): Response
    {
        $locale = Locale::of($request);
        $guide = $this->guides->get($locale, $slug) ?? throw $this->createNotFoundException(sprintf('No guide "%s".', $slug));

        return $this->render('client/docs/guide.html.twig', [
            'guide' => $guide,
            'guides' => $this->guides->all($locale),
        ]);
    }

    #[Route(['en' => '/news', 'fr' => '/fr/actualites'], name: 'app_news', methods: ['GET'])]
    public function news(Request $request): Response
    {
        return $this->render('client/news/index.html.twig', ['items' => $this->news->all(Locale::of($request))]);
    }

    #[Route(['en' => '/news/{slug}', 'fr' => '/fr/actualites/{slug}'], name: 'app_news_item', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function item(Request $request, string $slug): Response
    {
        $locale = Locale::of($request);
        $item = $this->news->get($locale, $slug) ?? throw $this->createNotFoundException(sprintf('No news "%s".', $slug));
        // Its alternates: the languages it is written in.
        $alternates = array_values(array_filter(['en', 'fr'], fn (string $l): bool => null !== $this->news->get($l, $slug)));

        return $this->render('client/news/item.html.twig', ['item' => $item, 'alternate_locales' => $alternates]);
    }

    #[Route(['en' => '/news/feed.atom', 'fr' => '/fr/actualites/feed.atom'], name: 'app_news_atom', methods: ['GET'], priority: 10)]
    public function atom(Request $request): Response
    {
        return $this->render('client/news/feed.atom.twig', ['items' => $this->news->all(Locale::of($request))], new Response('', 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8']));
    }

    #[Route(['en' => '/news/feed.rss', 'fr' => '/fr/actualites/feed.rss'], name: 'app_news_rss', methods: ['GET'], priority: 10)]
    public function rss(Request $request): Response
    {
        return $this->render('client/news/feed.rss.twig', ['items' => $this->news->all(Locale::of($request))], new Response('', 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']));
    }
}
