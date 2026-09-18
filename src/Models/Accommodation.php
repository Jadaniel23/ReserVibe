<?php
// src/Models/Accommodation.php

namespace App\Models;

class Accommodation {
    /**
     * Returns a list of available rooms.
     * Updated to match Verde Royale Resort tiers.
     */
    public static function getAll() {
        return [
            ['id' => 1, 'name' => 'Open Cottage (10 pax)', 'price' => 1500, 'description' => 'Perfect for small groups and outdoor gatherings.'],
            ['id' => 2, 'name' => 'Kubo Gazebo (15-20 pax)', 'price' => 2500, 'description' => 'Traditional Filipino gazebo for larger groups.'],
            ['id' => 3, 'name' => 'Standard AC Room (2-4 pax)', 'price' => 3500, 'description' => 'Cool and comfortable room for a small family.'],
            ['id' => 4, 'name' => 'Family AC Room (6-10 pax)', 'price' => 5500, 'description' => 'Spacious air-conditioned room for the whole family.'],
        ];
    }

    /**
     * Finds a specific room by ID.
     */
    public static function find($id) {
        $rooms = self::getAll();
        foreach ($rooms as $room) {
            if ($room['id'] == $id) {
                return $room;
            }
        }
        return null;
    }
}
