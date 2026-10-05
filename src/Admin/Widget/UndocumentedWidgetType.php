<?php

namespace App\Admin\Widget;

use App\Entity\Package;
use App\Repository\PackageRepository;
use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\DashboardWidgetTypeInterface;

/**
 * The dashboard's "Paquets sans documentation": the packages whose
 * repository has no docs/ folder (plan 0, rule 10: each package ships one) -
 * those with a README alone, and those with nothing at all.
 */
final class UndocumentedWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(private readonly PackageRepository $packages)
    {
    }

    public static function getName(): string
    {
        return 'app_undocumented';
    }

    public function getTemplate(): string
    {
        return 'admin/widget/undocumented.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $without = array_values(array_filter($this->packages->findCatalogue(), static fn (Package $p): bool => 0 === $p->getDocPages()));

        return [
            'readme_only' => array_values(array_filter($without, static fn (Package $p): bool => $p->hasReadme())),
            'nothing' => array_values(array_filter($without, static fn (Package $p): bool => !$p->hasReadme())),
            'total' => \count($this->packages->findCatalogue()),
        ];
    }
}
