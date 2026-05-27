<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Product;
use App\Form\Product1Type;
use App\Repository\ProductRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
    ) {}
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();
        $productsWithStatus = [];

        foreach ($products as $product) {
            $productsWithStatus[] = [
                'product' => $product,
                'stockStatus' => $this->calculateProductStockStatus($product),
            ];
        }

        return $this->render('product/index.html.twig', [
            'productsWithStatus' => $productsWithStatus,
            'products' => $products, // Keep for backward compatibility
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only Staff/Admin can create products.');
        }

        $product = new Product();
        $form = $this->createForm(Product1Type::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Set createdBy if user is logged in
                if ($this->getUser()) {
                    $product->setCreatedBy($this->getUser());
                }
                $entityManager->persist($product);
                $entityManager->flush();

                // Log the creation
                $this->activityLogService->log(
                    $this->getUser(),
                    ActivityLog::ACTION_CREATE,
                    'Product',
                    (string)$product->getId(),
                    "Created product: {$product->getName()}"
                );

                $this->addFlash('success', 'Product created successfully!');
                return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
            } else {
                // Form has validation errors - show them to user
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', 'Error: ' . $error->getMessage());
                }
            }
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        // Calculate overall stock status for the product
        $stockStatus = $this->calculateProductStockStatus($product);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'stockStatus' => $stockStatus,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only Staff/Admin can edit products.');
        }

        // Staff and Admin can edit all products - no restrictions
        // If you want to add any permission checks, do it here

        $form = $this->createForm(Product1Type::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log the update
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_UPDATE,
                'Product',
                (string)$product->getId(),
                "Updated product: {$product->getName()}"
            );

            $this->addFlash('success', 'Product updated successfully!');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only Staff/Admin can delete products.');
        }

        // Check if staff can only delete their own records
        // Admin can delete any product
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            if ($product->getCreatedBy() !== $this->getUser()) {
                $this->addFlash('error', 'You can only delete your own products.');
                return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            $productName = $product->getName(); // Store name before deletion
            $productId = (string)$product->getId();

            $entityManager->remove($product);
            $entityManager->flush();

            // Log the deletion
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_DELETE,
                'Product',
                $productId,
                "Deleted product: {$productName}"
            );

            $this->addFlash('success', 'Product deleted successfully!');
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    private function calculateProductStockStatus(Product $product): string
    {
        $stocks = $product->getStocks();
        $totalQuantity = 0;
        $hasLowStock = false;
        $hasOutOfStock = false;

        foreach ($stocks as $stock) {
            $totalQuantity += $stock->getQuantity();
            $status = $stock->getStatus();

            if ($status === 'Out of Stock') {
                $hasOutOfStock = true;
            } elseif ($status === 'Low Stock') {
                $hasLowStock = true;
            }
        }

        // Determine overall status
        if ($hasOutOfStock || $totalQuantity === 0) {
            return 'Out of Stock';
        } elseif ($hasLowStock) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
}
