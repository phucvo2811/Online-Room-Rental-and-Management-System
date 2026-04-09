<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Models\RoomBlockModel;
use App\Models\PostModel;

class PostController extends BaseController
{
    private RoomBlockModel $properties;
    private PostModel $listings;

    public function __construct()
    {
        parent::__construct();
        $this->properties = new RoomBlockModel();
        $this->listings   = new PostModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        // Only show APPROVED properties for posting
        $approvedProperties = $this->properties->getApprovedByUser($_SESSION['user_id']);

        if (empty($approvedProperties)) {
            $pendingCount = count($this->properties->getByStatus('pending'));
            if ($pendingCount > 0) {
                $this->setFlash('info', "Bạn có $pendingCount bất động sản đang chờ phê duyệt. Vui lòng chờ quản trị viên.");
            } else {
                $this->setFlash('info', 'Bạn chưa có bất động sản nào được phê duyệt để đăng tin.');
            }
        }

        $this->view('post/index', [
            'properties' => $approvedProperties,
            'pageTitle'  => 'Chọn BĐS đăng bài',
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $propertyId = (int)$this->get('property_id', 0);
        if ($propertyId <= 0) {
            $this->setFlash('danger', 'Vui lòng chọn một bất động sản trước khi tạo bài đăng.');
            $this->redirect('/post');
            return;
        }

        $property = $this->properties->getById($propertyId);
        if (!$property || ($property['user_id'] ?? null) !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Bất động sản không tồn tại hoặc không phải của bạn.');
            $this->redirect('/post');
            return;
        }

        // IMPORTANT: Property MUST be approved to create a listing
        if ($property['status'] !== 'approved') {
            $this->setFlash('danger', 'Bất động sản này chưa được phê duyệt. Chỉ có thể đăng tin từ bất động sản đã được phê duyệt.');
            $this->redirect('/post');
            return;
        }

        $existingListing = $this->listings->getActiveListingByProperty($propertyId);

        $this->view('post/create', [
            'property'       => $property,
            'existing'       => $existingListing,
            'pageTitle'      => 'Xác nhận đăng tin',
            'csrf'           => $this->generateCsrf(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        $propertyId = (int)$this->post('property_id', 0);
        if ($propertyId <= 0) {
            $this->setFlash('danger', 'Vui lòng chọn một bất động sản trước khi đăng.');
            $this->redirect('/post');
            return;
        }

        $property = $this->properties->getById($propertyId);
        if (!$property || ($property['user_id'] ?? null) !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Bất động sản không tồn tại hoặc không phải của bạn.');
            $this->redirect('/post');
            return;
        }

        // IMPORTANT: Property MUST be approved
        if ($property['status'] !== 'approved') {
            $this->setFlash('danger', 'Bất động sản này chưa được phê duyệt. Không thể đăng tin.');
            $this->redirect('/post');
            return;
        }

        $existingListing = $this->listings->getActiveListingByProperty($propertyId);
        if ($existingListing) {
            $this->setFlash('warning', 'Bất động sản này đã có bài đăng đang hoạt động.');
            $this->redirect('/post');
            return;
        }

        $title = trim($this->post('title', ''));
        if ($title === '') {
            $title = 'Bài đăng ' . $property['name'];
        }

        // Prepare data based on property type
        $priceInfo = $this->properties->getPriceDisplayInfo($property);
        
        $data = [
            'user_id'    => $_SESSION['user_id'],
            'type'       => 'block',
            'room_id'    => null,
            'block_id'   => $propertyId,
            'title'      => mb_substr($title, 0, 255),
            'description'=> trim($this->post('description', '')),
            'status'     => 'inactive',  // New listings await admin approval
        ];

        // Set price display fields based on property type
        if ($priceInfo['type'] === 'range') {
            $data['price_low']  = $priceInfo['min'];
            $data['price_high'] = $priceInfo['max'];
        } else {
            $data['price_low']  = $priceInfo['price'];
            $data['price_high'] = $priceInfo['price'];
        }

        $this->listings->create($data);

        $this->setFlash('success', 'Bài đăng đã được tạo và đang chờ duyệt.');
        $this->redirect('/my-posts');
    }
}
