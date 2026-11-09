<?php

namespace App\Catalogue\Api;

/**
 * Where a family or a provider stands (config/apis.yaml):
 *
 *   available  released: its package is in the catalogue
 *   reserved   its name and repositories are taken, nothing released yet
 *   wanted     proposed, looking for someone to maintain it
 */
enum Status: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Wanted = 'wanted';
}
