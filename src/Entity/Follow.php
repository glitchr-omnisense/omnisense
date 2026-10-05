<?php

namespace App\Entity;

use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member follows a package: it is listed on their account, with what
 * changed since (its registry's newest version, its announcements).
 */
#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\Table(name: 'catalogue_follow')]
#[ORM\UniqueConstraint(name: 'catalogue_follow_once', columns: ['user_id', 'package_id'])]
class Follow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    protected User $user;

    #[ORM\ManyToOne(targetEntity: Package::class)]
    #[ORM\JoinColumn(name: 'package_id', nullable: false, onDelete: 'CASCADE')]
    protected Package $package;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    /** The registry's newest version on the day the member started following: what "changed since" compares with. */
    #[ORM\Column(length: 60, nullable: true)]
    protected ?string $sinceVersion = null;

    public function __construct(User $user, Package $package)
    {
        $this->user = $user;
        $this->package = $package;
        $this->createdAt = new \DateTimeImmutable();
        $this->sinceVersion = $package->getPublishedVersion();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPackage(): Package
    {
        return $this->package;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSinceVersion(): ?string
    {
        return $this->sinceVersion;
    }

    /** The registry serves another version than on the day the member started following. */
    public function hasNewVersion(): bool
    {
        $now = $this->package->getPublishedVersion();

        return null !== $now && null !== $this->sinceVersion && $now !== $this->sinceVersion;
    }
}
