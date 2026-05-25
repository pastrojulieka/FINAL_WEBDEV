<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserDashboardController extends AbstractController
{
    #[Route('/user/dashboard', name: 'app_user_dashboard')]
    public function index(ProductRepository $productRepository): Response
    {
        // Fetch all products from the database
        $products = $productRepository->findAll();
        
        return $this->render('user_dashboard/index.html.twig', [
            'controller_name' => 'UserDashboardController',
            'products' => $products,
        ]);
    }
}