<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FollowRepository;
use Base\Blog\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "My account": the packages a member follows - each with the version its
 * registry serves now, marked when it is no longer the one of the day they
 * started following -, the latest announcements, the account's settings
 * (omnibase's), and the back office for those who run the site.
 */
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function index(FollowRepository $follows, PostRepository $posts): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('client/account/index.html.twig', [
            'follows' => $follows->findForUser($user),
            'posts' => $posts->findLatest(3),
        ]);
    }
}
