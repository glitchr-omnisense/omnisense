<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Validator\Constraints as AssertBase;
use Doctrine\ORM\Mapping as ORM;

/**
 * Whoever signs in: a member who follows packages (anyone signs up), the
 * people who write the announcements and run the catalogue, and Glitch Art.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'userProfile')]
#[DiscriminatorEntry(value: 'user')]
#[AssertBase\UniqueEntity(fields: ['username'], groups: ['new', 'edit'])]
class User extends \Base\Entity\User
{
    public static $userIdentifier = 'username';

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    protected $username;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->username ?? parent::__toString());
    }
}
