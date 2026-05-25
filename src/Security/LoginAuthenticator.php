<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Cart;
use App\Entity\CartItem;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator, private EntityManagerInterface $em)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        // store last email in session (avoid direct dependency on Security class)
        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // merge session cart into DB cart if any
        $session = $request->getSession();
        $sessionCart = $session->get('cart', []);
        $user = $token->getUser();
        if ($user && is_array($sessionCart) && count($sessionCart) > 0) {
            $cart = $this->em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                $cart = new Cart();
                $cart->setUser($user);
                $this->em->persist($cart);
            }

            foreach ($sessionCart as $pid => $qty) {
                $pid = (int) $pid;
                $found = null;
                foreach ($cart->getItems() as $it) {
                    if ($it->getProduct()->getId() === $pid) {
                        $found = $it;
                        break;
                    }
                }
                if ($found) {
                    $found->setQuantity($found->getQuantity() + (int)$qty);
                } else {
                    $product = $this->em->getRepository(\App\Entity\Product::class)->find($pid);
                    if ($product) {
                        $item = new CartItem();
                        $item->setProduct($product);
                        $item->setQuantity((int)$qty);
                        $cart->addItem($item);
                        $this->em->persist($item);
                    }
                }
            }
            $this->em->flush();
            $session->remove('cart');
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Redirect based on user role
        $user = $token->getUser();
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        } elseif (in_array('ROLE_STAFF', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_staff_dashboard'));
        } else {
            return new RedirectResponse($this->urlGenerator->generate('app_user_dashboard'));
        }
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
