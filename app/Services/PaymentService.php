<?php
namespace App\Services;

use App\Models\PaymentModel;
use App\Models\SubscriptionModel;

class PaymentService
{
    private PaymentModel      $paymentModel;
    private SubscriptionModel $subscriptionModel;
    private VNPayService      $vnpay;

    public function __construct()
    {
        $this->paymentModel      = new PaymentModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->vnpay             = new VNPayService();
    }

    public function createPayment(int $userId, string $packageType): array
    {
        if (!isset(PaymentModel::PACKAGES[$packageType])) {
            throw new \InvalidArgumentException("Gói '{$packageType}' không hợp lệ.");
        }

        $package = PaymentModel::PACKAGES[$packageType];
        $txnRef  = VNPayService::generateTxnRef($userId);
        $ip      = VNPayService::getClientIp();

        $paymentId = $this->paymentModel->createPending($userId, $packageType, $txnRef, $ip);

        $this->paymentModel->log($paymentId, 'create', [
            'user_id'     => $userId,
            'package'     => $packageType,
            'amount'      => $package['amount'],
            'txn_ref'     => $txnRef,
        ]);

        $orderInfo = "Nang cap PRO {$package['label']} - User #{$userId}";
        $url = $this->vnpay->buildPaymentUrl($txnRef, $package['amount'], $orderInfo, $ip);

        return ['url' => $url, 'txnRef' => $txnRef];
    }


    public function handleReturn(array $params): array
    {
        // Always log incoming data
        $this->paymentModel->log(null, 'return', $params);

        // 1. Verify hash
        if (!$this->vnpay->verifyHash($params)) {
            return ['success' => false, 'message' => 'Chữ ký không hợp lệ. Giao dịch bị từ chối.', 'payment' => null];
        }

        $txnRef = $params['vnp_TxnRef'] ?? '';
        $payment = $this->paymentModel->findByTxnRef($txnRef);

        if (!$payment) {
            return ['success' => false, 'message' => 'Không tìm thấy giao dịch.', 'payment' => null];
        }

        $this->paymentModel->log($payment['id'], 'return', $params);

        $responseCode = $params['vnp_ResponseCode'] ?? '99';

        if ($this->vnpay->isSuccessCode($responseCode)) {
            if ($payment['status'] === 'pending') {
                $this->confirmPayment($payment, $params);
            }
            $msg = 'Thanh toán thành công! Gói PRO đã được kích hoạt.';
            return ['success' => true, 'message' => $msg, 'payment' => $this->paymentModel->findById($payment['id'])];
        }

        if ($payment['status'] === 'pending') {
            $this->paymentModel->markFailed($payment['id'], $responseCode);
        }

        $msg = VNPayService::responseMessage($responseCode);
        return ['success' => false, 'message' => $msg, 'payment' => $this->paymentModel->findById($payment['id'])];
    }

    public function handleIpn(array $params): array
    {
        $this->paymentModel->log(null, 'ipn', $params);

        if (!$this->vnpay->verifyHash($params)) {
            return ['RspCode' => '97', 'Message' => 'Invalid signature'];
        }

        $txnRef = $params['vnp_TxnRef'] ?? '';
        $payment = $this->paymentModel->findByTxnRef($txnRef);

        if (!$payment) {
            return ['RspCode' => '01', 'Message' => 'Order not found'];
        }

        $expectedAmount = $payment['amount'] * 100;
        $receivedAmount = (int)($params['vnp_Amount'] ?? 0);

        if ($receivedAmount !== $expectedAmount) {
            return ['RspCode' => '04', 'Message' => 'Invalid amount'];
        }

        if ($payment['status'] === 'success') {
            return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
        }
        if ($payment['status'] === 'failed') {
            return ['RspCode' => '02', 'Message' => 'Order already failed'];
        }

        $responseCode = $params['vnp_ResponseCode'] ?? '99';

        if ($this->vnpay->isSuccessCode($responseCode)) {
            $this->confirmPayment($payment, $params);
            return ['RspCode' => '00', 'Message' => 'Confirm success'];
        }

        $this->paymentModel->markFailed($payment['id'], $responseCode);
        return ['RspCode' => '00', 'Message' => 'Confirm failed transaction'];
    }

    private function confirmPayment(array $payment, array $vnpData): void
    {
        $this->paymentModel->markSuccess($payment['id'], $vnpData);

        $packageType = $payment['package_type'];
        $days        = PaymentModel::PACKAGES[$packageType]['days'] ?? 30;
        $amount      = (float)$payment['amount'];

        $this->subscriptionModel->expireForUser($payment['user_id']);

        $this->subscriptionModel->createForUser(
            $payment['user_id'],
            $packageType,
            $days,
            $amount
        );
    }
}
