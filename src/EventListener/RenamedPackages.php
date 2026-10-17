<?php

namespace App\EventListener;

use App\Catalogue\Tier;
use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\ManualRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A family's core moves from glitchr/ to omniscient/ on Packagist
 * (omniscient/omnilab first), the old name kept as a `replace`: its package
 * page and its manual move with it. /packages/glitchr/omnilab and
 * /docs/glitchr/omnilab/... answer 301 towards the omniscient/ address once
 * the catalogue (or the manuals) hold the new name and no longer the old one;
 * while glitchr/ is still the only name, nothing changes.
 */
final class RenamedPackages
{
    private const PATTERN = '#^/(packages|docs)/glitchr/(omni[a-z]+)(/.*)?$#';

    public function __construct(
        private readonly PackageRepository $packages,
        private readonly ManualRegistry $manuals,
    ) {
    }

    // Before the router (32): the old address is not a route's business.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 40)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethodSafe() || 1 !== preg_match(self::PATTERN, $request->getPathInfo(), $m)) {
            return;
        }
        [, $section, $package, $rest] = $m + [3 => ''];
        $old = 'glitchr/'.$package;
        $new = Tier::HOUSE_VENDORS[0].'/'.$package;

        $moved = 'packages' === $section
            ? '' === $rest && null === $this->packages->findOneByHandle($old) && null !== $this->packages->findOneByHandle($new)
            : !$this->manuals->has($old) && $this->manuals->has($new);
        if (!$moved) {
            return;
        }

        $query = $request->getQueryString();
        $event->setResponse(new RedirectResponse($request->getBaseUrl().'/'.$section.'/'.$new.$rest.(null !== $query ? '?'.$query : ''), Response::HTTP_MOVED_PERMANENTLY));
    }
}
