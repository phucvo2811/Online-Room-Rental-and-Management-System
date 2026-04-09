<?php
namespace App\Services;

/**
 * PropertyValidator - Handles validation for the two property groups
 * GROUP 1: Composite (boarding_house, dormitory, homestay)
 * GROUP 2: Single (mini_house, full_house)
 */
class PropertyValidator
{
    const GROUP_COMPOSITE = ['boarding_house', 'dormitory', 'homestay'];
    const GROUP_SINGLE = ['mini_house', 'full_house'];
    const ALL_TYPES = ['boarding_house', 'dormitory', 'homestay', 'mini_house', 'full_house'];

    /**
     * Check if property type is composite (contains multiple rooms)
     */
    public static function isComposite(string $type): bool
    {
        return in_array($type, self::GROUP_COMPOSITE);
    }

    /**
     * Check if property type is single/standalone
     */
    public static function isSingle(string $type): bool
    {
        return in_array($type, self::GROUP_SINGLE);
    }

    /**
     * Validate property type
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::ALL_TYPES);
    }

    /**
     * Validate composite property (boarding_house, dormitory, homestay)
     * Required: name, address, type, price_min, price_max
     */
    public static function validateComposite(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Tên bất động sản là bắt buộc.';
        }

        if (empty(trim($data['address'] ?? ''))) {
            $errors['address'] = 'Địa chỉ là bắt buộc.';
        }

        if (!self::isComposite($data['type'] ?? '')) {
            $errors['type'] = 'Loại bất động sản không hợp lệ cho nhóm này.';
        }

        if (empty($data['price_min']) || !is_numeric($data['price_min']) || (float)$data['price_min'] <= 0) {
            $errors['price_min'] = 'Giá tối thiểu là bắt buộc và phải lớn hơn 0.';
        }

        if (empty($data['price_max']) || !is_numeric($data['price_max']) || (float)$data['price_max'] <= 0) {
            $errors['price_max'] = 'Giá tối đa là bắt buộc và phải lớn hơn 0.';
        }

        if (!empty($data['price_min']) && !empty($data['price_max']) && 
            is_numeric($data['price_min']) && is_numeric($data['price_max']) &&
            (float)$data['price_min'] > (float)$data['price_max']) {
            $errors['price_min'] = 'Giá tối thiểu không được lớn hơn giá tối đa.';
        }

        return $errors;
    }

    /**
     * Validate single property (mini_house, full_house)
     * Required: name, address, type, price, area
     */
    public static function validateSingle(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Tên bất động sản là bắt buộc.';
        }

        if (empty(trim($data['address'] ?? ''))) {
            $errors['address'] = 'Địa chỉ là bắt buộc.';
        }

        if (!self::isSingle($data['type'] ?? '')) {
            $errors['type'] = 'Loại bất động sản không hợp lệ cho nhóm này.';
        }

        if (empty($data['price']) || !is_numeric($data['price']) || (float)$data['price'] <= 0) {
            $errors['price'] = 'Giá là bắt buộc và phải lớn hơn 0.';
        }

        if (empty($data['area']) || !is_numeric($data['area']) || (float)$data['area'] <= 0) {
            $errors['area'] = 'Diện tích là bắt buộc và phải lớn hơn 0.';
        }

        return $errors;
    }

    /**
     * Validate property by its type
     * Routes to appropriate validator based on GROUP
     */
    public static function validate(array $data): array
    {
        $type = $data['type'] ?? '';

        if (!self::isValidType($type)) {
            return ['type' => 'Loại bất động sản không hợp lệ.'];
        }

        if (self::isComposite($type)) {
            return self::validateComposite($data);
        } else {
            return self::validateSingle($data);
        }
    }

    /**
     * Format property price for display
     */
    public static function formatPrice(array $property): string
    {
        if (self::isComposite($property['type'] ?? '')) {
            $min = $property['price_min'] ?? $property['computed_price_min'] ?? 0;
            $max = $property['price_max'] ?? $property['computed_price_max'] ?? 0;
            return number_format($min, 0, ',', '.') . 'đ - ' . 
                   number_format($max, 0, ',', '.') . 'đ / tháng';
        } else {
            $price = $property['price'] ?? 0;
            return number_format($price, 0, ',', '.') . 'đ / tháng';
        }
    }

    /**
     * Get property type label in Vietnamese
     */
    public static function getTypeLabel(string $type): string
    {
        $labels = [
            'boarding_house' => 'Nhà trọ',
            'dormitory'      => 'Ký túc xá',
            'mini_house'     => 'Căn hộ Mini',
            'full_house'     => 'Nhà nguyên căn',
            'homestay'       => 'Homestay',
        ];
        return $labels[$type] ?? $type;
    }
}
