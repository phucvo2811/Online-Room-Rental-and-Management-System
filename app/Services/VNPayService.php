<?php
namespace App\Services;

/**
 * VNPay Integration Helper
 *
 * Handles: URL generation, secure hash signing/verification.
 * Docs: https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.md
 *
 * Config constants expected (set in config/app.php → bootstrap/app.php):
 *   VNP_TMN_CODE   – Terminal code from VNPay merchant portal
 *   VNP_HASH_SECRET – Hash secret from VNPay merchant portal
 *   VNP_URL        – Payment gateway URL
 *   VNP_RETURN_URL – Your return URL after payment
 */
class VNPayService
{
    // Vietnamese timezone offset (+7) used by VNPay datetime format
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';
    private const DATE_FORMAT = 'YmdHis';

    private string $tmnCode;
    private string $hashSecret;
    private string $gatewayUrl;
    private string $returnUrl;

    public function __construct()
    {
        $this->tmnCode    = defined('VNP_TMN_CODE')    ? VNP_TMN_CODE    : '';
        $this->hashSecret = defined('VNP_HASH_SECRET') ? VNP_HASH_SECRET : '';
        $this->gatewayUrl = defined('VNP_URL')         ? VNP_URL         : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
        $this->returnUrl  = defined('VNP_RETURN_URL')  ? VNP_RETURN_URL  : (APP_URL . '/payment/return');
    }

    /**
     * Build the VNPay payment URL for a transaction.
     *
     * @param  string $txnRef     Unique order code (our reference)
     * @param  int    $amount     Amount in VND (integer, NOT x100 yet)
     * @param  string $orderInfo  Human-readable order description
     * @param  string $ipAddress  Client IP address
     * @return string             Full redirect URL for the user's browser
     */
    public function buildPaymentUrl(
        string $txnRef,
        int    $amount,
        string $orderInfo,
        string $ipAddress
    ): string {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        // VNPay requires create date and expire date
        $createDate = $now->format(self::DATE_FORMAT);
        $expireDate = $now->modify('+15 minutes')->format(self::DATE_FORMAT);

        // VNPay amount must be multiplied by 100
        $params = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $this->tmnCode,
            'vnp_Amount'     => $amount * 100,
            'vnp_CreateDate' => $createDate,
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => $ipAddress,
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => $orderInfo,
            'vnp_OrderType'  => 'other',
            'vnp_ReturnUrl'  => $this->returnUrl,
            'vnp_TxnRef'     => $txnRef,
            'vnp_ExpireDate' => $expireDate,
        ];

        // Sort params alphabetically by key (required by VNPay spec)
        ksort($params);

        // Build query string for signing (no URL encoding of values yet)
        $hashData = [];
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null) {
                $hashData[] = urlencode((string)$k) . '=' . urlencode((string)$v);
            }
        }
        $hashString = implode('&', $hashData);
        $secureHash = hash_hmac('sha512', $hashString, $this->hashSecret);

        return $this->gatewayUrl . '?' . $hashString . '&vnp_SecureHash=' . $secureHash;
    }

    /**
     * Verify the secure hash on a VNPay callback (return or IPN).
     * Remove vnp_SecureHash before hashing, then compare.
     *
     * @param  array $params  Raw GET params from VNPay
     * @return bool
     */
    public function verifyHash(array $params): bool
    {
        if (empty($params['vnp_SecureHash'])) {
            return false;
        }

        $receivedHash = $params['vnp_SecureHash'];

        // Remove hash fields before re-computing
        $filtered = $params;
        unset($filtered['vnp_SecureHash'], $filtered['vnp_SecureHashType']);

        ksort($filtered);

        $hashParts = [];
        foreach ($filtered as $k => $v) {
            if ($v !== '' && $v !== null) {
                $hashParts[] = urlencode((string)$k) . '=' . urlencode((string)$v);
            }
        }
        $hashString    = implode('&', $hashParts);
        $expectedHash  = hash_hmac('sha512', $hashString, $this->hashSecret);

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expectedHash, $receivedHash);
    }

    /**
     * Check whether the VNPay response code indicates a successful payment.
     */
    public function isSuccessCode(string $responseCode): bool
    {
        return $responseCode === '00';
    }

    /**
     * Generate a unique transaction reference (max 40 chars for VNPay).
     * Format: TS<userId><microtime_hex>
     */
    public static function generateTxnRef(int $userId): string
    {
        $micro = str_replace('.', '', (string)microtime(true));
        return 'TS' . $userId . $micro . random_int(100, 999);
    }

    /**
     * Get the client real IP, respecting common proxy headers.
     */
    public static function getClientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_CLIENT_IP']        ?? null,
            $_SERVER['REMOTE_ADDR']           ?? null,
        ];
        foreach ($candidates as $ip) {
            if ($ip && filter_var(explode(',', $ip)[0], FILTER_VALIDATE_IP)) {
                return explode(',', $ip)[0];
            }
        }
        return '127.0.0.1';
    }

    /** Human-readable description for VNPay response codes */
    public static function responseMessage(string $code): string
    {
        return match($code) {
            '00' => 'Giao dịch thành công',
            '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
            '09' => 'Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking.',
            '10' => 'Xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
            '11' => 'Đã hết hạn chờ thanh toán.',
            '12' => 'Thẻ/Tài khoản bị khóa.',
            '13' => 'Sai mật khẩu OTP.',
            '24' => 'Khách hàng hủy giao dịch.',
            '51' => 'Tài khoản không đủ số dư.',
            '65' => 'Vượt hạn mức giao dịch trong ngày.',
            '75' => 'Ngân hàng thanh toán đang bảo trì.',
            '79' => 'Nhập sai mật khẩu thanh toán quá số lần quy định.',
            '99' => 'Lỗi không xác định.',
            default => 'Giao dịch thất bại (mã: ' . $code . ').',
        };
    }
}
