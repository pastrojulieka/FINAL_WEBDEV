<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/stock')]
class StockController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
    ) {}
    #[Route('/', name: 'app_stock_index', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'stocks' => $stockRepository->findAll(),
            'statusSummary' => $stockRepository->getStatusSummary(),
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, StockRepository $stockRepository): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            error_log('Stock form submitted. Is valid: ' . ($form->isValid() ? 'yes' : 'no'));

            if ($form->isValid()) {
                try {
                    // Set createdBy if user is logged in
                    if ($this->getUser()) {
                        $stock->setCreatedBy($this->getUser());
                    }
                    $stockRepository->save($stock, true);

                    // Log the creation
                    $this->activityLogService->log(
                        $this->getUser(),
                        ActivityLog::ACTION_CREATE,
                        'Stock',
                        (string)$stock->getId(),
                        "Created stock for product: {$stock->getProduct()->getName()}"
                    );

                    $this->addFlash('success', 'Stock created successfully! ID: ' . $stock->getId());
                    return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    error_log('Stock save error: ' . $e->getMessage());
                    $this->addFlash('error', 'Error saving stock: ' . $e->getMessage());
                }
            } else {
                // Form has validation errors - show them to user
                error_log('Form validation failed');
                foreach ($form->getErrors(true) as $error) {
                    error_log('Error: ' . $error->getMessage());
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, StockRepository $stockRepository): Response
    {
        // Staff and Admin can edit all stocks - no restrictions
        // If you want to add any permission checks, do it here

        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stockRepository->save($stock, true);

            // Log the update
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_UPDATE,
                'Stock',
                (string)$stock->getId(),
                "Updated stock for product: {$stock->getProduct()->getName()}"
            );

            $this->addFlash('success', 'Stock updated successfully!');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, StockRepository $stockRepository): Response
    {
        // Check if staff can only delete their own records
        // Admin can delete any stock
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            if ($stock->getCreatedBy() !== $this->getUser()) {
                $this->addFlash('error', 'You can only delete your own stocks.');
                return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        $token = $request->getPayload()->getString('_token');
        if ($token === '' && $request->request->has('_token')) {
            $token = (string) $request->request->get('_token');
        }

        if ($this->isCsrfTokenValid('delete' . $stock->getId(), $token)) {
            $productName = $stock->getProduct()->getName(); // Store name before deletion
            $stockId = (string)$stock->getId();

            $stockRepository->remove($stock, true);

            // Log the deletion
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_DELETE,
                'Stock',
                $stockId,
                "Deleted stock for product: {$productName}"
            );

            $this->addFlash('success', 'Stock deleted successfully!');
        }

        return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
    }
}
