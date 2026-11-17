<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\BookingStation;
use App\Entity\User;
use App\Form\StaffBookingFormType;
use App\Repository\BookingRepository;
use App\Repository\BookingStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Staff: view all bookings, create new ones for customers,
 * and edit or delete the ones they created themselves.
 */
#[Route('/staff/bookings')]
#[IsGranted('ROLE_STAFF')]
class StaffBookingController extends AbstractController
{
    /**
     * Price in pesos, how many PCs the plan needs, and a fixed length in hours (null = staff picks).
     * 'per_hour' plans are charged price × hours; the others are a flat price.
     */
    private const PLANS = [
        'Hourly Gaming' => ['price' => 50, 'per_hour' => true, 'pcs' => 1, 'fixed_hours' => null],
        'Extended Gaming' => ['price' => 200, 'per_hour' => false, 'pcs' => 1, 'fixed_hours' => 5],
        'Gaming Package' => ['price' => 180, 'per_hour' => true, 'pcs' => 3, 'fixed_hours' => null],
    ];

    #[Route('', name: 'app_staff_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        /** @var User $staff */
        $staff = $this->getUser();

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

    /**
     * Used by the booking form: which PCs are already taken for this date and time?
     * Example: /staff/bookings/availability?date=2026-11-20&start=14:00&end=16:00
     */
    #[Route('/availability', name: 'app_staff_booking_availability', methods: ['GET'])]
    public function availability(Request $request, BookingStationRepository $stationRepository): JsonResponse
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date'));
        $start = \DateTimeImmutable::createFromFormat('!H:i', substr($request->query->getString('start'), 0, 5));
        $end = \DateTimeImmutable::createFromFormat('!H:i', substr($request->query->getString('end'), 0, 5));

        if (!$date || !$start || !$end || $end <= $start) {
            return $this->json(['taken' => []]);
        }

        $except = $request->query->getInt('except') ?: null;

