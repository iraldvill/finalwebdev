<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\BookingStationRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    private const TOTAL_PC_STATIONS = 30;

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository,
        BookingRepository $bookingRepository,
        BookingStationRepository $stationRepository,
        ReviewRepository $reviewRepository,
    ): Response {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));

        // ---- Users ----
        $userEntities = $userRepository->findBy([], ['createdAt' => 'DESC']);
        $users = [];
        foreach ($userEntities as $u) {
            $users[] = [
                'id' => $u->getId(),
                'full_name' => $u->getFullName(),
                'email' => $u->getEmail(),
                'role' => match (true) {
                    in_array('ROLE_ADMIN', $u->getRoles(), true) => 'admin',
                    in_array('ROLE_STAFF', $u->getRoles(), true) => 'staff',
                    default => 'customer',
                },
                'is_active' => $u->isActive(),
                'created_at' => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // ---- Bookings ----
        $bookingEntities = $bookingRepository->findAllNewestFirst();
        $bookings = [];
        foreach ($bookingEntities as $b) {
            $numbers = $b->getBookingStations()
                ->map(fn ($s) => $s->getStationNumber())
                ->toArray();
            sort($numbers);

            $bookings[] = [
                'id' => $b->getId(),
                'full_name' => $b->getUser()?->getFullName(),
                'plan_name' => $b->getPlanName(),
                'stations' => $numbers ? 'PC '.implode(', PC ', $numbers) : '—',
                'booking_date' => $b->getBookingDate()?->format('Y-m-d'),
                'booking_time' => $b->getBookingTime()?->format('H:i'),
                'booking_time_end' => $b->getBookingTimeEnd()?->format('H:i'),
                'status' => $b->getStatus(),
                'payment_method' => match ($b->getPaymentMethod()) {
                    'gcash' => 'GCash',
                    'maya' => 'Maya',
                    default => null,
                },
                'payment_status' => $b->getPaymentStatus(),
                'created_at' => $b->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // ---- Reviews ----
        $reviews = [];
        foreach ($reviewRepository->findBy([], ['createdAt' => 'DESC']) as $r) {
            $reviews[] = [
                'id' => $r->getId(),
                'full_name' => $r->getUser()?->getFullName(),
                'rating' => $r->getRating(),
                'comment' => $r->getComment(),
                'created_at' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // ---- PC stations reserved today ----
        $pcAssignments = array_fill(1, self::TOTAL_PC_STATIONS, null);
        foreach ($stationRepository->findForDate($today) as $s) {
            $n = $s->getStationNumber();
            if ($n >= 1 && $n <= self::TOTAL_PC_STATIONS) {
                $booking = $s->getBooking();
                $pcAssignments[$n] = [
                    'full_name' => $booking->getUser()?->getFullName(),
                    'plan_name' => $booking->getPlanName(),
                    'booking_time' => $s->getBookingTime()?->format('H:i'),
                    'booking_time_end' => $s->getBookingTimeEnd()?->format('H:i'),
                ];
            }
        }

        return $this->render('dashboard/home.html.twig', [
            'adminName' => $this->getUser()?->getFullName(),
            'stats' => [
                'total_users' => count($users),
                'total_staff' => count(array_filter($users, fn ($u) => 'staff' === $u['role'])),
                'total_bookings' => count($bookings),
                'active_bookings' => $bookingRepository->countActive(),
                'today_bookings' => $bookingRepository->countOnDate($today),
            ],
            'users' => $users,
            'bookings' => $bookings,
            'reviews' => $reviews,
            'pcAssignments' => $pcAssignments,
            'totalStations' => self::TOTAL_PC_STATIONS,
        ]);
    }
}