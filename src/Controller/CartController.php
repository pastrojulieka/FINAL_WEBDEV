<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CartController extends AbstractController
{
    #[Route('/cart/add', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['id'] ?? null;
        $qty = max(1, (int) ($data['qty'] ?? 1));

        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing product id'], 400);
        }

        $product = $em->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        $session = $request->getSession();

        // If user is authenticated, persist to DB cart
        $user = $this->getUser();
        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                $cart = new Cart();
                $cart->setUser($user);
                $em->persist($cart);
            }

            // find existing item
            $existing = null;
            foreach ($cart->getItems() as $it) {
                if ($it->getProduct()->getId() === (int) $productId) {
                    $existing = $it;
                    break;
                }
            }

            if ($existing) {
                $existing->setQuantity($existing->getQuantity() + $qty);
            } else {
                $item = new CartItem();
                $item->setProduct($product);
                $item->setQuantity($qty);
                $cart->addItem($item);
                $em->persist($item);
            }

            $em->flush();

            $totalItems = $cart->getTotalItems();

            return new JsonResponse(['success' => true, 'totalItems' => $totalItems]);
        }

        // Guest: session cart
        $cart = $session->get('cart', []);

        if (isset($cart[$productId])) {
            $cart[$productId] += $qty;
        } else {
            $cart[$productId] = $qty;
        }

        $session->set('cart', $cart);

        $totalItems = array_sum($cart);

        return new JsonResponse(['success' => true, 'totalItems' => $totalItems]);
    }

    #[Route('/cart/count', name: 'app_cart_count', methods: ['GET'])]
    public function count(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $total = 0;

        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if ($cart) {
                $total = $cart->getTotalItems();
            }
        } else {
            $session = $request->getSession();
            $sessionCart = $session->get('cart', []);
            $total = array_sum($sessionCart);
        }

        return new JsonResponse(['success' => true, 'totalItems' => $total]);
    }

    #[Route('/cart', name: 'app_cart', methods: ['GET'])]
    public function view(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $items = [];
        $total = 0;

        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if ($cart) {
                $items = $cart->getItems()->toArray();
                $total = $cart->getTotalItems();
            }
        } else {
            $session = $request->getSession();
            $sessionCart = $session->get('cart', []);
            if ($sessionCart) {
                foreach ($sessionCart as $pid => $qty) {
                    $product = $em->getRepository(Product::class)->find((int)$pid);
                    if ($product) {
                        $items[] = ['product' => $product, 'quantity' => $qty];
                    }
                }
                $total = array_sum($sessionCart);
            }
        }

        return $this->render('cart/index.html.twig', ['items' => $items, 'totalItems' => $total]);
    }

    #[Route('/cart/update', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['id'] ?? null;
        $qty = max(1, (int) ($data['qty'] ?? 1));

        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing product id'], 400);
        }

        $user = $this->getUser();
        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['success' => false, 'message' => 'Cart not found'], 404);
            }

            foreach ($cart->getItems() as $item) {
                if ($item->getProduct()->getId() === (int)$productId) {
                    $item->setQuantity($qty);
                    $em->flush();
                    return new JsonResponse(['success' => true, 'totalItems' => $cart->getTotalItems()]);
                }
            }

            return new JsonResponse(['success' => false, 'message' => 'Item not found in cart'], 404);
        }

        $session = $request->getSession();
        $sessionCart = $session->get('cart', []);
        if (isset($sessionCart[$productId])) {
            $sessionCart[$productId] = $qty;
            $session->set('cart', $sessionCart);
            return new JsonResponse(['success' => true, 'totalItems' => array_sum($sessionCart)]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Item not found'], 404);
    }

    #[Route('/cart/remove', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['id'] ?? null;

        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing product id'], 400);
        }

        $user = $this->getUser();
        if ($user) {
            $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['success' => false, 'message' => 'Cart not found'], 404);
            }

            foreach ($cart->getItems() as $item) {
                if ($item->getProduct()->getId() === (int)$productId) {
                    $cart->removeItem($item);
                    $em->remove($item);
                    $em->flush();
                    return new JsonResponse(['success' => true, 'totalItems' => $cart->getTotalItems()]);
                }
            }

            return new JsonResponse(['success' => false, 'message' => 'Item not found'], 404);
        }

        $session = $request->getSession();
        $sessionCart = $session->get('cart', []);
        if (isset($sessionCart[$productId])) {
            unset($sessionCart[$productId]);
            $session->set('cart', $sessionCart);
            return new JsonResponse(['success' => true, 'totalItems' => array_sum($sessionCart)]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Item not found'], 404);
    }

    #[Route('/cart/checkout', name: 'app_cart_checkout', methods: ['POST'])]
    public function checkout(Request $request, EntityManagerInterface $em, StockRepository $stockRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Please log in to complete checkout.');
            return $this->redirectToRoute('app_login');
        }

        $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);
        $sessionCart = $request->getSession()->get('cart', []);
        $items = [];

        if ($cart && $cart->getItems()->count() > 0) {
            foreach ($cart->getItems() as $item) {
                $items[] = ['product' => $item->getProduct(), 'quantity' => $item->getQuantity()];
            }
        } elseif (!empty($sessionCart)) {
            foreach ($sessionCart as $pid => $qty) {
                $product = $em->getRepository(Product::class)->find((int) $pid);
                if ($product) {
                    $items[] = ['product' => $product, 'quantity' => (int) $qty];
                }
            }
        }

        if (empty($items)) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('app_cart');
        }

        $customerName = $user->getUserIdentifier();
        $createdOrders = 0;

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $item['product'];
            $quantity = $item['quantity'];

            $stocks = $stockRepository->findBy(['product' => $product]);
            $totalStock = array_sum(array_map(fn ($s) => $s->getQuantity(), $stocks));
            if (count($stocks) === 0) {
                $totalStock = $product->getQuantity();
            }

            if ($totalStock < $quantity) {
                $this->addFlash('error', "Insufficient stock for {$product->getName()}. Available: {$totalStock}.");
                return $this->redirectToRoute('app_cart');
            }

            $order = new Order();
            $order->setCustomerName($customerName);
            $order->setProductName($product->getName());
            $order->setMaterial($product->getMaterial());
            $order->setColor($product->getColor());
            $order->setQuantity($quantity);
            $order->setPrice($product->getPrice());
            $order->setTotalAmount($product->getPrice() * $quantity);
            $order->setDate(new \DateTime());
            $order->setDeliveryDate(new \DateTimeImmutable('+7 days'));
            $order->setStatus(Order::STATUS_PENDING);
            $order->setCreatedBy($user);

            $product->setQuantity($product->getQuantity() - $quantity);
            $em->persist($order);
            ++$createdOrders;
        }

        if ($cart) {
            foreach ($cart->getItems()->toArray() as $item) {
                $cart->removeItem($item);
                $em->remove($item);
            }
        }
        $request->getSession()->remove('cart');

        $em->flush();

        $this->addFlash('success', "{$createdOrders} order(s) placed successfully!");

        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_order_index');
        }

        return $this->redirectToRoute('app_user_dashboard');
    }
}
