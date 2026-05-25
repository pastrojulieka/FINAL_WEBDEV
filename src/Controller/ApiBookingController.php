<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiBookingController extends AbstractController
{
    #[Route('/bookings', name: 'api_bookings', methods: ['GET'])]
    public function getBookings(BookingRepository $bookingRepository): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        /** @var User $user */
        // For customers, get their bookings. For staff/admin, get all bookings.
        $roles = $user->getRoles();
        $isStaffOrAdmin = in_array('ROLE_STAFF', $roles) || in_array('ROLE_ADMIN', $roles);
        if (!$isStaffOrAdmin) {
            // Customer - get bookings by customer name
            $bookings = $bookingRepository->findByCustomerName($user->getUserIdentifier());
        } else {
            // Staff/Admin - get all bookings
            $bookings = $bookingRepository->findBy([], ['created_at' => 'DESC']);
        }

        $bookingData = [];
        
        foreach ($bookings as $booking) {
            $bookingData[] = [
                'id' => $booking->getId(),
                'customer_name' => $booking->getCustomerName(),
                'service_type' => $booking->getServiceType(),
                'description' => $booking->getDescription(),
                'booking_date' => $booking->getBookingDate()->format('Y-m-d'),
                'start_time' => $booking->getStartTime()->format('H:i'),
                'end_time' => $booking->getEndTime()->format('H:i'),
                'status' => $booking->getStatus(),
                'price' => $booking->getPrice(),
                'notes' => $booking->getNotes(),
                'created_at' => $booking->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $booking->getUpdatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $booking->getCreatedBy() ? $booking->getCreatedBy()->getEmail() : null
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $bookingData
        ]);
    }

    #[Route('/bookings/{id}', name: 'api_booking_show', methods: ['GET'])]
    public function getBooking(int $id, BookingRepository $bookingRepository): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $booking = $bookingRepository->find($id);
        
        if (!$booking) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        /** @var User $user */
        // Check if user has permission to view this booking
        if (!in_array('ROLE_STAFF', $user->getRoles()) && !in_array('ROLE_ADMIN', $user->getRoles())) {
            // Customer can only view their own bookings
            if ($booking->getCustomerName() !== $user->getUserIdentifier()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $booking->getId(),
                'customer_name' => $booking->getCustomerName(),
                'service_type' => $booking->getServiceType(),
                'description' => $booking->getDescription(),
                'booking_date' => $booking->getBookingDate()->format('Y-m-d'),
                'start_time' => $booking->getStartTime()->format('H:i'),
                'end_time' => $booking->getEndTime()->format('H:i'),
                'status' => $booking->getStatus(),
                'price' => $booking->getPrice(),
                'notes' => $booking->getNotes(),
                'created_at' => $booking->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $booking->getUpdatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $booking->getCreatedBy() ? $booking->getCreatedBy()->getEmail() : null
            ]
        ]);
    }

    #[Route('/bookings', name: 'api_create_booking', methods: ['POST'])]
    public function createBooking(
        Request $request, 
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['service_type'], $data['booking_date'], $data['start_time'], $data['end_time'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Service type, booking date, start time, and end time are required'
            ], 400);
        }

        /** @var User $user */
        $booking = new Booking();
        $booking->setCustomerName($user->getUserIdentifier());
        $booking->setServiceType($data['service_type']);
        $booking->setDescription($data['description'] ?? '');
        $booking->setBookingDate(new \DateTime($data['booking_date']));
        $booking->setStartTime(new \DateTime($data['start_time']));
        $booking->setEndTime(new \DateTime($data['end_time']));
        $booking->setStatus('pending');
        $booking->setPrice($data['price'] ?? null);
        $booking->setNotes($data['notes'] ?? null);
        $booking->setCreatedBy($user);

        // Validate the booking entity
        $errors = $validator->validate($booking);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $entityManager->persist($booking);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'id' => $booking->getId(),
                'customer_name' => $booking->getCustomerName(),
                'service_type' => $booking->getServiceType(),
                'description' => $booking->getDescription(),
                'booking_date' => $booking->getBookingDate()->format('Y-m-d'),
                'start_time' => $booking->getStartTime()->format('H:i'),
                'end_time' => $booking->getEndTime()->format('H:i'),
                'status' => $booking->getStatus(),
                'price' => $booking->getPrice(),
                'notes' => $booking->getNotes(),
                'created_at' => $booking->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ], 201);
    }

    #[Route('/bookings/{id}', name: 'api_update_booking', methods: ['PUT', 'PATCH'])]
    public function updateBooking(
        int $id,
        Request $request, 
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $booking = $bookingRepository->find($id);
        
        if (!$booking) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        /** @var User $user */
        // Check if user has permission to update this booking
        if (!in_array('ROLE_STAFF', $user->getRoles()) && !in_array('ROLE_ADMIN', $user->getRoles())) {
            // Customer can only update their own bookings
            if ($booking->getCustomerName() !== $user->getUserIdentifier()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }
            
            // Customers can only update certain fields
            $allowedFields = ['description', 'notes'];
            $data = json_decode($request->getContent(), true);
            
            if ($data) {
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $setter = 'set' . ucfirst(str_replace('_', '', $field));
                        if (method_exists($booking, $setter)) {
                            $booking->$setter($data[$field]);
                        }
                    }
                }
            }
        } else {
            // Staff/Admin can update all fields
            $data = json_decode($request->getContent(), true);
            
            if ($data) {
                if (isset($data['service_type'])) {
                    $booking->setServiceType($data['service_type']);
                }
                if (isset($data['description'])) {
                    $booking->setDescription($data['description']);
                }
                if (isset($data['booking_date'])) {
                    $booking->setBookingDate(new \DateTime($data['booking_date']));
                }
                if (isset($data['start_time'])) {
                    $booking->setStartTime(new \DateTime($data['start_time']));
                }
                if (isset($data['end_time'])) {
                    $booking->setEndTime(new \DateTime($data['end_time']));
                }
                if (isset($data['status'])) {
                    $booking->setStatus($data['status']);
                }
                if (isset($data['price'])) {
                    $booking->setPrice($data['price']);
                }
                if (isset($data['notes'])) {
                    $booking->setNotes($data['notes']);
                }
            }
        }

        // Validate the booking entity
        $errors = $validator->validate($booking);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking updated successfully',
            'data' => [
                'id' => $booking->getId(),
                'customer_name' => $booking->getCustomerName(),
                'service_type' => $booking->getServiceType(),
                'description' => $booking->getDescription(),
                'booking_date' => $booking->getBookingDate()->format('Y-m-d'),
                'start_time' => $booking->getStartTime()->format('H:i'),
                'end_time' => $booking->getEndTime()->format('H:i'),
                'status' => $booking->getStatus(),
                'price' => $booking->getPrice(),
                'notes' => $booking->getNotes(),
                'updated_at' => $booking->getUpdatedAt()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/bookings/{id}', name: 'api_delete_booking', methods: ['DELETE'])]
    public function deleteBooking(int $id, BookingRepository $bookingRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $booking = $bookingRepository->find($id);
        
        if (!$booking) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        /** @var User $user */
        // Check if user has permission to delete this booking
        if (!in_array('ROLE_STAFF', $user->getRoles()) && !in_array('ROLE_ADMIN', $user->getRoles())) {
            // Customer can only delete their own bookings
            if ($booking->getCustomerName() !== $user->getUserIdentifier()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }
            
            // Customers can only delete pending bookings
            if ($booking->getStatus() !== 'pending') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete booking that is not pending'
                ], 400);
            }
        }

        $entityManager->remove($booking);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking deleted successfully'
        ]);
    }
}
