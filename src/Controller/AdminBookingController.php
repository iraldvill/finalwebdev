<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Form\BookingFormType;
use App\Repository\BookingStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin only: edit, accept, reject, mark paid and delete ANY booking.
 * The list of bookings is the "Bookings" tab on the dashboard.
 */
#[Route('/admin/bookings')]
#[IsGranted('ROLE_ADMIN')]
class AdminBookingController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_admin_booking_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Booking $booking,
        Request $request,
        EntityManagerInterface $em,
        BookingStationRepository $stationRepository,
    ): Response {
        $form = $this->createForm(BookingFormType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = $booking->getBookingTime();
            $end = $booking->getBookingTimeEnd();

            if ($end <= $start) {
                $form->get('bookingTimeEnd')->addError(new FormError('The end time must be after the start time.'));
            } elseif ('cancelled' !== $booking->getStatus() && $clash = $this->findClash($booking, $stationRepository)) {
                $form->addError(new FormError(sprintf('PC %d is already reserved by another booking at that time.', $clash)));
            } else {
                // Keep the reserved PCs in step with the booking's new date/time.
                foreach ($booking->getBookingStations() as $station) {
                    $station->setBookingDate($booking->getBookingDate());
                    $station->setBookingTime($start);
                    $station->setBookingTimeEnd($end);
                }

                $em->flush();
                $this->addFlash('success', 'Booking was updated.');

                return $this->backToBookings();
            }
        }

        return $this->render('admin/booking_form.html.twig', [
            'form' => $form,
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/status/{action}', name: 'app_admin_booking_status', requirements: ['id' => '\d+', 'action' => 'accept|reject'], methods: ['POST'])]
    public function status(Booking $booking, string $action, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('booking-status-'.$booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');

            return $this->backToBookings();
        }

        $booking->setStatus('accept' === $action ? 'confirmed' : 'cancelled');
        $em->flush();

        $this->addFlash('success', 'accept' === $action ? 'Booking accepted.' : 'Booking rejected.');

        return $this->backToBookings();
    }

    #[Route('/{id}/paid', name: 'app_admin_booking_paid', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markPaid(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('booking-paid-'.$booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');

            return $this->backToBookings();
        }

        $booking->setPaymentStatus('paid');
        $em->flush();

        $this->addFlash('success', 'Booking marked as paid.');

        return $this->backToBookings();
    }

    #[Route('/{id}/delete', name: 'app_admin_booking_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-booking-'.$booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');

            return $this->backToBookings();
        }

        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Booking was deleted.');

        return $this->backToBookings();
    }

    /** The first PC of this booking that another booking already holds, or null. */
    private function findClash(Booking $booking, BookingStationRepository $stationRepository): ?int
    {
        foreach ($booking->getBookingStations() as $station) {
            if ($stationRepository->isTaken(
                $station->getStationNumber(),
                $booking->getBookingDate(),
                $booking->getBookingTime(),
                $booking->getBookingTimeEnd(),
                $booking->getId(),
            )) {
                return $station->getStationNumber();
            }
        }

        return null;
    }

    private function backToBookings(): Response
    {
        return $this->redirect($this->generateUrl('app_dashboard').'#bookings');
    }
}