        return $this->json([
            'taken' => $stationRepository->findTakenNumbers($date, $start, $end, $except),
        ]);
    }

    #[Route('/new', name: 'app_staff_booking_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        BookingStationRepository $stationRepository,
    ): Response {
        $form = $this->createForm(StaffBookingFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->checkAndCompute($form, $stationRepository, null);

            if (null !== $result) {
                /** @var User $staff */
                $staff = $this->getUser();
                $data = $form->getData();

                $booking = new Booking();
                $booking->setUser($data['customer']);
                $booking->setCreatedBy($staff);
                $booking->setPaymentStatus('unpaid');
                $booking->setStatus('confirmed');
                $this->fillBooking($booking, $data, $result);
                $em->persist($booking);

                foreach ($result['pcs'] as $n) {
                    $station = new BookingStation();
                    $station->setStationNumber($n);
                    $this->syncStation($station, $result);
                    $booking->addBookingStation($station);
                    $em->persist($station);
                }

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

        return $this->render('staff/booking_new.html.twig', [
            'form' => $form,
            'booking' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_staff_booking_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Booking $booking,
        Request $request,
        EntityManagerInterface $em,
        BookingStationRepository $stationRepository,
    ): Response {
        // Staff may only edit the bookings they created themselves.
        $this->denyUnlessOwn($booking);

        $form = $this->createForm(StaffBookingFormType::class, $this->initialData($booking));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->checkAndCompute($form, $stationRepository, $booking);

            if (null !== $result) {
                $data = $form->getData();

                $booking->setUser($data['customer']);
                $this->fillBooking($booking, $data, $result);

                // Keep the reserved PCs in step: drop unticked ones, add new ones, update the rest.
                $current = [];
                foreach ($booking->getBookingStations() as $station) {
                    $current[$station->getStationNumber()] = $station;
                }
                foreach ($current as $n => $station) {
                    if (!in_array($n, $result['pcs'], true)) {
                        $booking->getBookingStations()->removeElement($station);
                        $em->remove($station);
                    }
                }
                foreach ($result['pcs'] as $n) {
                    if (isset($current[$n])) {
                        $station = $current[$n];
                    } else {
                        $station = new BookingStation();
                        $station->setStationNumber($n);
                        $booking->addBookingStation($station);
                        $em->persist($station);
                    }
                    $this->syncStation($station, $result);
                }

                $em->flush();

                $this->addFlash('success', 'Your booking was updated.');

                return $this->redirectToRoute('app_staff_booking_index');
            }
        }

        return $this->render('staff/booking_new.html.twig', [
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

    /**
     * Shared checks for creating and editing. Adds errors to the form and returns null when
     * something is wrong; otherwise returns the worked-out date, times, PCs and price.
     *
     * @return array{plan: array, date: \DateTimeImmutable, start: \DateTimeImmutable, end: \DateTimeImmutable, pcs: int[], hours: int, pesos: int}|null
     */
    private function checkAndCompute(
        FormInterface $form,
        BookingStationRepository $stationRepository,
        ?Booking $existing,
    ): ?array {
        $data = $form->getData();
        $plan = self::PLANS[$data['plan']] ?? null;

        if (null === $plan) {
            $form->get('plan')->addError(new FormError('Unknown plan.'));

            return null;
        }

        /** @var \DateTimeImmutable $date */
        $date = $data['bookingDate'];
        /** @var \DateTimeImmutable $start */
        $start = $data['startTime'];

        // Extended Gaming always lasts 5 hours; the other plans use the end time staff picked.
        if (null !== $plan['fixed_hours']) {
            $end = $start->modify('+'.$plan['fixed_hours'].' hours');
        } elseif (null === $data['endTime']) {
            $form->get('endTime')->addError(new FormError('Please pick an end time.'));

            return null;
        } else {
            $end = $data['endTime'];
            if ($end <= $start) {
                $form->get('endTime')->addError(new FormError('The end time must be after the start time.'));

                return null;
            }
        }

        // Per-hour plans are charged for every started hour.
        $minutes = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        $hours = (int) ceil($minutes / 60);
        if ($hours > 12) {
            $form->get('endTime')->addError(new FormError('A single booking can be at most 12 hours.'));

            return null;
        }

        $pcs = array_map('intval', $data['stations']);
        sort($pcs);

        // The session has to start in the future (Philippine time) —
        // unless an existing booking's date and time were left unchanged.
        $unchanged = null !== $existing
            && $existing->getBookingDate()->format('Y-m-d') === $date->format('Y-m-d')
            && $existing->getBookingTime()->format('H:i') === $start->format('H:i');
        $manila = new \DateTimeZone('Asia/Manila');
        $startsAt = new \DateTimeImmutable($date->format('Y-m-d').' '.$start->format('H:i'), $manila);
        if (!$unchanged && $startsAt < new \DateTimeImmutable('now', $manila)) {
            $form->get('startTime')->addError(new FormError('That time has already passed.'));
        }

        // Sessions can't run past midnight.
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) {
            $form->get('startTime')->addError(new FormError(sprintf(
                'A %d-hour session starting at %s would go past midnight.',
                $hours,
                $start->format('g:i A'),
            )));
        }

        // The plan decides how many PCs are needed.
        if (count($pcs) !== $plan['pcs']) {
            $form->get('stations')->addError(new FormError(sprintf(
                '%s needs exactly %d PC%s.',
                $data['plan'],
                $plan['pcs'],
                1 === $plan['pcs'] ? '' : 's',
            )));
        }

        // Double-booking check (a booking never clashes with itself; cancelled ones don't hold PCs).
        if ($form->isValid() && 'cancelled' !== $existing?->getStatus()) {
            $taken = array_values(array_filter(
                $pcs,
                fn (int $n) => $stationRepository->isTaken($n, $date, $start, $end, $existing?->getId()),
            ));
            if ($taken) {
                $form->get('stations')->addError(new FormError(sprintf(
                    '%s already reserved at that time. Pick other PCs or another time.',
                    'PC '.implode(', PC ', $taken).(1 === count($taken) ? ' is' : ' are'),
                )));
            }
        }

        if (!$form->isValid()) {
            return null;
        }

        return [
            'plan' => $plan,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'pcs' => $pcs,
            'hours' => $hours,
            'pesos' => $plan['per_hour'] ? $plan['price'] * $hours : $plan['price'],
        ];
    }

    private function fillBooking(Booking $booking, array $data, array $result): void
    {
        $booking->setPlanName($data['plan']);
        $booking->setBookingDate($result['date']);
        $booking->setBookingTime($result['start']);
        $booking->setBookingTimeEnd($result['end']);
        $booking->setAmount($result['pesos'] * 100); // stored in centavos
        $booking->setPaymentMethod($data['paymentMethod']);
    }

    private function syncStation(BookingStation $station, array $result): void
    {
        $station->setBookingDate($result['date']);
        $station->setBookingTime($result['start']);
        $station->setBookingTimeEnd($result['end']);
    }
}