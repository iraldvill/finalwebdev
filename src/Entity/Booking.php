<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $planName = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingTime = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingTimeEnd = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 20)]
    private ?string $paymentStatus = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'booking')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $User = null;

    /**
     * @var Collection<int, BookingStation>
     */
    #[ORM\OneToMany(targetEntity: BookingStation::class, mappedBy: 'booking', orphanRemoval: true)]
    private Collection $BookingStations;

    public function __construct()
    {
        $this->BookingStations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanName(): ?string
    {
        return $this->planName;
    }

    public function setPlanName(string $planName): static
    {
        $this->planName = $planName;

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

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): static
    {
        $this->User = $User;

        return $this;
    }

    /**
     * @return Collection<int, BookingStation>
     */
    public function getBookingStations(): Collection
    {
        return $this->BookingStations;
    }

    public function addBookingStation(BookingStation $bookingStation): static
    {
        if (!$this->BookingStations->contains($bookingStation)) {
            $this->BookingStations->add($bookingStation);
            $bookingStation->setBooking($this);
        }

        return $this;
    }

    public function removeBookingStation(BookingStation $bookingStation): static
    {
        if ($this->BookingStations->removeElement($bookingStation)) {
            // set the owning side to null (unless already changed)
            if ($bookingStation->getBooking() === $this) {
                $bookingStation->setBooking(null);
            }
        }

        return $this;
    }
}
