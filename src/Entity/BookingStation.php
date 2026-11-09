<?php

namespace App\Entity;

use App\Repository\BookingStationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingStationRepository::class)]
class BookingStation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'BookingStations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\Column]
    private ?int $stationNumber = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingTimeEnd = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getStationNumber(): ?int
    {
        return $this->stationNumber;
    }

    public function setStationNumber(int $stationNumber): static
    {
        $this->stationNumber = $stationNumber;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): static
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getBookingTime(): ?\DateTimeImmutable
    {
        return $this->bookingTime;
    }

    public function setBookingTime(\DateTimeImmutable $bookingTime): static
    {
        $this->bookingTime = $bookingTime;

        return $this;
    }

    public function getBookingTimeEnd(): ?\DateTimeImmutable
    {
        return $this->bookingTimeEnd;
    }

    public function setBookingTimeEnd(\DateTimeImmutable $bookingTimeEnd): static
    {
        $this->bookingTimeEnd = $bookingTimeEnd;

        return $this;
    }
}
