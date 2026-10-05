<?php

namespace App\Repository;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserRepository extends \Base\Repository\UserRepository
{
    /** The coworkers: everyone granted a staff role (ROLE_STAFF and the roles above it). */
    public function countStaff(): int
    {
        $qb = $this->createQueryBuilder('u')->select('COUNT(u.id)');
        $any = $qb->expr()->orX();
        foreach (['ROLE_STAFF', 'ROLE_ADMIN', 'ROLE_SUPERADMIN', 'ROLE_EDITOR'] as $i => $role) {
            $any->add("u.roles LIKE :role$i");
            $qb->setParameter("role$i", '%'.$role.'%');
        }

        return (int) $qb->andWhere($any)->getQuery()->getSingleScalarResult();
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
