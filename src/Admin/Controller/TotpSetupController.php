<?php

namespace App\Admin\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AdminRoute(path: '/2fa-setup', name: '2fa_setup')]
final class TotpSetupController extends AbstractController
{
    public function __invoke(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        $qrSvg = null;
        $secret = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_2fa_setup', $request->getPayload()->getString('_token'))) {
                throw new AccessDeniedException('Invalid CSRF token.');
            }

            $user->setTotpSecret($totpAuthenticator->generateSecret());
            $entityManager->flush();

            // The QR/secret is displayed exactly once, in this response.
            $secret = $user->getTotpSecret();
            $qrSvg = new Builder(
                writer: new SvgWriter(),
                data: $totpAuthenticator->getQRContent($user),
                size: 240,
                margin: 8,
            )->build()->getDataUri();
        }

        return $this->render('admin/totp_setup.html.twig', [
            'enrolled' => null !== $user->getTotpSecret(),
            'qr_svg' => $qrSvg,
            'secret' => $secret,
        ]);
    }
}
