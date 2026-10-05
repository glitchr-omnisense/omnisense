<?php

namespace App\Repository;

use App\Entity\Follow;
use App\Entity\Package;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Follow> */
class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    public function findOneFor(User $user, Package $package): ?Follow
    {
        return $this->findOneBy(['user' => $user, 'package' => $package]);
    }

    /** @return list<Follow> a member's packages, by name */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('p')
            ->join('f.package', 'p')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countFor(Package $package): int
    {
        return $this->count(['package' => $package]);
    }

    /** @return array<int, int> followers by package id */
    public function countByPackage(): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.package) AS package, COUNT(f.id) AS followers')
            ->groupBy('f.package')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'followers', 'package');
    }
}
