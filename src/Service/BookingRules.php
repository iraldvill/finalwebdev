<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\BookingStation;
use App\Repository\BookingStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The booking rules shared by staff and customers: plans and prices, the form checks
 * (past times, midnight, PC count, double booking) and saving the reserved PCs.
 */
class BookingRules
{
    /**
     * Price in pesos, how many PCs the plan needs, and a fixed length in hours (null = the user picks).
     * 'per_hour' plans are charged price × hours; the others are a flat price.
     */
    public const PLANS = [
        'Hourly Gaming' => ['price' => 50, 'per_hour' => true, 'pcs' => 1, 'fixed_hours' => null],
        'Extended Gaming' => ['price' => 200, 'per_hour' => false, 'pcs' => 1, 'fixed_hours' => 5],
        'Gaming Package' => ['price' => 180, 'per_hour' => true, 'pcs' => 3, 'fixed_hours' => null],
    ];

    public function __construct(private readonly BookingStationRepository $stationRepository)
    {
    }

    /**
     * Checks a submitted booking form. Adds errors to the form and returns null when something
     * is wrong; otherwise returns the worked-out date, times, PCs and price.
     *
     * @return array{plan: array, date: \DateTimeImmutable, start: \DateTimeImmutable, end: \DateTimeImmutable, pcs: int[], hours: int, pesos: int}|null
     */
    public function check(FormInterface $form, ?Booking $existing = null): ?array
    {
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

        // Extended Gaming always lasts 5 hours; the other plans use the end time that was picked.
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
            $taken = $this->stationRepository->findTakenNumbers($date, $start, $end, $existing?->getId());
            $clash = array_values(array_intersect($pcs, $taken));
            if ($clash) {
                $form->get('stations')->addError(new FormError(sprintf(
                    '%s already reserved at that time. Pick other PCs or another time.',
                    'PC '.implode(', PC ', $clash).(1 === count($clash) ? ' is' : ' are'),
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

    /** Copies the checked answers onto the booking. */
    public function fill(Booking $booking, array $data, array $result): void
    {
        $booking->setPlanName($data['plan']);
        $booking->setBookingDate($result['date']);
        $booking->setBookingTime($result['start']);
        $booking->setBookingTimeEnd($result['end']);
        $booking->setAmount($result['pesos'] * 100); // stored in centavos
        $booking->setPaymentMethod($data['paymentMethod']);
    }

    /** Keeps the booking's reserved PCs in step: drops unticked ones, adds new ones, updates the rest. */
    public function syncStations(Booking $booking, array $result, EntityManagerInterface $em): void
    {
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
            $station->setBookingDate($result['date']);
            $station->setBookingTime($result['start']);
            $station->setBookingTimeEnd($result['end']);
        }
    }

    /**
     * For the booking form's live availability: which PCs are taken for ?date=&start=&end=&except=
     *
     * @return int[]
     */
    public function takenNumbers(Request $request): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('date'));
        $start = \DateTimeImmutable::createFromFormat('!H:i', substr($request->query->getString('start'), 0, 5));
        $end = \DateTimeImmutable::createFromFormat('!H:i', substr($request->query->getString('end'), 0, 5));

        if (!$date || !$start || !$end || $end <= $start) {
            return [];
        }

        return $this->stationRepository->findTakenNumbers($date, $start, $end, $request->query->getInt('except') ?: null);
    }
}