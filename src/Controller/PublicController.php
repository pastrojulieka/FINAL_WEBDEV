<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
final class PublicController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function home(ProductRepository $productRepository): Response
    {
        // Get featured products (latest 6 products)
        $featuredProducts = $productRepository->findBy([], ['id' => 'DESC'], 6);

        return $this->render('public/home.html.twig', [
            'featuredProducts' => $featuredProducts,
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('public/about.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        // Optional: embed a third-party form (e.g., Google Forms) if configured.
        // When set, the iframe handles submissions and provides feedback inside the form.
        $googleFormsEmbedUrl = getenv('GOOGLE_FORMS_CONTACT_EMBED_URL') ?: null;

        $success = false;
        $error = null;

        // Only handle the local fallback form submission when an embed isn't configured.
        if (!$googleFormsEmbedUrl && $request->isMethod('POST')) {
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');

            if ($name && $email && $subject && $message) {
                try {
                    $emailMessage = (new Email())
                        ->from($email)
                        ->to('contact@julieka.com') // Change to your email
                        ->subject('Contact Form: ' . $subject)
                        ->html(
                            "<h2>New Contact Form Submission</h2>" .
                            "<p><strong>Name:</strong> {$name}</p>" .
                            "<p><strong>Email:</strong> {$email}</p>" .
                            "<p><strong>Subject:</strong> {$subject}</p>" .
                            "<p><strong>Message:</strong></p>" .
                            "<p>" . nl2br(htmlspecialchars($message)) . "</p>"
                        );

                    $mailer->send($emailMessage);
                    $success = true;
                } catch (\Exception $e) {
                    $error = 'Failed to send message. Please try again later.';
                }
            } else {
                $error = 'Please fill in all fields.';
            }
        }

        return $this->render('public/contact.html.twig', [
            'success' => $success,
            'error' => $error,
            'googleFormsEmbedUrl' => $googleFormsEmbedUrl,
        ]);
    }

    #[Route('/testimonials', name: 'app_testimonials')]
    public function testimonials(): Response
    {
        return $this->render('public/testimonials.html.twig');
    }
}

