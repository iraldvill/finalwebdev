<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * All bookings, newest first, with the customer and PCs loaded in one query.
     *
     * @return Booking[]
     */
    public function findAllNewestFirst(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')->addSelect('u')
            ->leftJoin('b.bookingStations', 's')->addSelect('s')
            ->orderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.bookingTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Bookings that are "confirmed" or "active". */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('LOWER(b.status) IN (:statuses)')
            ->setParameter('statuses', ['active', 'confirmed'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Bookings whose booking date is the given day. */
    public function countOnDate(\DateTimeImmutable $date): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.bookingDate = :date')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}