<?php

namespace App\Controller\Admin;

use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\BranchExporter;
use Base\Wikidoc\Manual\ManualRegistry;
use Base\Wikidoc\Search\ManualSearch;
use Base\Wikidoc\Search\TypesenseIndex;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The manuals, as omnibase/docs reads them now: each package's versions
 * (the branch read in place, those copied by `wikidoc:sync`), how many
 * pages each holds, which engine answers the search. Two buttons do what
 * the two commands do: copy the other branches again, fill the search index.
 */
#[IsGranted('ROLE_ADMIN')]
class ManualsController extends AbstractController
{
    public function __construct(
        private readonly ManualRegistry $manuals,
        private readonly BranchExporter $exporter,
        private readonly ManualSearch $search,
        private readonly LockFactory $locks,
        private readonly ?TypesenseIndex $typesense = null,
    ) {
    }

    #[Route('/admin/manuals', name: 'admin_manuals', methods: ['GET'])]
    public function index(PackageRepository $packages): Response
    {
        $rows = [];
        $pages = 0;
        $versions = 0;
        foreach ($this->manuals->all() as $manual) {
            $row = ['manual' => $manual, 'package' => $packages->findOneByHandle($manual->getKey()), 'versions' => []];
            foreach ($manual->versions as $version) {
                $count = \count($this->manuals->pages($manual, $version)->getAllPages());
                $row['versions'][] = ['version' => $version, 'pages' => $count];
                $pages += $count;
                ++$versions;
            }
            $rows[] = $row;
        }

        return $this->render('admin/manuals.html.twig', [
            'rows' => $rows,
            'pages' => $pages,
            'versions' => $versions,
            'engine' => $this->search->engine(),
            'typesense' => null !== $this->typesense,
            'git' => BranchExporter::isAvailable(),
        ]);
    }

    /** What `bin/console wikidoc:sync` does: the branches that are not checked out, copied again. */
    #[Route('/admin/manuals/sync', name: 'admin_manuals_sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        $this->guard($request, 'admin-manuals-sync');
        if (!BranchExporter::isAvailable()) {
            $this->addFlash('warning', 'git n\'est pas installé ici : chaque manuel garde la seule version de son dépôt.');

            return $this->redirectToRoute('admin_manuals');
        }

        $lock = $this->locks->createLock('app-manuals-sync', 600);
        if (!$lock->acquire()) {
            $this->addFlash('warning', 'Une synchronisation est déjà en cours.');

            return $this->redirectToRoute('admin_manuals');
        }
        try {
            $this->manuals->reset();
            $exported = 0;
            $failed = 0;
            foreach ($this->manuals->all() as $manual) {
                $kept = [];
                foreach ($this->exporter->branches($manual) as $branch => $ref) {
                    try {
                        if ($this->exporter->export($manual, $branch, $ref) > 0) {
                            $kept[] = $branch;
                            ++$exported;
                        }
                    } catch (\Throwable) {
                        ++$failed;
                    }
                }
                $this->exporter->prune($manual, $kept);
            }
            $this->manuals->reset();
        } finally {
            $lock->release();
        }

        $this->addFlash($failed > 0 ? 'warning' : 'success', sprintf('%d version(s) copiée(s) depuis les autres branches%s.', $exported, $failed > 0 ? sprintf(', %d en échec', $failed) : ''));

        return $this->redirectToRoute('admin_manuals');
    }

    /** What `bin/console wikidoc:index` does: one Typesense collection per manual and version. */
    #[Route('/admin/manuals/index', name: 'admin_manuals_index', methods: ['POST'])]
    public function fill(Request $request): Response
    {
        $this->guard($request, 'admin-manuals-index');
        if (null === $this->typesense || !$this->typesense->isAvailable()) {
            $this->addFlash('warning', 'Typesense ne répond pas : rien n\'a été indexé. La recherche lit l\'index des pages en attendant.');

            return $this->redirectToRoute('admin_manuals');
        }

        $keep = [];
        $records = 0;
        foreach ($this->manuals->all() as $manual) {
            foreach ($manual->versions as $version) {
                $records += $this->typesense->index($manual, $version);
                $keep[] = $this->typesense->collection($manual, $version);
            }
        }
        $this->typesense->prune($keep);
        $this->addFlash('success', sprintf('%d entrée(s) dans %d collection(s).', $records, \count($keep)));

        return $this->redirectToRoute('admin_manuals');
    }

    private function guard(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }
    }
}
