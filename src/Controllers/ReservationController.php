<?php
// src/Controllers/ReservationController.php

namespace App\Controllers;

use App\Models\Accommodation;
use App\Models\Reservation;
use App\Helpers\Response;

class ReservationController {
    public function showForm() {
        $id = $_GET['id'] ?? null;
        $room = $id ? Accommodation::find($id) : null;

        if (!$room) {
            die("Invalid room selected.");
        }

        Response::render('guest/book', ['room' => $room]);
    }

    public function create() {
        $resId = Reservation::create($_POST);

        // Redirect to a "Success" page or just show a message
        echo "<h1>Booking Successful!</h1>";
        echo "<p>Your reservation ID is: <strong>$resId</strong></p>";
        echo "<a href='?action=home'>Return Home</a>";
        exit;
    }
}
