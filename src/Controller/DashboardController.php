<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    private const TOTAL_PC_STATIONS = 30;

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        // ---- Placeholder data (replace with Doctrine repositories later) ----
        $users = [
            ['id' => 1, 'full_name' => 'JanIrald', 'email' => 'admin@example.com', 'role' => 'admin', 'created_at' => '2026-09-01 09:00:00'],
            ['id' => 2, 'full_name' => 'IraldVillaflores', 'email' => 'irald@example.com', 'role' => 'customer', 'created_at' => '2026-09-05 14:20:00'],
        ];

        $bookings = [];

        $reviews = [
            ['id' => 1, 'full_name' => 'IraldVillaflores', 'rating' => 5, 'comment' => 'Fast PCs and a great place to play!', 'created_at' => '2026-09-09 20:15:00'],
        ];

        // station number => reservation info (null = idle)
        $pcAssignments = array_fill(1, self::TOTAL_PC_STATIONS, null);

        $stats = [
            'total_users' => count($users),
            'total_bookings' => count($bookings),
            'active_bookings' => 0,
            'today_bookings' => 0,
        ];

        return $this->render('dashboard/home.html.twig', [
            'adminName' => 'Irald',
            'stats' => $stats,
            'users' => $users,
            'bookings' => $bookings,
            'reviews' => $reviews,
            'pcAssignments' => $pcAssignments,
            'totalStations' => self::TOTAL_PC_STATIONS,
        ]);
    }
}