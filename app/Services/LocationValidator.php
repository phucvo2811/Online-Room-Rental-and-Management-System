<?php
namespace App\Services;

use App\Core\Database;

/**
 * LocationValidator - Validates address and location hierarchy
 * Ensures district, ward, street consistency and database integrity
 */
class LocationValidator
{
    /**
     * Validate address hierarchy - ensures street belongs to ward and ward to district
     * @return array Errors array (empty if valid)
     */
    public static function validateAddressHierarchy(int $districtId, int $wardId, int $streetId): array
    {
        $errors = [];

        // Validate district exists
        if ($districtId <= 0) {
            $errors['district_id'] = 'Vui lòng chọn quận/huyện.';
            return $errors;
        }

        $district = Database::fetch("SELECT id FROM districts WHERE id = ?", [$districtId]);
        if (!$district) {
            $errors['district_id'] = 'Quận/huyện không tồn tại.';
        }

        // Validate ward belongs to district
        if ($wardId > 0) {
            $ward = Database::fetch(
                "SELECT id FROM wards WHERE id = ? AND district_id = ?",
                [$wardId, $districtId]
            );
            if (!$ward) {
                $errors['ward_id'] = 'Phường/xã không tồn tại trong quận/huyện đã chọn.';
            }

            // Validate street belongs to ward
            if ($streetId > 0) {
                $street = Database::fetch(
                    "SELECT id FROM streets WHERE id = ? AND ward_id = ?",
                    [$streetId, $wardId]
                );
                if (!$street) {
                    $errors['street_id'] = 'Đường phố không tồn tại trong phường/xã đã chọn.';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate address text field
     */
    public static function validateAddressText(string $address): array
    {
        $errors = [];

        $address = trim($address);
        if (strlen($address) < 5) {
            $errors['address'] = 'Địa chỉ phải có ít nhất 5 ký tự.';
        }
        if (strlen($address) > 255) {
            $errors['address'] = 'Địa chỉ không được vượt quá 255 ký tự.';
        }

        return $errors;
    }

    /**
     * Validate complete address with optional hierarchy
     */
    public static function validateAddress(
        string $address,
        int $districtId = 0,
        int $wardId = 0,
        int $streetId = 0
    ): array {
        $errors = [];

        // Validate address text
        $textErrors = self::validateAddressText($address);
        $errors = array_merge($errors, $textErrors);

        // Validate hierarchy if any location is provided
        if ($districtId > 0 || $wardId > 0 || $streetId > 0) {
            // If any location provided, district must be provided
            if ($districtId <= 0) {
                $errors['district_id'] = 'Vui lòng chọn quận/huyện.';
            } else {
                $hierarchyErrors = self::validateAddressHierarchy($districtId, $wardId, $streetId);
                $errors = array_merge($errors, $hierarchyErrors);
            }
        }

        return $errors;
    }

    /**
     * Get location display string from IDs
     */
    public static function getLocationDisplay(
        int $districtId = 0,
        int $wardId = 0,
        int $streetId = 0
    ): string
    {
        $parts = [];

        if ($streetId > 0) {
            $street = Database::fetch("SELECT name FROM streets WHERE id = ?", [$streetId]);
            if ($street) {
                $parts[] = $street['name'];
            }
        }

        if ($wardId > 0) {
            $ward = Database::fetch("SELECT name FROM wards WHERE id = ?", [$wardId]);
            if ($ward) {
                $parts[] = $ward['name'];
            }
        }

        if ($districtId > 0) {
            $district = Database::fetch("SELECT name FROM districts WHERE id = ?", [$districtId]);
            if ($district) {
                $parts[] = $district['name'];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Format complete address with hierarchy
     */
    public static function formatAddress(
        string $address,
        int $districtId = 0,
        int $wardId = 0,
        int $streetId = 0
    ): string
    {
        $parts = [];

        if (!empty($address)) {
            $parts[] = trim($address);
        }

        $locationDisplay = self::getLocationDisplay($districtId, $wardId, $streetId);
        if (!empty($locationDisplay)) {
            $parts[] = $locationDisplay;
        }

        return implode(', ', $parts);
    }

    /**
     * Get all districts
     */
    public static function getAllDistricts(): array
    {
        return Database::fetchAll("SELECT id, name FROM districts ORDER BY name ASC") ?? [];
    }

    /**
     * Get wards for a district
     */
    public static function getWardsByDistrict(int $districtId): array
    {
        return Database::fetchAll(
            "SELECT id, name FROM wards WHERE district_id = ? ORDER BY name ASC",
            [$districtId]
        ) ?? [];
    }

    /**
     * Get streets for a ward
     */
    public static function getStreetsByWard(int $wardId): array
    {
        return Database::fetchAll(
            "SELECT id, name FROM streets WHERE ward_id = ? ORDER BY name ASC",
            [$wardId]
        ) ?? [];
    }
}
