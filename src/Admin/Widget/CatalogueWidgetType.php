<?php

namespace App\Admin\Widget;

use App\Repository\FollowRepository;
use App\Repository\PackageRepository;
use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\DashboardWidgetTypeInterface;
use Base\Wikidoc\Manual\ManualRegistry;
use Base\Wikidoc\Search\ManualSearch;

/**
 * The dashboard's "Catalogue": how many packages the last run found, how
 * many are on their registry, how many manuals are published, which engine
 * answers the search, and when the repositories were last read.
 */
final class CatalogueWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(
        private readonly PackageRepository $packages,
        private readonly FollowRepository $follows,
        private readonly ManualRegistry $manuals,
        private readonly ManualSearch $search,
    ) {
    }

    public static function getName(): string
    {
        return 'app_catalogue';
    }

    public function getTemplate(): string
    {
        return 'admin/widget/catalogue.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        return [
            'counts' => $this->packages->counts(),
            'synced' => $this->packages->lastSync(),
            'manuals' => \count($this->manuals->all()),
            'followers' => $this->follows->count([]),
            'engine' => $this->search->engine(),
        ];
    }
}
