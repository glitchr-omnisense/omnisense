<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true, 'test' => true],
    Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
    League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
    Well\Known\WellKnownBundle::class => ['all' => true],
    Nelmio\SecurityBundle\NelmioSecurityBundle::class => ['all' => true],
    Nelmio\CorsBundle\NelmioCorsBundle::class => ['all' => true],
    HWI\Bundle\OAuthBundle\HWIOAuthBundle::class => ['all' => true],
    Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    Webauthn\Bundle\WebauthnBundle::class => ['all' => true],
    Google\GoogleBundle::class => ['all' => true],
    Base\BaseBundle::class => ['all' => true],
    Base\Admin\AdminBundle::class => ['all' => true],
    // The announcements (omnibase/blog), the manuals read in each package's
    // repository (omnibase/docs) and their search (glitchr/typesense-bundle).
    Base\Blog\BlogBundle::class => ['all' => true],
    Base\Wikidoc\WikidocBundle::class => ['all' => true],
    Typesense\Bundle\TypesenseBundle::class => ['all' => true],
    // The cookie answer: the circle in the footer, its panel (omnibase/consent).
    Base\Consent\ConsentBundle::class => ['all' => true],
    // Each package's repository read through libgit2: its tags, its branches, their
    // last commits (git/git-bundle ships no Flex recipe).
    Git\GitBundle::class => ['all' => true],
];
