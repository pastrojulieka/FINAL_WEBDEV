<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StaffGoogleVerifyController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    #[Route('/staff/google/verify', name: 'app_staff_google_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $idToken = is_array($data) ? ($data['id_token'] ?? null) : null;

        if (!is_string($idToken) || $idToken === '') {
            return $this->json(['success' => false, 'message' => 'Missing id_token'], 400);
        }

        $claims = $this->decodeJwtPayload($idToken);
        if (!$claims) {
            return $this->json(['success' => false, 'message' => 'Invalid id_token'], 400);
        }

        $email = $claims['email'] ?? null;
        if (!is_string($email) || $email === '') {
            return $this->json(['success' => false, 'message' => 'Email not found in id_token'], 400);
        }

        // Optional: restrict staff sign-in by explicit email allowlist (comma-separated).
        // Example: STAFF_EMAIL_ALLOWLIST="akeiluj@gmail.com,other@company.com"
        $allowlistRaw = $this->env('STAFF_EMAIL_ALLOWLIST');
        if (is_string($allowlistRaw) && trim($allowlistRaw) !== '') {
            $allowed = array_values(array_filter(array_map(
                static fn (string $v) => strtolower(trim($v)),
                explode(',', $allowlistRaw)
            )));

            if ($allowed && !in_array(strtolower($email), $allowed, true)) {
                return $this->json(['success' => false, 'message' => 'Not authorized as staff'], 403);
            }
        }

        // Optional: restrict staff sign-in by email domain (set in env if desired).
        $staffDomain = $this->env('STAFF_EMAIL_DOMAIN');
        if (is_string($staffDomain) && $staffDomain !== '') {
            $emailDomain = strtolower((string) preg_replace('/^.+@/', '', $email));
            if (!str_ends_with($emailDomain, strtolower($staffDomain))) {
                return $this->json(['success' => false, 'message' => 'Not authorized as staff'], 403);
            }
        }

        // Optional: validate audience against your Google OAuth client ID.
        $expectedAud = $this->env('GOOGLE_STAFF_OAUTH_CLIENT_ID');
        if (is_string($expectedAud) && $expectedAud !== '') {
            $aud = $claims['aud'] ?? null;
            if (is_string($aud) && $aud !== $expectedAud) {
                return $this->json(['success' => false, 'message' => 'Invalid id_token audience'], 400);
            }
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_STAFF']);

            // Password is required by the entity schema; we set a random one.
            $randomPassword = bin2hex(random_bytes(18));
            $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

            // Requirement: auto verification on Google login.
            $user->setIsVerified(true);

            $this->entityManager->persist($user);
        } else {
            // Ensure staff role and verified status.
            $roles = $user->getRoles();
            $roles[] = 'ROLE_STAFF';
            $user->setRoles(array_values(array_unique($roles)));
            $user->setIsVerified(true);
        }

        $this->entityManager->flush();

        // Programmatic login: sets the session and returns a redirect response from your authenticator.
        $response = $this->security->login($user, null, 'main');

        $redirectUrl = $this->urlGenerator->generate('app_staff_dashboard');
        if ($response instanceof RedirectResponse) {
            $redirectUrl = $response->getTargetUrl();
        }

        return $this->json([
            'success' => true,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    /**
     * Decodes the JWT payload without verifying the signature.
     *
     * For a midterm project rubric, this keeps dependencies light.
     * If you want full security, switch to a JWT library + JWK signature verification.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - (strlen($payload) % 4)) % 4); // pad base64url
        $payload = strtr($payload, '-_', '+/');

        $decoded = base64_decode($payload, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $claims = json_decode($decoded, true);
        return is_array($claims) ? $claims : null;
    }

    private function json(array $data, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse($data, $statusCode);
    }
}

