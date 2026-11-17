<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\BookingStation;
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
 * Staff: view all bookings, create new ones for customers,
 * and edit or delete the ones they created themselves.
 * The booking rules (plans, prices, double-booking) live in App\Service\BookingRules.
 */
#[Route('/staff/bookings')]
#[IsGranted('ROLE_STAFF')]
class StaffBookingController extends AbstractController
{
    public function __construct(private readonly BookingRules $rules)
    {
    }

    #[Route('', name: 'app_staff_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        $rows = [];
        $mineCount = 0;
        foreach ($bookingRepository->findAllNewestFirst() as $b) {
            $numbers = $b->getBookingStations()
                ->map(fn ($s) => $s->getStationNumber())
                ->toArray();
            sort($numbers);

            $mine = $this->isOwnBooking($b);
            $mineCount += $mine ? 1 : 0;

            $rows[] = [
                'id' => $b->getId(),
                'customer' => $b->getUser()?->getFullName(),
                'plan' => $b->getPlanName(),
                'stations' => $numbers ? 'PC '.implode(', PC ', $numbers) : '—',
                'date' => $b->getBookingDate()?->format('Y-m-d'),
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
                'created_by' => $b->getCreatedBy()?->getFullName(),
                'mine' => $mine,
            ];
        }

        return $this->render('staff/booking_list.html.twig', [
            'bookings' => $rows,
            'mineCount' => $mineCount,
        ]);
    }

    /** Which PCs are already taken for this date and time? (used live by the booking form) */
    #[Route('/availability', name: 'app_staff_booking_availability', methods: ['GET'])]
    public function availability(Request $request): JsonResponse
    {
        return $this->json(['taken' => $this->rules->takenNumbers($request)]);
    }

    #[Route('/new', name: 'app_staff_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(StaffBookingFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->rules->check($form);

            if (null !== $result) {
                /** @var User $staff */
                $staff = $this->getUser();
                $data = $form->getData();

                $booking = new Booking();
                $booking->setUser($data['customer']);
                $booking->setCreatedBy($staff);
                $booking->setPaymentStatus('unpaid');
                $booking->setStatus('confirmed');
                $this->rules->fill($booking, $data, $result);
                $em->persist($booking);
                $this->rules->syncStations($booking, $result, $em);
                $em->flush();

                $this->addFlash('success', sprintf(
                    'Booking created for %s: %s, %s %s–%s, PC %s — ₱%s.',
                    $data['customer']->getFullName(),
                    $data['plan'],
                    $result['date']->format('M j, Y'),
                    $result['start']->format('g:i A'),
                    $result['end']->format('g:i A'),
                    implode(', ', $result['pcs']),
                    number_format($result['pesos']),
                ));

                return $this->redirectToRoute('app_staff_booking_index');
            }
        }

        return $this->render('booking/form.html.twig', [
            'area' => 'staff',
            'form' => $form,
            'booking' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_staff_booking_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        // Staff may only edit the bookings they created themselves.
        $this->denyUnlessOwn($booking);

        $form = $this->createForm(StaffBookingFormType::class, $this->initialData($booking));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->rules->check($form, $booking);

            if (null !== $result) {
                $data = $form->getData();

                $booking->setUser($data['customer']);
                $this->rules->fill($booking, $data, $result);
                $this->rules->syncStations($booking, $result, $em);
                $em->flush();

                $this->addFlash('success', 'Your booking was updated.');

                return $this->redirectToRoute('app_staff_booking_index');
            }
        }

        return $this->render('booking/form.html.twig', [
            'area' => 'staff',
            'form' => $form,
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_staff_booking_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        // Staff may only delete the bookings they created themselves.
        $this->denyUnlessOwn($booking);

        if (!$this->isCsrfTokenValid('staff-delete-booking-'.$booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');

            return $this->redirectToRoute('app_staff_booking_index');
        }

        // The booking's reserved PCs are removed with it (orphanRemoval on the entity).
        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Your booking was deleted.');

        return $this->redirectToRoute('app_staff_booking_index');
    }

    // ------------------------------------------------------------------

    private function isOwnBooking(Booking $booking): bool
    {
        $me = $this->getUser();

        return $me instanceof User
            && null !== $booking->getCreatedBy()
            && $booking->getCreatedBy()->getId() === $me->getId();
    }

    private function denyUnlessOwn(Booking $booking): void
    {
        if (!$this->isOwnBooking($booking)) {
            throw $this->createAccessDeniedException('You can only change bookings you created.');
        }
    }

    /** Values that pre-fill the form when editing. */
    private function initialData(Booking $booking): array
    {
        $numbers = $booking->getBookingStations()
            ->map(fn (BookingStation $s) => $s->getStationNumber())
            ->toArray();
        sort($numbers);

        return [
            'customer' => $booking->getUser(),
            'plan' => $booking->getPlanName(),
            'bookingDate' => $booking->getBookingDate(),
            'startTime' => $booking->getBookingTime(),
            'endTime' => $booking->getBookingTimeEnd(),
            'stations' => array_values($numbers),
            'paymentMethod' => $booking->getPaymentMethod(),
        ];
    }
}