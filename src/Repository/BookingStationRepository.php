<?php

namespace App\Repository;

use App\Entity\BookingStation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingStation>
 */
class BookingStationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingStation::class);
    }

    /**
     * Your old double-booking check: is this PC already reserved on that
     * day for any time that overlaps start-end? Cancelled bookings don't count.
     * Pass $exceptBookingId when editing a booking so it doesn't clash with itself.
     */
    public function isTaken(
        int $stationNumber,
        \DateTimeImmutable $date,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $exceptBookingId = null,
    ): bool {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.booking', 'b')
            ->where('s.stationNumber = :station')
            ->andWhere('s.bookingDate = :date')
            ->andWhere('b.status != :cancelled')
            ->andWhere('s.bookingTime < :end')
            ->andWhere('s.bookingTimeEnd > :start')
            ->setParameter('station', $stationNumber)
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('start', $start, Types::TIME_IMMUTABLE)
            ->setParameter('end', $end, Types::TIME_IMMUTABLE);

        if (null !== $exceptBookingId) {
            $qb->andWhere('b.id != :except')->setParameter('except', $exceptBookingId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Which PC numbers are already reserved on that day for any time overlapping start-end?
     * Used by the staff booking form to grey out unavailable PCs.
     * Cancelled bookings don't count; $exceptBookingId skips the booking being edited.
     *
     * @return int[]
     */
    public function findTakenNumbers(
        \DateTimeImmutable $date,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $exceptBookingId = null,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT s.stationNumber')
            ->join('s.booking', 'b')
            ->where('s.bookingDate = :date')
            ->andWhere('b.status != :cancelled')
            ->andWhere('s.bookingTime < :end')
            ->andWhere('s.bookingTimeEnd > :start')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('start', $start, Types::TIME_IMMUTABLE)
            ->setParameter('end', $end, Types::TIME_IMMUTABLE);

        if (null !== $exceptBookingId) {
            $qb->andWhere('b.id != :except')->setParameter('except', $exceptBookingId);
        }

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'stationNumber'));
    }

    /**
     * Every PC reserved on the given day (for the PC Stations tab).
     *
     * @return BookingStation[]
     */
    public function findForDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.booking', 'b')->addSelect('b')
            ->join('b.user', 'u')->addSelect('u')
            ->where('s.bookingDate = :date')
            ->andWhere('b.status != :cancelled')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getResult();
    }
}