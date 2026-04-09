<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\UserModel;
use App\Models\RoomModel;
use App\Models\RoomBlockModel;
use App\Models\SubscriptionModel;
use App\Models\LandlordProfileModel;
use App\Models\FinancialRecordModel;
use App\Models\LandlordRequestModel;

class UserController extends BaseController
{
    private UserModel $users;
    private SubscriptionModel $subscriptions;
    private LandlordProfileModel $landlordProfiles;
    private FinancialRecordModel $financialRecords;

    public function __construct()
    {
        parent::__construct();
        $this->users             = new UserModel();
        $this->subscriptions     = new SubscriptionModel();
        $this->landlordProfiles  = new LandlordProfileModel();
        $this->financialRecords  = new FinancialRecordModel();
    }

    public function dashboard(): void
    {
        $user      = $this->currentUser();
        $blockModel = new RoomBlockModel();
        $roomModel  = new RoomModel();

        $myBlocks  = $user['role'] === 'landlord' ? $blockModel->getByUser($user['id']) : [];
        $viewCount = $user['role'] === 'landlord' ? $roomModel->getTotalViewsByUser($user['id']) : 0;

        // Landlord upgrade status (for tenant banner)
        $userRecord     = $this->users->findById($user['id']);
        $landlordStatus = $userRecord['landlord_status'] ?? 'none';

        $proStatus = [
            'is_pro'    => false,
            'plan'      => null,
            'ends_at'   => null,
            'days_left' => 0,
        ];

        if ($user['role'] === 'landlord') {
            $sub = $this->subscriptions->getActiveForUser($user['id']);
            if ($sub) {
                $ends = new \DateTime($sub['ends_at']);
                $now  = new \DateTime();
                $diff = $now->diff($ends);
                $days = (int)$diff->format('%r%a');
                $proStatus = [
                    'is_pro'    => true,
                    'plan'      => $sub['plan'],
                    'ends_at'   => $sub['ends_at'],
                    'days_left' => max(0, $days),
                ];
            }
        }

        $profile = $user['role'] === 'landlord' ? $this->landlordProfiles->getByUser($user['id']) : null;

        // Live financial summary + room list (replaces manual calc)
        $financeSummary  = $user['role'] === 'landlord'
            ? $this->financialRecords->getLiveSummary($user['id'])
            : [];
        $roomListFinance = $user['role'] === 'landlord'
            ? $this->financialRecords->getRoomList($user['id'])
            : [];
        $monthlyTrend    = $user['role'] === 'landlord'
            ? $this->financialRecords->getMonthlyTrend($user['id'], 6)
            : [];

        $this->users->markAllRead($user['id']);
        $this->view('user/dashboard', [
            'myBlocks'         => $myBlocks,
            'favorites'        => $this->users->getFavorites($user['id']),
            'notifs'           => $this->users->getNotifications($user['id']),
            'proStatus'        => $proStatus,
            'proPlans'         => APP_CONFIG['pro_plans'] ?? [],
            'landlordProfile'  => $profile,
            'landlordStatus'   => $landlordStatus,
            'financeSummary'   => $financeSummary,
            'roomListFinance'  => $roomListFinance,
            'monthlyTrend'     => $monthlyTrend,
            'viewCount'        => $viewCount,
            'pageTitle'        => 'Dashboard',
        ]);
    }

    public function profile(): void
    {
        $user = $this->users->findById($_SESSION['user_id']);
        $this->view('user/profile', ['user' => $user, 'pageTitle' => 'Hồ sơ của tôi']);
    }

    public function updateProfile(): void
    {
        $data   = ['name' => trim($this->post('name', '')), 'phone' => trim($this->post('phone', ''))];
        $errors = [];
        if (mb_strlen($data['name']) < 2) $errors['name'] = 'Tên quá ngắn';

        $avatar = $this->uploadSingle('avatar');
        if ($avatar) {
            $data['avatar']          = $avatar;
            $_SESSION['user_avatar'] = $avatar;
        }

        if (!empty($this->post('new_password'))) {
            $user = $this->users->findById($_SESSION['user_id']);
            if (!$this->users->verifyPassword($this->post('current_password', ''), $user['password'])) {
                $errors['current_password'] = 'Mật khẩu hiện tại không đúng';
            } elseif (strlen($this->post('new_password')) < 6) {
                $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            } else {
                $data['password'] = password_hash($this->post('new_password'), PASSWORD_BCRYPT);
            }
        }

        if (empty($errors)) {
            $this->users->update($_SESSION['user_id'], $data);
            $_SESSION['user_name'] = $data['name'];
            $this->setFlash('success', 'Cập nhật hồ sơ thành công!');
            $this->redirect('/profile');
        }
        $user = $this->users->findById($_SESSION['user_id']);
        $this->view('user/profile', ['user' => $user, 'errors' => $errors, 'pageTitle' => 'Hồ sơ']);
    }

