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
     */
    public function isTaken(
        int $stationNumber,
        \DateTimeImmutable $date,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): bool {
        $count = $this->createQueryBuilder('s')
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
            ->setParameter('end', $end, Types::TIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
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