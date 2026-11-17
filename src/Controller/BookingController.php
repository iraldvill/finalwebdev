<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Form\StaffBookingFormType;
use App\Repository\BookingRepository;
use App\Service\BookingRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customers: "Book a Seat" and "My Bookings".
 * Guests are sent to the login page first (see access_control in security.yaml);
 * staff and admins have their own booking pages and can't use these.
 */
#[IsGranted('ROLE_USER')]
class BookingController extends AbstractController
{
    public function __construct(private readonly BookingRules $rules)
    {
    }

    #[Route('/book', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyUnlessCustomer();

        // "Choose plan" buttons on the homepage send ?plan=… to pre-select a plan.
        $initial = [];
        $plan = $request->query->getString('plan');
        if (isset(BookingRules::PLANS[$plan])) {
            $initial['plan'] = $plan;
        }

        $form = $this->createForm(StaffBookingFormType::class, $initial, ['with_customer' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->rules->check($form);

            if (null !== $result) {
                /** @var User $user */
                $user = $this->getUser();
                $data = $form->getData();

                $booking = new Booking();
                $booking->setUser($user);          // booked by the customer, so no createdBy
                $booking->setPaymentStatus('unpaid');
                $booking->setStatus('pending');    // staff or admin confirm it
                $this->rules->fill($booking, $data, $result);
                $em->persist($booking);
                $this->rules->syncStations($booking, $result, $em);
                $em->flush();

                $this->addFlash('success', sprintf(
                    'Your booking request was sent: %s, %s %s–%s, PC %s — ₱%s. We will confirm it shortly.',
                    $data['plan'],
                    $result['date']->format('M j, Y'),
                    $result['start']->format('g:i A'),
                    $result['end']->format('g:i A'),
                    implode(', ', $result['pcs']),
                    number_format($result['pesos']),
                ));

                return $this->redirectToRoute('app_booking_index');
            }
        }

        return $this->render('booking/form.html.twig', [
            'area' => 'customer',
            'form' => $form,
            'booking' => null,
        ]);
    }

    #[Route('/book/availability', name: 'app_booking_availability', methods: ['GET'])]
    public function availability(Request $request): JsonResponse
    {
        $this->denyUnlessCustomer();

        return $this->json(['taken' => $this->rules->takenNumbers($request)]);
    }

    #[Route('/my-bookings', name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        $this->denyUnlessCustomer();

        $rows = [];
        foreach ($bookingRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']) as $b) {
            $numbers = $b->getBookingStations()
                ->map(fn ($s) => $s->getStationNumber())
                ->toArray();
            sort($numbers);

            $rows[] = [
                'plan' => $b->getPlanName(),
                'stations' => $numbers ? 'PC '.implode(', PC ', $numbers) : '—',
                'date_label' => $b->getBookingDate()?->format('M j, Y'),
                'time' => $b->getBookingTime()?->format('g:i A').' – '.$b->getBookingTimeEnd()?->format('g:i A'),
                'status' => $b->getStatus(),
                'payment_method' => match ($b->getPaymentMethod()) {
                    'gcash' => 'GCash',
                    'maya' => 'Maya',
                    default => null,
                },
                'payment_status' => $b->getPaymentStatus(),
                'amount' => number_format(($b->getAmount() ?? 0) / 100),
            ];
        }

        return $this->render('booking/index.html.twig', ['bookings' => $rows]);
    }

    /** Staff and admins don't book seats for themselves — they have their own pages. */
    private function denyUnlessCustomer(): void
    {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Booking a seat is for customers only.');
        }
    }
}