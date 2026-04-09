<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\LocationModel;
use App\Models\UserModel;
use App\Models\LandlordRequestModel;
use App\Models\FinancialRecordModel;

use App\Services\ChatbotService;

class ApiController extends BaseController
{
    public function wards(): void
    {
        $districtId = (int)$this->get('district_id', 0);
        $data = $districtId ? (new LocationModel())->getWardsByDistrict($districtId) : [];
        $this->json($data);
    }

    public function streets(): void
    {
        $wardId  = (int)$this->get('ward_id', 0);
        $keyword = trim($this->get('q', ''));
        $loc     = new LocationModel();
        $data    = $keyword ? $loc->searchStreets($keyword) : ($wardId ? $loc->getStreetsByWard($wardId) : []);
        $this->json($data);
    }

    public function chatbot(): void
    {
        $message = trim($this->post('message', ''));
        $service = new ChatbotService();
        $response = $service->processMessage($message);
        $this->json($response);
    }

    // ── Landlord Request API ───────────────────────────────────────────────

    /**
     * POST /api/landlord/request
     * Tenant submits a request to become a landlord.
     */
    public function landlordRequest(): void
    {
        if (!$this->isLoggedIn()) {
            $this->json(['error' => 'Unauthorized'], 401);
            return;
        }

        $user       = $this->currentUser();
        $userRecord = (new UserModel())->findById($user['id']);

        // Role guard — only tenants may apply
        if (!$userRecord || $userRecord['role'] !== 'tenant') {
            $this->json(['error' => 'Chỉ người thuê mới có thể đăng ký làm chủ trọ.'], 403);
            return;
        }

        // Block duplicate pending requests
        if (($userRecord['landlord_status'] ?? 'none') === 'pending') {
            $this->json(['error' => 'Yêu cầu của bạn đang chờ duyệt.'], 409);
            return;
        }

        // Validate inputs
        $name  = trim($this->post('name', ''));
        $phone = trim($this->post('phone', ''));
        $desc  = trim($this->post('description', ''));

        if (mb_strlen($name) < 2) {
            $this->json(['error' => 'Họ tên phải có ít nhất 2 ký tự.'], 422);
            return;
        }
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
            $this->json(['error' => 'Số điện thoại không hợp lệ (9–11 chữ số).'], 422);
            return;
        }

        $model = new LandlordRequestModel();
        $id    = $model->create([
            'user_id'     => $user['id'],
            'name'        => $name,
            'phone'       => $phone,
            'description' => $desc ?: null,
            'status'      => 'pending',
        ]);

        (new UserModel())->update($user['id'], ['landlord_status' => 'pending']);

        $this->json(['success' => true, 'id' => (int)$id]);
    }

    /**
     * GET /api/admin/landlord-requests
     * Admin: list all pending requests.
     */
    public function adminGetLandlordRequests(): void
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }
        $status = $this->get('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }
        $this->json((new LandlordRequestModel())->getAllForAdmin($status));
    }

    /**
     * POST /api/admin/approve-landlord
     * Admin: approve a landlord request by id.
     */
    public function adminApproveLandlord(): void
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }

        $id    = (int)$this->post('id', 0);
        $model = new LandlordRequestModel();
        $req   = $model->findById($id);

        if (!$req || $req['status'] !== 'pending') {
            $this->json(['error' => 'Request not found or already processed.'], 404);
            return;
        }

        $model->approve($id, $_SESSION['user_id']);

        $users = new UserModel();
        $users->update($req['user_id'], [
            'role'            => 'landlord',
            'landlord_status' => 'approved',
        ]);
        $users->addNotification(
            $req['user_id'],
            'Yêu cầu làm chủ trọ đã được duyệt ✅',
            'Chúc mừng! Tài khoản của bạn đã được nâng cấp thành Chủ trọ.',
            'success'
        );

        $this->json(['success' => true]);
    }

    /**
     * POST /api/admin/reject-landlord
     * Admin: reject a landlord request by id.
     */
    public function adminRejectLandlord(): void
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }

        $id     = (int)$this->post('id', 0);
        $reason = trim($this->post('reason', 'Không đáp ứng yêu cầu'));
        $model  = new LandlordRequestModel();
        $req    = $model->findById($id);

        if (!$req || $req['status'] !== 'pending') {
            $this->json(['error' => 'Request not found or already processed.'], 404);
            return;
        }

        $model->reject($id, $_SESSION['user_id'], $reason);

        $users = new UserModel();
        $users->update($req['user_id'], ['landlord_status' => 'rejected']);
        $users->addNotification(
            $req['user_id'],
            'Yêu cầu làm chủ trọ bị từ chối ❌',
            "Yêu cầu của bạn bị từ chối. Lý do: {$reason}. Bạn có thể gửi lại yêu cầu mới.",
            'danger'
        );

        $this->json(['success' => true]);
    }

    /* ── Financial summary (landlord only) ──────────────────────────── */

    public function financeSummary(): void
    {
        $user = $this->currentUser();
        if (!$user || $user['role'] !== 'landlord') {
            http_response_code(403);
            $this->json(['error' => 'Forbidden']);
            return;
        }
        $model   = new FinancialRecordModel();
        $summary = $model->getLiveSummary($user['id']);
        $rooms   = $model->getRoomList($user['id']);
        $trend   = $model->getMonthlyTrend($user['id'], 6);

        $this->json([
            'summary' => $summary,
            'rooms'   => $rooms,
            'trend'   => $trend,
        ]);
    }
}