    public function purchasePro(): void
    {
        $user = $this->currentUser();
        if ($user['role'] !== 'landlord') {
            $this->setFlash('danger', 'Chức năng chỉ dành cho chủ trọ.');
            $this->redirect('/dashboard');
        }

        $planId = $this->post('plan_id', '');
        $plans  = APP_CONFIG['pro_plans'] ?? [];
        $plan   = null;
        foreach ($plans as $p) {
            if (($p['id'] ?? '') === $planId) {
                $plan = $p;
                break;
            }
        }

        if (!$plan) {
            $this->setFlash('danger', 'Gói PRO không hợp lệ.');
            $this->redirect('/dashboard');
        }

        $this->subscriptions->expireForUser($user['id']);
        $this->subscriptions->createForUser($user['id'], $plan['title'], (int)$plan['days'], (float)($plan['price'] ?? 0));

        $this->setFlash('success', "Đã kích hoạt gói {$plan['title']} ({$plan['days']} ngày). Đã cập nhật trạng thái PRO.");
        $this->redirect('/dashboard');
    }

    public function saveFinancials(): void
    {
        $user = $this->currentUser();
        if ($user['role'] !== 'landlord') {
            $this->setFlash('danger', 'Chức năng chỉ dành cho chủ trọ.');
            $this->redirect('/dashboard');
        }

        $costElectricity = (float)$this->post('cost_electricity', 0);
        $costWater       = (float)$this->post('cost_water', 0);
        $costMaintenance = (float)$this->post('cost_maintenance', 0);
        $costOther       = (float)$this->post('cost_other', 0);

        // Save cost breakdown to landlord_profiles
        $this->landlordProfiles->upsert($user['id'], [
            'cost_electricity' => $costElectricity,
            'cost_water'       => $costWater,
            'cost_maintenance' => $costMaintenance,
            'cost_other'       => $costOther,
        ]);

        // Take a monthly snapshot for trend chart
        $live = $this->financialRecords->getLiveSummary($user['id']);
        $this->financialRecords->snapshotForUser($user['id'], $live);

        $this->setFlash('success', 'Đã cập nhật chi phí và lưu báo cáo tháng.');
        $this->redirect('/dashboard');
    }

    public function favorites(): void
    {
        $this->view('user/favorites', [
            'favorites' => $this->users->getFavorites($_SESSION['user_id']),
            'pageTitle' => 'Yêu thích',
        ]);
    }

    public function toggleFavorite(): void
    {
        $roomId = (int)$this->post('room_id', 0);
        $result = $this->users->toggleFavorite($_SESSION['user_id'], $roomId);
        $this->json(['status' => $result]);
    }

    // ── Landlord Request ───────────────────────────────────────────────────

    /**
     * Show the landlord registration form (or current request status).
     * GET /landlord-request
     */
    public function landlordRequestForm(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        // Already a landlord — nothing to do here
        if ($user['role'] === 'landlord') {
            $this->setFlash('info', 'Bạn đã là chủ trọ.');
            $this->redirect('/dashboard');
        }

        $userRecord     = $this->users->findById($user['id']);
        $landlordStatus = $userRecord['landlord_status'] ?? 'none';
        $latestRequest  = (new LandlordRequestModel())->getLatestByUser($user['id']);

        $this->view('user/landlord-request', [
            'landlordStatus' => $landlordStatus,
            'latestRequest'  => $latestRequest,
            'pageTitle'      => 'Đăng ký làm chủ trọ',
            'csrf'           => $this->generateCsrf(),
        ]);
    }

    /**
     * Handle landlord registration form submission.
     * POST /landlord-request
     */
    public function submitLandlordRequest(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        // Backend role guard — do not trust session alone
        $userRecord = $this->users->findById($user['id']);
        if (!$userRecord || $userRecord['role'] !== 'tenant') {
            $this->setFlash('danger', 'Chỉ người thuê mới có thể đăng ký làm chủ trọ.');
            $this->redirect('/dashboard');
        }

        // CSRF check
        $token = $this->post('csrf_token', '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->setFlash('danger', 'Phiên làm việc hết hạn. Vui lòng thử lại.');
            $this->redirect('/landlord-request');
        }

        // Already pending — block duplicate submissions
        if (($userRecord['landlord_status'] ?? 'none') === 'pending') {
            $this->setFlash('warning', 'Yêu cầu của bạn đang chờ admin duyệt.');
            $this->redirect('/landlord-request');
        }

        // Terms agreement — required
        if (!$this->post('agree_terms')) {
            $this->setFlash('danger', 'Bạn phải đồng ý với Điều khoản sử dụng để tiếp tục.');
            $this->redirect('/landlord-request');
        }

        // Input validation
        $name  = trim($this->post('name', ''));
        $phone = trim($this->post('phone', ''));
        $desc  = trim($this->post('description', ''));

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Họ tên phải có ít nhất 2 ký tự.';
        }
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
            $errors[] = 'Số điện thoại không hợp lệ (9–11 chữ số).';
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode(' ', $errors));
            $this->redirect('/landlord-request');
        }

        // Create the request row
        $model = new LandlordRequestModel();
        $model->create([
            'user_id'     => $user['id'],
            'name'        => $name,
            'phone'       => $phone,
            'description' => $desc ?: null,
            'status'      => 'pending',
        ]);

        // Update user landlord_status to 'pending'
        $this->users->update($user['id'], ['landlord_status' => 'pending']);

        $this->logActivity($user['id'], 'landlord_request_submitted', 'user', $user['id']);

        $this->setFlash('success', 'Yêu cầu đăng ký làm chủ trọ đã được gửi thành công! Admin sẽ xem xét và phản hồi sớm nhất có thể.');
        $this->redirect('/landlord-request');
    }
}