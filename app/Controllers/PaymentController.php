<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PaymentModel;
use App\Services\PaymentService;

/**
 * PaymentController
 *
 * Handles all payment-related HTTP actions:
 *   GET  /payment/packages  — package selection page
 *   POST /payment/create    — create VNPay payment & redirect
 *   GET  /payment/return    — VNPay return URL (browser redirect)
 *   GET  /payment/ipn       — VNPay IPN (server notification)
 *   GET  /payment/history   — user's payment history
 */
class PaymentController extends BaseController
{
    private PaymentService $service;
    private PaymentModel   $paymentModel;

    public function __construct()
    {
        parent::__construct();
        $this->service      = new PaymentService();
        $this->paymentModel = new PaymentModel();
    }

    /* ── Package selection page ──────────────────────────────────────── */

    public function packages(): void
    {
        $this->requireAuth();

        $this->view('payment/packages', [
            'packages'  => PaymentModel::PACKAGES,
            'csrf'      => $this->generateCsrf(),
            'page_title'=> 'Nâng cấp PRO',
        ]);
    }

    /* ── Create payment (POST) ───────────────────────────────────────── */

    public function create(): void
    {
        $this->requireAuth();

        // CSRF check
        if (!$this->validateCsrf()) {
            $this->setFlash('danger', 'Yêu cầu không hợp lệ.');
            $this->redirect('/payment/packages');
        }

        $packageType = $this->post('package_type', '');
        $userId      = (int)($_SESSION['user_id']);

        if (!isset(PaymentModel::PACKAGES[$packageType])) {
            $this->setFlash('danger', 'Gói không hợp lệ.');
            $this->redirect('/payment/packages');
        }

        try {
            $result = $this->service->createPayment($userId, $packageType);
            // Redirect user to VNPay
            header('Location: ' . $result['url']);
            exit;
        } catch (\Exception $e) {
            $this->setFlash('danger', 'Không thể tạo thanh toán: ' . $e->getMessage());
            $this->redirect('/payment/packages');
        }
    }

    /* ── VNPay Return URL ────────────────────────────────────────────── */

    public function returnUrl(): void
    {
        // VNPay sends GET params — no auth check here (user may have lost session)
        $params = $_GET;
        $result = $this->service->handleReturn($params);

        if ($result['success']) {
            $this->view('payment/success', [
                'message'     => $result['message'],
                'payment'     => $result['payment'],
                'packages'    => PaymentModel::PACKAGES,
                'page_title'  => 'Thanh toán thành công',
            ]);
        } else {
            $this->view('payment/failed', [
                'message'     => $result['message'],
                'payment'     => $result['payment'],
                'packages'    => PaymentModel::PACKAGES,
                'page_title'  => 'Thanh toán thất bại',
            ]);
        }
    }

    /* ── VNPay IPN (server-to-server) ────────────────────────────────── */

    public function ipn(): void
    {
        // IPN must always return JSON — never redirect
        $result = $this->service->handleIpn($_GET);
        $this->json($result);
    }

    /* ── User payment history ────────────────────────────────────────── */

    public function history(): void
    {
        $this->requireAuth();
        $userId   = (int)$_SESSION['user_id'];
        $payments = $this->paymentModel->getForUser($userId);

        $this->view('payment/history', [
            'payments'   => $payments,
            'packages'   => PaymentModel::PACKAGES,
            'page_title' => 'Lịch sử thanh toán',
        ]);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    /**
     * Validate the CSRF token submitted in a POST form.
     */
    private function validateCsrf(): bool
    {
        $token = $this->post('_csrf', '');
        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
