<?php

namespace App\Repository;

use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserRepository extends \Base\Repository\UserRepository
{
    /** The coworkers: everyone granted a staff role (ROLE_STAFF and the roles above it). */
    public function countStaff(): int
    {
        $any = array_map(
            static fn (string $role): Criterion => Criterion::of('roles', '%'.$role.'%', Parser::OPTION_PARTIAL),
            ['ROLE_STAFF', 'ROLE_ADMIN', 'ROLE_SUPERADMIN', 'ROLE_EDITOR'],
        );

        return $this->count([Group::of(Parser::SEPARATOR_OR, ...$any)]);
    }

    /** Sign in with the email or the username. */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findOneByEmailOrUsername($identifier, $identifier);
        if (null === $user) {
            throw new UserNotFoundException();
        }

        return $user;
    }
}
