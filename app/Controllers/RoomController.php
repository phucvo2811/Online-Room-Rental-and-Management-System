<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;
use App\Models\RoomModel;
use App\Models\RoomBlockModel;
use App\Models\RoomTypeModel;
use App\Models\PostModel;
use App\Models\LocationModel;
use App\Models\UserModel;

class RoomController extends BaseController
{
    private RoomModel      $rooms;
    private RoomBlockModel $blocks;
    private RoomTypeModel  $roomTypes;
    private LocationModel  $locations;
    private array          $blockAllowedRoomTypes = ['boarding_house', 'dormitory', 'homestay'];

    public function __construct()
    {
        parent::__construct();
        $this->rooms     = new RoomModel();
        $this->blocks    = new RoomBlockModel();
        $this->roomTypes = new RoomTypeModel();
        $this->locations = new LocationModel();
    }

    public function index(): void
    {
        // Hiển thị danh sách bất động sản (block-level), không hiển thị từng phòng đơn lẻ.
        $filters = [
            'type'            => $this->get('type', $this->get('room_type', null)),
            'status'          => $this->get('status', ''),
            'location'        => trim($this->get('location', '')),
            'room_count_min'  => $this->get('room_count_min', ''),
            'room_count_max'  => $this->get('room_count_max', ''),
            'sort'            => $this->get('sort', ''),
            'price_min'       => $this->get('price_min', ''),
            'price_max'       => $this->get('price_max', ''),
        ];

        // Quick price range selection from compact value `price` parameter
        $priceRange = $this->get('price', '');
        if ($priceRange && strpos($priceRange, ',') !== false) {
            [$min, $max] = explode(',', $priceRange, 2);
            $filters['price_min'] = (int)trim($min);
            $filters['price_max'] = (int)trim($max);
        }

        // preserve backward compatibility with old filters
        $filters['district_id'] = $this->get('district_id', '');
        $filters['ward_id']     = $this->get('ward_id', '');
        $filters['has_ac']      = $this->get('has_ac', '');
        $filters['has_wifi']    = $this->get('has_wifi', '');

        $effectiveFilters = array_filter($filters, function ($value) {
            return $value !== '' && $value !== null;
        });

        $this->view('room/properties', [
            'blocks'       => $this->blocks->getFiltered($effectiveFilters),
            'filters'      => $filters,
            'pageTitle'    => 'Danh sách Nhà trọ / KTX',
        ]);
    }

    public function show(string $id): void
    {
        $room = $this->rooms->getDetail((int)$id);
        // Allow room owner and admin to view their own pending/rejected rooms
        $isOwner = $this->isLoggedIn() && isset($_SESSION['user_id']) && (int)($room['user_id'] ?? 0) === (int)$_SESSION['user_id'];
        $isAdmin = $this->isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$room || ($room['status'] !== 'approved' && !$isOwner && !$isAdmin)) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }
        $this->rooms->incrementView((int)$id);
        $isFav = false;
        if ($this->isLoggedIn()) {
            $isFav = (new UserModel())->isFavorite($_SESSION['user_id'], (int)$id);
        }
        $related = $this->rooms->getFiltered(['district_id' => $room['district_id'], 'room_type' => $room['room_type']]);
        $related = array_slice(array_values(array_filter($related, fn($r) => $r['id'] != $id)), 0, 4);

        $images = $this->rooms->getImages((int)$id);
        if (empty($images)) {
            $blockId = $room['block_id'] ?? $room['property_id'] ?? null;
            if ($blockId) {
                $images = $this->blocks->getImages((int)$blockId);
            }
        }

        $this->view('room/show', [
            'room'       => $room,
            'images'     => $images,
            'reviews'    => $this->rooms->getReviews((int)$id),
            'related'    => $related,
            'isFavorite' => $isFav,
            'pageTitle'  => $room['title'],
        ]);
    }

    public function myRooms(): void
    {
        $this->redirect('/my-blocks');
    }

    public function assignRoomBlock(string $id): void
    {
        $room = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);

        if (!$this->canUseBlockForRoom($room['room_type'] ?? '')) {
            $this->setFlash('danger', 'Chỉ phòng trọ hoặc ký túc xá mới gắn được nhà trọ.');
            $this->redirect('/my-rooms');
        }

        $blockId = (int)$this->post('block_id', 0);
        if ($blockId > 0) {
            $block = $this->blocks->getBlockWithRooms($blockId, $_SESSION['user_id']);
            if ($block && ($block['type'] ?? '') === ($room['room_type'] ?? '')) {
                $this->rooms->assignBlock((int)$id, $blockId);
                $this->setFlash('success', 'Phòng đã được gắn vào nhà trọ thành công.');
                $this->redirect('/my-rooms');
            }
        }

        $this->setFlash('danger', 'Không chọn nhà trọ hợp lệ.');
        $this->redirect('/my-rooms');
    }

    public function myPosts(): void
    {
        $postModel = new PostModel();
        $this->view('room/my_posts', [
            'posts'     => $postModel->getAllByUser($_SESSION['user_id']),
            'pageTitle' => 'Bài đăng của tôi',
        ]);
    }

    public function createPostForm(): void
    {
        $propertyId = (int)$this->get('property_id', 0);

        if ($propertyId <= 0) {
            // Show ONLY APPROVED properties for posting
            $approvedProperties = $this->blocks->getApprovedByUser($_SESSION['user_id']);
            
            if (empty($approvedProperties)) {
                $this->setFlash('warning', 'Bạn chưa có bất động sản nào được phê duyệt. Vui lòng chờ quản trị viên phê duyệt hoặc tạo bất động sản mới.');
                $this->redirect('/my-blocks');
                return;
            }

            $this->view('room/post_select_source', [
                'properties' => $approvedProperties,
                'pageTitle'  => 'Chọn BĐS để đăng tin',
            ]);
            return;
        }

        $property = $this->blocks->getById($propertyId);
        
        // Must be approved and belong to user
        if (!$property || ($property['user_id'] ?? null) !== $_SESSION['user_id'] || $property['status'] !== 'approved') {
            $this->setFlash('danger', 'Bất động sản không tồn tại, không phải của bạn, hoặc chưa được phê duyệt.');
            $this->redirect('/rooms/post');
            return;
        }

        $existing = (new PostModel())->getActiveListingByProperty($propertyId);
        $this->view('room/post_confirm_property', [
            'property' => $property,
            'existing' => $existing,
            'pageTitle'=> 'Xác nhận đăng tin',
            'csrf'     => $this->generateCsrf(),
        ]);
    }

    public function storePost(): void
    {
        $propertyId = (int)$this->post('property_id', 0);
        if ($propertyId <= 0) {
            $this->setFlash('danger', 'Vui lòng chọn một bất động sản trước khi đăng bài.');
            $this->redirect('/rooms/post');
            return;
        }

        $property = $this->blocks->getById($propertyId);
        
        // Check: belongs to user, is approved, and has not reached max active listings
        if (!$property || ($property['user_id'] ?? null) !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Bất động sản không tồn tại hoặc không phải của bạn.');
            $this->redirect('/rooms/post');
            return;
        }

        if ($property['status'] !== 'approved') {
            $this->setFlash('danger', 'Bất động sản này chưa được phê duyệt. Vui lòng chờ quản trị viên.');
            $this->redirect('/rooms/post');
            return;
        }

        $postModel = new PostModel();
        $existing = $postModel->getActiveListingByProperty($propertyId);
        if ($existing) {
            $this->setFlash('warning', 'Bất động sản này đã có bài đăng đang hoạt động.');
            $this->redirect('/rooms/post?property_id=' . $propertyId);
            return;
        }

        $title = trim($this->post('title', ''));
        if ($title === '') {
            $title = 'Bài đăng ' . $property['name'];
        }

        $data = [
            'user_id'     => $_SESSION['user_id'],
            'block_id'    => $propertyId,
            'title'       => mb_substr($title, 0, 255),
            'description' => trim($this->post('description', '')),
            'status'      => 'inactive',  // Status for listing approval
        ];

        $postModel->create($data);
        $this->setFlash('success', 'Bài đăng mới đã được tạo và gửi duyệt.');
        $this->redirect('/my-posts');
    }

    public function showPost(string $id): void
    {
        $post = (new PostModel())->getById((int)$id);
        if (!$post) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        if ($post['status'] !== 'active' && $post['user_id'] !== ($_SESSION['user_id'] ?? 0) && ($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        // Increment view count for public viewers (not owner, not admin)
        if ($post['status'] === 'active'
            && ($post['user_id'] !== ($_SESSION['user_id'] ?? 0))
            && (($_SESSION['user_role'] ?? '') !== 'admin')) {
            (new PostModel())->incrementView((int)$id);
        }

        if ($post['type'] === 'block') {
            $block = $this->blocks->findById((int)$post['block_id']);
            if (!$block) {
                http_response_code(404);
                echo $this->twig->render('errors/404.twig');
                return;
            }
            $rooms = $this->blocks->getRooms((int)$post['block_id']);
            $availableCount = count(array_filter($rooms, fn($r) => $r['occupancy_status'] === 'available'));
            $this->view('room/post_block', [
                'post'      => $post,
                'block'     => $block,
                'rooms'     => $rooms,
                'available' => $availableCount,
                'pageTitle' => $post['title'],
            ]);
            return;
        }

        if ($post['type'] === 'room') {
            $room = null;
            if (!empty($post['room_id'])) {
                $room = $this->rooms->getDetail((int)$post['room_id']);
            }
            $block = !empty($post['block_id']) ? $this->blocks->findById((int)$post['block_id']) : null;
            $this->view('room/post_room', [
                'post'      => $post,
                'room'      => $room,
                'block'     => $block,
                'pageTitle' => $post['title'],
            ]);
            return;
        }

        // Legacy behavior for unsupported types
        http_response_code(404);
        echo $this->twig->render('errors/404.twig');

        http_response_code(404);
        echo $this->twig->render('errors/404.twig');
    }

    public function editPostForm(string $id): void
    {
        $postModel = new PostModel();
        $post = $postModel->getById((int)$id);
        if (!$post || $post['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Không có quyền chỉnh sửa hoặc không tồn tại.');
            $this->redirect('/my-posts');
        }

        $this->view('room/post_form', [
            'post' => $post,
            'roomBlocks' => $this->blocks->getByUser($_SESSION['user_id']),
            'rooms' => $this->rooms->getByUser($_SESSION['user_id']),
            'mode' => 'edit',
            'pageTitle' => 'Chỉnh sửa bài đăng',
            'csrf' => $this->generateCsrf(),
        ]);
    }

    public function updatePost(string $id): void
    {
        $postModel = new PostModel();
        $post = $postModel->getById((int)$id);
        if (!$post || $post['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Không có quyền chỉnh sửa hoặc không tồn tại.');
            $this->redirect('/my-posts');
        }

        $postType = in_array($this->post('type', 'block'), ['block', 'room'], true) ? $this->post('type') : 'block';

        $data = [
            'type'        => $postType,
            'room_id'     => $postType === 'room' ? (int)$this->post('room_id', 0) : null,
            'block_id'    => $postType === 'block' ? (int)$this->post('block_id', 0) : ($this->post('block_id') ? (int)$this->post('block_id') : null),
            'title'       => trim($this->post('title','')),
            'description' => trim($this->post('description','')),
            'price_low'   => $this->post('price_low') !== '' ? (float)$this->post('price_low') : null,
            'price_high'  => $this->post('price_high') !== '' ? (float)$this->post('price_high') : null,
            'image_url'   => trim($this->post('image_url','')),
        ];

        $errors = [];
        if (mb_strlen($data['title']) < 5) $errors['title'] = 'Tiêu đề phải có ít nhất 5 ký tự.';

        if ($postType === 'block' && empty($data['block_id'])) {
            $errors['block_id'] = 'Chọn một nhà trọ / ký túc xá.';
        }

        if (!empty($errors)) {
            $this->view('room/post_form', [
                'post' => array_merge($post, $data),
                'roomBlocks' => $this->blocks->getByUser($_SESSION['user_id']),
                'rooms' => $this->rooms->getByUser($_SESSION['user_id']),
                'mode' => 'edit',
                'pageTitle' => 'Chỉnh sửa bài đăng',
                'errors' => $errors,
                'csrf' => $this->generateCsrf(),
            ]);
            return;
        }

        $postModel->update((int)$id, $data);
        $this->setFlash('success', 'Cập nhật bài đăng thành công.');
        $this->redirect('/my-posts');
    }

    public function deletePost(string $id): void
    {
        $postModel = new PostModel();
        $post = $postModel->getById((int)$id);
        if ($post && $post['user_id'] === $_SESSION['user_id']) {
            $postModel->delete((int)$id);
            $this->setFlash('success', 'Đã xóa bài đăng.');
        }
        $this->redirect('/my-posts');
    }

    public function togglePost(string $id): void
    {
        $postModel = new PostModel();
        $post = $postModel->getById((int)$id);
        if (!$post || $post['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Không có quyền thực hiện thao tác này.');
            $this->redirect('/my-posts');
        }
        $newStatus = $postModel->toggleStatus((int)$id, $_SESSION['user_id']);
        $label = $newStatus === 'active' ? 'Bài đăng đã được mở lại.' : 'Bài đăng đã được tạm đóng.';
        $this->setFlash('success', $label);
        $this->redirect('/my-posts');
    }

    public function myBlocks(): void
    {
        $postModel = new PostModel();
        $blocks = $this->blocks->getByUser($_SESSION['user_id']);
        foreach ($blocks as &$block) {
            $block['latest_post'] = $postModel->getLatestByBlock((int)$block['id']);
        }
        unset($block);
        $this->view('room/blocks', [
            'blocks'    => $blocks,
            'pageTitle' => 'Quản lý nhà trọ',
        ]);
    }

    public function createBlockForm(): void
    {
        $this->view('room/block_form', [
            'block'       => [],
            'blockImages' => [],
            'districts'   => $this->locations->getAllDistricts(),
            'pageTitle'   => 'Tạo bất động sản mới',
            'mode'        => 'create',
            'csrf'        => $this->generateCsrf(),
        ]);
    }

    public function createBlock(): void
    {
        $data = $this->only(['name', 'address', 'description', 'type', 'price', 'price_min', 'price_max', 'area',
                             'district_id', 'ward_id', 'street_id',
                             'has_wifi', 'has_ac', 'has_parking', 'allow_pet', 'allow_cooking',
                             'electric_price', 'water_price', 'internet_price',
                             'deposit_months', 'floor', 'max_people', 'num_bedrooms', 'num_bathrooms', 'contact_phone',
                             'latitude', 'longitude', 'map_address']);
        $errors = $this->validateBlockCreation($data);

        if (!empty($errors)) {
            $this->view('room/block_form', [
                'block'       => $data,
                'blockImages' => [],
                'errors'      => $errors,
                'pageTitle'   => 'Tạo bất động sản mới',
                'mode'        => 'create',
                'csrf'        => $this->generateCsrf(),
            ]);
            return;
        }

        $blockData = [
            'user_id'        => $_SESSION['user_id'],
            'name'           => trim($data['name']),
            'address'        => trim($data['address']),
            'description'    => trim($data['description'] ?? ''),
            'type'           => $data['type'],
            'status'         => 'pending',
            'area'           => empty($data['area']) ? null : (float)$data['area'],
            'district_id'    => empty($data['district_id']) ? null : (int)$data['district_id'],
            'ward_id'        => empty($data['ward_id']) ? null : (int)$data['ward_id'],
            'street_id'      => empty($data['street_id']) ? null : (int)$data['street_id'],
            'has_wifi'       => (int)!empty($data['has_wifi']),
            'has_ac'         => (int)!empty($data['has_ac']),
            'has_parking'    => (int)!empty($data['has_parking']),
            'allow_pet'      => (int)!empty($data['allow_pet']),
            'allow_cooking'  => (int)!empty($data['allow_cooking']),
            'electric_price' => empty($data['electric_price']) ? null : (float)$data['electric_price'],
            'water_price'    => empty($data['water_price']) ? null : (float)$data['water_price'],
            'internet_price' => empty($data['internet_price']) ? null : (float)$data['internet_price'],
            'deposit_months' => empty($data['deposit_months']) ? 1 : (int)$data['deposit_months'],
            'floor'          => empty($data['floor']) ? null : (int)$data['floor'],
            'max_people'     => empty($data['max_people']) ? null : (int)$data['max_people'],
            'num_bedrooms'   => empty($data['num_bedrooms']) ? null : (int)$data['num_bedrooms'],
            'num_bathrooms'  => empty($data['num_bathrooms']) ? null : (int)$data['num_bathrooms'],
            'contact_phone'  => trim($data['contact_phone'] ?? ''),
            'latitude'       => $data['latitude'] !== '' && is_numeric($data['latitude']) ? (float)$data['latitude'] : null,
            'longitude'      => $data['longitude'] !== '' && is_numeric($data['longitude']) ? (float)$data['longitude'] : null,
            'map_address'    => trim($data['map_address'] ?? ''),
        ];

        if ($this->blocks->isComposite($data['type'])) {
            $blockData['price']     = null;
            $blockData['price_min'] = empty($data['price_min']) ? null : (float)$data['price_min'];
            $blockData['price_max'] = empty($data['price_max']) ? null : (float)$data['price_max'];
        } else {
            $blockData['price']     = empty($data['price']) ? null : (float)$data['price'];
            $blockData['price_min'] = null;
            $blockData['price_max'] = null;
        }

        $blockId = $this->blocks->create($blockData);

        // Handle uploaded images (up to 10)
        $this->saveBlockImages((int)$blockId);

        $this->setFlash('success', 'Bất động sản đã được tạo và đang chờ duyệt từ quản trị viên.');
        $this->redirect('/my-blocks');
    }

    public function editBlockForm(string $id): void
    {
        $block = $this->blocks->findById((int)$id);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Nhà trọ không tồn tại hoặc bạn không có quyền.');
            $this->redirect('/my-blocks');
        }

        $wards = [];
        $streets = [];
        if (!empty($block['district_id'])) {
            $wards = $this->locations->getWardsByDistrict((int)$block['district_id']);
        }
        if (!empty($block['ward_id'])) {
            $streets = $this->locations->getStreetsByWard((int)$block['ward_id']);
        }

        $this->view('room/block_form', [
            'block'       => $block,
            'blockImages' => $this->blocks->getImages((int)$id),
            'districts'   => $this->locations->getAllDistricts(),
            'wards'       => $wards,
            'streets'     => $streets,
            'pageTitle'   => 'Chỉnh sửa bất động sản',
            'mode'        => 'edit',
            'csrf'        => $this->generateCsrf(),
        ]);
    }

    public function updateBlock(string $id): void
    {
        $block = $this->blocks->findById((int)$id);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Nhà trọ không tồn tại hoặc bạn không có quyền.');
            $this->redirect('/my-blocks');
        }

        $data = $this->only(['name', 'address', 'description', 'type', 'price', 'price_min', 'price_max', 'area',
                             'district_id', 'ward_id', 'street_id',
                             'has_wifi', 'has_ac', 'has_parking', 'allow_pet', 'allow_cooking',
                             'electric_price', 'water_price', 'internet_price',
                             'deposit_months', 'floor', 'max_people', 'num_bedrooms', 'num_bathrooms', 'contact_phone',
                             'latitude', 'longitude', 'map_address']);
        $errors = $this->validateBlock($data);

        if (!empty($errors)) {
            $this->view('room/block_form', [
                'block'     => array_merge($block, $data),
                'errors'    => $errors,
                'pageTitle' => 'Chỉnh sửa nhà trọ',
                'mode'      => 'edit',
                'csrf'      => $this->generateCsrf(),
            ]);
            return;
        }

        // Convert empty price to NULL for numeric field
        $data['price']         = empty($data['price']) ? null : (float)$data['price'];
        $data['price_min']     = empty($data['price_min']) ? null : (float)$data['price_min'];
        $data['price_max']     = empty($data['price_max']) ? null : (float)$data['price_max'];
        $data['area']          = empty($data['area']) ? null : (float)$data['area'];
        $data['district_id']   = empty($data['district_id']) ? null : (int)$data['district_id'];
        $data['ward_id']       = empty($data['ward_id']) ? null : (int)$data['ward_id'];
        $data['street_id']     = empty($data['street_id']) ? null : (int)$data['street_id'];
        $data['has_wifi']      = (int)!empty($data['has_wifi']);
        $data['has_ac']        = (int)!empty($data['has_ac']);
        $data['has_parking']   = (int)!empty($data['has_parking']);
        $data['allow_pet']     = (int)!empty($data['allow_pet']);
        $data['allow_cooking'] = (int)!empty($data['allow_cooking']);
        $data['electric_price']= empty($data['electric_price']) ? null : (float)$data['electric_price'];
        $data['water_price']   = empty($data['water_price']) ? null : (float)$data['water_price'];
        $data['internet_price']= empty($data['internet_price']) ? null : (float)$data['internet_price'];
        $data['deposit_months']= empty($data['deposit_months']) ? 1 : (int)$data['deposit_months'];
        $data['floor']         = empty($data['floor']) ? null : (int)$data['floor'];
        $data['max_people']    = empty($data['max_people']) ? null : (int)$data['max_people'];
        $data['num_bedrooms']  = empty($data['num_bedrooms']) ? null : (int)$data['num_bedrooms'];
        $data['num_bathrooms'] = empty($data['num_bathrooms']) ? null : (int)$data['num_bathrooms'];
        $data['contact_phone'] = trim($data['contact_phone'] ?? '');
        $data['latitude']      = $data['latitude'] !== '' && is_numeric($data['latitude']) ? (float)$data['latitude'] : null;
        $data['longitude']     = $data['longitude'] !== '' && is_numeric($data['longitude']) ? (float)$data['longitude'] : null;
        $data['map_address']   = trim($data['map_address'] ?? '');
        $this->blocks->update((int)$id, $data);

        $postModel = new PostModel();
        $postModel->updateByBlock((int)$id, [
            'title'      => 'Cho thuê ' . ($data['name'] ?? $block['name']),
            'price_low'  => $data['price'] ?? $block['price'],
            'price_high' => $data['price'] ?? $block['price'],
            'description' => $data['description'] ?? $block['description'],
        ]);

        // Handle new images if uploaded
        $this->saveBlockImages((int)$id, true);

        // Sync shared fields to all rooms in this block
        $this->syncRoomsFromBlock((int)$id, $data);

        $this->setFlash('success', 'Cập nhật bất động sản thành công. Thông tin đã đồng bộ xuống tất cả phòng.');
        $this->redirect('/my-blocks');
    }

    /**
     * Sync block-level shared fields to all rooms in the block.
     * Only pushes fields that are common to both rooms and the block (address,
     * amenities, utilities, contact phone, district/ward/street).
     */
    private function boolVal(mixed $v): int
    {
        return ($v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true' || $v === 'yes' || $v === 'on') ? 1 : 0;
    }

    private function syncRoomsFromBlock(int $blockId, array $blockData): void
    {
        $sync = [
            'address'        => $blockData['address']        ?? null,
            'district_id'    => $blockData['district_id']    ?? null,
            'ward_id'        => $blockData['ward_id']        ?? null,
            'street_id'      => $blockData['street_id']      ?? null,
            'has_wifi'       => (int)!empty($blockData['has_wifi']),
            'has_ac'         => (int)!empty($blockData['has_ac']),
            'has_parking'    => (int)!empty($blockData['has_parking']),
            'allow_pet'      => (int)!empty($blockData['allow_pet']),
            'allow_cooking'  => (int)!empty($blockData['allow_cooking']),
            'electric_price' => $blockData['electric_price'] ?? 0,
            'water_price'    => $blockData['water_price']    ?? 0,
            'internet_price' => $blockData['internet_price'] ?? 0,
            'deposit_months' => $blockData['deposit_months'] ?? 1,
            'contact_phone'  => $blockData['contact_phone']  ?? '',
        ];
        $sets   = implode(', ', array_map(fn($k) => "$k=?", array_keys($sync)));
        $params = [...array_values($sync), $blockId, $blockId];
        Database::execute(
            "UPDATE rooms SET $sets WHERE property_id=? OR block_id=?",
            $params
        );
    }

    /**
     * Save uploaded images for a block (up to 10).
     * @param int  $blockId
     * @param bool $appendMode  If false, replaces all existing images.
     */
    private function saveBlockImages(int $blockId, bool $appendMode = false): void
    {
        $files = $_FILES['images'] ?? null;
        if (empty($files['name'][0])) {
            return; // No files uploaded
        }

        $uploadDir = __DIR__ . '/../../public/uploads/blocks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowed    = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxBytes   = 5 * 1024 * 1024; // 5 MB per image
        $maxImages  = 10;
        $saved      = 0;

        if (!$appendMode) {
            // Replace: delete old images first (files stay on disk for now)
            $this->blocks->deleteImages($blockId);
        }

        $count = min(count($files['name']), $maxImages);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > $maxBytes) continue;
            $mime = mime_content_type($files['tmp_name'][$i]);
            if (!in_array($mime, $allowed, true)) continue;

            $ext      = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                default      => 'jpg',
            };
            $filename = 'block_' . $blockId . '_' . uniqid() . '.' . $ext;
            $dest     = $uploadDir . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $isPrimary = ($saved === 0 && !$appendMode);
                $this->blocks->addImage($blockId, 'blocks/' . $filename, $isPrimary, $saved);
                $saved++;
            }
        }
    }

    public function deleteBlock(string $id): void
    {
        $block = $this->blocks->findById((int)$id);
        if ($block && $block['user_id'] === $_SESSION['user_id']) {
            $this->blocks->delete((int)$id);
            $this->setFlash('success', 'Đã xóa nhà trọ.');
        }
        $this->redirect('/my-blocks');
    }

    public function blockRooms(string $id): void
    {
        $block = $this->blocks->getBlockWithRooms((int)$id, $_SESSION['user_id']);
        if (!$block) {
            $this->setFlash('danger', 'Nhà trọ không tồn tại hoặc không có quyền truy cập.');
            $this->redirect('/my-blocks');
        }

        $rooms = $this->blocks->getRooms((int)$id);

        if (empty($rooms) && in_array($block['type'], ['boarding_house', 'dormitory', 'homestay'], true)) {
            $this->setFlash('warning', 'Nhà trọ/KTX/Homestay phải có ít nhất 1 phòng để xuất hiện trên trang tìm kiếm. Vui lòng thêm phòng ngay bây giờ.');
        }

        $this->view('room/block_rooms', [
            'block'     => $block,
            'rooms'     => $rooms,
            'types'     => $this->roomTypes->toOptionList(),
            'pageTitle' => 'Quản lý phòng trong ' . $block['name'],
        ]);
    }

    public function listProperties(): void
    {
        $filters = [
            'location'       => $this->get('location', null),
            'price_min'      => $this->get('price_min', null),
            'price_max'      => $this->get('price_max', null),
            'type'           => $this->get('type', null),
            'status'         => $this->get('status', null),
            'room_count_min' => $this->get('room_count_min', null),
            'room_count_max' => $this->get('room_count_max', null),
            'sort'           => $this->get('sort', null),
            'district_id'    => $this->get('district_id', null),
            'ward_id'        => $this->get('ward_id', null),
            'has_ac'         => $this->get('has_ac', null),
            'has_wifi'       => $this->get('has_wifi', null),
            'landlord_id'    => $this->get('landlord_id', null),
        ];

        // Look up landlord info when filtering by landlord
        $activeLandlord = null;
        if (!empty($filters['landlord_id'])) {
            $activeLandlord = Database::fetch(
                "SELECT id, name, avatar FROM users WHERE id = ?",
                [(int)$filters['landlord_id']]
            );
        }

        $wards = [];
        if (!empty($filters['district_id'])) {
            $wards = $this->locations->getWardsByDistrict((int)$filters['district_id']);
        }

        $this->view('room/properties', [
            'blocks'         => $this->blocks->getFiltered(array_filter($filters, fn($v) => $v !== null && $v !== '')),
            'selectedType'   => $filters['type'],
            'filters'        => $filters,
            'districts'      => $this->locations->getAllDistricts(),
            'wards'          => $wards,
            'activeLandlord' => $activeLandlord,
            'pageTitle'      => 'Danh sách Bất động sản'
        ]);
    }

    public function showProperty(string $id): void
    {
        $property = $this->blocks->getById((int)$id);
        if (!$property) {
            http_response_code(404);
            echo $this->twig->render('errors/404.twig');
            return;
        }

        $rooms = $this->blocks->getRooms((int)$id);

        if (empty($rooms) && in_array($property['type'], ['mini_house','full_house','homestay'], true)) {
            // fallback single-item listing for mini/full house/homestay
            $rooms = [[
                'id'              => null,
                'room_number'     => 1,
                'name'            => $property['name'],
                'title'           => $property['name'],
                'price'           => $property['price'],
                'area'            => null,
                'occupancy_status'=> 'available',
                'room_type_name'  => ucfirst(str_replace('_', ' ', $property['type']))
            ]];
        }

        $selectedRoom = null;
        foreach ($rooms as $room) {
            if (($room['occupancy_status'] ?? 'available') === 'available') {
                $selectedRoom = $room;
                break;
            }
        }

        if (!$selectedRoom && !empty($rooms)) {
            $selectedRoom = $rooms[0];
        }

        // Block-level images
        $blockImages = $this->blocks->getImages((int)$id);

        $selectedRoomImages = [];
        if (!empty($selectedRoom['id'])) {
            $selectedRoomImages = (new RoomModel())->getImages((int)$selectedRoom['id']);
        }
        // Fall back to block images when the room has none
        if (empty($selectedRoomImages)) {
            $selectedRoomImages = $blockImages;
        }

        // Reviews for all rooms of this property
        $blockReviews = $this->blocks->getBlockReviews((int)$id);
        $reviewCount  = count($blockReviews);
        $reviewAvg    = $reviewCount
            ? round(array_sum(array_column($blockReviews, 'rating')) / $reviewCount, 1)
            : 0;
        $ratingSummary = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($blockReviews as $rv) {
            $ratingSummary[(int)$rv['rating']]++;
        }

        // Similar properties (same type, exclude self, limit 4)
        $similar = array_filter(
            array_slice($this->blocks->getFiltered(['type' => $property['type']]), 0, 5),
            fn($b) => (int)$b['id'] !== (int)$id
        );
        $similar = array_values(array_slice($similar, 0, 4));

        $this->view('room/property_detail', [
            'block'             => $property,
            'rooms'             => $rooms,
            'selectedRoom'      => $selectedRoom,
            'selectedRoomImages'=> $selectedRoomImages,
            'blockImages'       => $blockImages,
            'blockReviews'      => $blockReviews,
            'reviewCount'       => $reviewCount,
            'reviewAvg'         => $reviewAvg,
            'ratingSummary'     => $ratingSummary,
            'similar'           => $similar,
            'pageTitle'         => $property['name'],
        ]);
    }

    public function getRoomByNumber(string $propertyId, string $roomNumber): void
    {
        header('Content-Type: application/json');

        $property = $this->blocks->getById((int)$propertyId);
        if (!$property) {
            http_response_code(404);
            echo json_encode(['error' => 'Property not found']);
            return;
        }

        if (in_array($property['type'], ['mini_house','full_house'], true)) {
            if ((int)$roomNumber !== 1) {
                http_response_code(404);
                echo json_encode(['error' => 'Room not found']);
                return;
            }

            echo json_encode([
                'id'      => null,
                'name'    => $property['name'],
                'price'   => $property['price'],
                'status'  => 'available',
                'images'  => []
            ]);
            return;
        }

        $room = $this->blocks->getRoomByNumber((int)$propertyId, (int)$roomNumber);
        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Room not found']);
            return;
        }

        $images = (new RoomModel())->getImages($room['id']);
        // Fall back to block images when room has none
        if (empty($images)) {
            $images = $this->blocks->getImages((int)$propertyId);
        }
        echo json_encode(['room' => $room, 'images' => $images]);
    }

    public function apiRoom(string $id): void
    {
        header('Content-Type: application/json');

        $room = $this->rooms->getDetail((int)$id);
        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Room not found']);
            return;
        }

        $images = $this->rooms->getImages((int)$id);
        if (empty($images)) {
            $blockId = $room['block_id'] ?? $room['property_id'] ?? null;
            if ($blockId) {
                $images = $this->blocks->getImages((int)$blockId);
            }
        }
        $status = $room['occupancy_status'] ?? ($room['is_available'] ? 'available' : 'rented');

        echo json_encode([
            'id'     => $room['id'],
            'price'  => (float)$room['price'],
            'status' => $status,
            'images' => $images,
        ]);
    }

    public function createBlockRoomForm(string $blockId): void
    {
        $block = $this->blocks->findById((int)$blockId);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Vui lòng chọn một nhà (property) để thêm phòng.');
            $this->redirect('/my-blocks');
        }

        $nextRoomNumber = $this->rooms->getNextAvailableRoomNumber((int)$blockId);

        $this->view('room/room_form', [
            'room'            => [],
            'block'           => $block,
            'types'           => $this->roomTypes->getAll(),
            'mode'            => 'create_block_room',
            'pageTitle'       => 'Thêm phòng mới',
            'csrf'            => $this->generateCsrf(),
            'nextRoomNumber'  => $nextRoomNumber,
            'existingRooms'   => $this->rooms->getByProperty((int)$blockId, $_SESSION['user_id']),
        ]);
    }

    public function createBlockRoom(string $blockId): void
    {
        $block = $this->blocks->findById((int)$blockId);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Vui lòng chọn một nhà (property) để thêm phòng.');
            $this->redirect('/my-blocks');
        }

        $data = $this->only(['name','title','room_number','price','area','room_type_id','occupancy_status','description','address',
                             'contact_phone','electric_price','water_price','internet_price',
                             'has_wifi','has_ac','has_parking','has_kitchen','has_fridge','has_washing_machine','has_private_bath','allow_pet','allow_cooking']);
        $data['has_wifi']            = !empty($data['has_wifi'])            ? 1 : 0;
        $data['has_ac']              = !empty($data['has_ac'])              ? 1 : 0;
        $data['has_parking']         = !empty($data['has_parking'])         ? 1 : 0;
        $data['has_kitchen']         = !empty($data['has_kitchen'])         ? 1 : 0;
        $data['has_fridge']          = !empty($data['has_fridge'])          ? 1 : 0;
        $data['has_washing_machine'] = !empty($data['has_washing_machine']) ? 1 : 0;
        $data['has_private_bath']    = !empty($data['has_private_bath'])    ? 1 : 0;
        $data['allow_pet']           = !empty($data['allow_pet'])           ? 1 : 0;
        $data['allow_cooking']       = !empty($data['allow_cooking'])       ? 1 : 0;
        $data['status'] = 'approved';
        $data['is_available'] = 1;
        $data['user_id'] = $_SESSION['user_id'];
        $data['block_id'] = (int)$blockId;
        $data['property_id'] = (int)$blockId;
        $data['room_number'] = (int)$this->post('room_number', 0) ?: null;
        $data['block_type'] = $block['type'];

        // Inherit block-level fields as defaults where room fields are empty
        if (empty($data['address']))        $data['address']        = $block['address']        ?? '';
        if (empty($data['district_id']))    $data['district_id']    = $block['district_id']    ?? null;
        if (empty($data['ward_id']))        $data['ward_id']        = $block['ward_id']        ?? null;
        if (empty($data['street_id']))      $data['street_id']      = $block['street_id']      ?? null;
        if (empty($data['contact_phone']))  $data['contact_phone']  = $block['contact_phone']  ?? '';
        if (empty($data['electric_price'])) $data['electric_price'] = $block['electric_price'] ?? 0;
        if (empty($data['water_price']))    $data['water_price']    = $block['water_price']    ?? 0;
        if (empty($data['internet_price'])) $data['internet_price'] = $block['internet_price'] ?? 0;
        if (empty($data['deposit_months'])) $data['deposit_months'] = $block['deposit_months'] ?? 1;

        if (!empty($data['room_type_id'])) {
            $type = $this->roomTypes->getById((int)$data['room_type_id']);
            if ($type) {
                if (empty($data['price'])) {
                    $data['price'] = $type['default_price'];
                }
                if (empty($data['area'])) {
                    $data['area'] = $type['default_area'];
                }
            }
        }

        $errors = $this->validateRoomData($data);
        if (!empty($errors)) {
            $nextRoomNumber = $this->rooms->getNextAvailableRoomNumber((int)$blockId);
            $this->view('room/room_form', [
                'room'            => $data,
                'block'           => $block,
                'types'           => $this->roomTypes->getAll(),
                'errors'          => $errors,
                'mode'            => 'create_block_room',
                'pageTitle'       => 'Thêm phòng mới',
                'csrf'            => $this->generateCsrf(),
                'nextRoomNumber'  => $nextRoomNumber,
                'existingRooms'   => $this->rooms->getByProperty((int)$blockId, $_SESSION['user_id']),
            ]);
            return;
        }

        $roomId = $this->rooms->create($data);
        if ($roomId) {
            $this->setFlash('success', 'Thêm phòng thành công.');
        }

        $this->redirect('/blocks/' . $blockId . '/rooms');
    }

    public function bulkCreateRooms(string $blockId): void
    {
        $block = $this->blocks->findById((int)$blockId);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Nhà trọ không tồn tại hoặc bạn không có quyền.');
            $this->redirect('/my-blocks');
        }

        $prefix   = trim($this->post('prefix', 'Phòng '));
        $start    = max(1, (int)$this->post('start', 1));
        $end      = max($start, (int)$this->post('end', $start));
        $typeId   = (int)$this->post('room_type_id', 0);
        $baseRent = (float)$this->post('price', 0);
        $area     = (float)$this->post('area', 0);

        $created = 0;
        for ($i = $start; $i <= $end; $i++) {
            $name = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
            $roomData = [
                'user_id'             => $_SESSION['user_id'],
                'block_id'            => (int)$blockId,
                'property_id'         => (int)$blockId,
                'room_number'         => $i,
                'title'               => $name,
                'price'               => $baseRent,
                'area'                => $area,
                'address'             => $block['address']        ?? '',
                'district_id'         => $block['district_id']    ?? null,
                'ward_id'             => $block['ward_id']        ?? null,
                'street_id'           => $block['street_id']      ?? null,
                'contact_phone'       => $block['contact_phone']  ?? '',
                'electric_price'      => $block['electric_price'] ?? 0,
                'water_price'         => $block['water_price']    ?? 0,
                'internet_price'      => $block['internet_price'] ?? 0,
                'deposit_months'      => $block['deposit_months'] ?? 1,
                'has_wifi'            => $this->boolVal($block['has_wifi']     ?? null),
                'has_ac'              => $this->boolVal($block['has_ac']       ?? null),
                'has_parking'         => $this->boolVal($block['has_parking']  ?? null),
                'allow_pet'           => $this->boolVal($block['allow_pet']    ?? null),
                'allow_cooking'       => $this->boolVal($block['allow_cooking']?? null),
                'status'              => 'approved',
                'is_available'        => 1,
                'room_type_id'        => $typeId > 0 ? $typeId : null,
                'occupancy_status'    => 'available',
            ];
            if ($typeId) {
                $type = $this->roomTypes->getById($typeId);
                if ($type) {
                    if (empty($roomData['price'])) $roomData['price'] = $type['default_price'];
                    if (empty($roomData['area']))  $roomData['area']  = $type['default_area'];
                }
            }
            if (empty($this->validateRoomData($roomData))) {
                $this->rooms->create($roomData);
                $created++;
            }
        }

        $this->setFlash('success', "Đã tạo {$created} phòng mới.");
        $this->redirect('/blocks/' . $blockId . '/rooms');
    }

    public function setRoomOccupancy(string $blockId, string $roomId): void
    {
        $block = $this->blocks->findById((int)$blockId);
        if (!$block || $block['user_id'] !== $_SESSION['user_id']) {
            $this->setFlash('danger', 'Nhà trọ không tồn tại hoặc bạn không có quyền.');
            $this->redirect('/my-blocks');
        }

        $room = $this->rooms->findByIdInProperty((int)$roomId, (int)$blockId);
        if (!$room) {
            $this->setFlash('danger', 'Phòng không tồn tại hoặc không thuộc dãy này.');
            $this->redirect('/blocks/' . $blockId . '/rooms');
        }

        $status = $this->post('occupancy_status', 'available');
        if (!in_array($status, ['available','rented','maintenance'], true)) {
            $status = 'available';
        }

        $this->rooms->setOccupancyStatus((int)$roomId, $status);
        $this->setFlash('success', 'Cập nhật trạng thái phòng thành công.');
        $this->redirect('/blocks/' . $blockId . '/rooms');
    }

    private function validateBlockCreation(array $data): array
    {
        $errors = [];
        
        // Required fields
        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Tên bất động sản là bắt buộc.';
        }
        if (empty(trim($data['address'] ?? ''))) {
            $errors['address'] = 'Địa chỉ là bắt buộc.';
        }
        if (empty(trim($data['contact_phone'] ?? ''))) {
            $errors['contact_phone'] = 'Số điện thoại liên hệ là bắt buộc.';
        }
        if (empty($data['latitude']) || !is_numeric($data['latitude']) ||
            empty($data['longitude']) || !is_numeric($data['longitude'])) {
            $errors['location'] = 'Vui lòng chọn vị trí trên bản đồ.';
        }
        
        // Validate property type
        if (empty($data['type']) || !$this->validateBlockHouseType($data['type'])) {
            $errors['type'] = 'Chọn loại bất động sản hợp lệ.';
        } else {
            $type = $data['type'];
            
            // GROUP 1: Composite properties - require price range
            if ($this->blocks->isComposite($type)) {
                if (empty($data['price_min']) || !is_numeric($data['price_min']) || $data['price_min'] <= 0) {
                    $errors['price_min'] = 'Giá tối thiểu là bắt buộc và phải lớn hơn 0.';
                }
                if (empty($data['price_max']) || !is_numeric($data['price_max']) || $data['price_max'] <= 0) {
                    $errors['price_max'] = 'Giá tối đa là bắt buộc và phải lớn hơn 0.';
                }
                if (!empty($data['price_min']) && !empty($data['price_max']) && 
                    is_numeric($data['price_min']) && is_numeric($data['price_max']) &&
                    (float)$data['price_min'] > (float)$data['price_max']) {
                    $errors['price_min'] = 'Giá tối thiểu không được lớn hơn giá tối đa.';
                }
            }
            // GROUP 2: Single properties - require single price and area
            else {
                if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
                    $errors['price'] = 'Giá là bắt buộc và phải lớn hơn 0.';
                }
                if (empty($data['area']) || !is_numeric($data['area']) || $data['area'] <= 0) {
                    $errors['area'] = 'Diện tích là bắt buộc và phải lớn hơn 0.';
                }
            }
        }
        
        return $errors;
    }

    private function validateBlockHouseType(string $houseType): bool
    {
        return in_array($houseType, ['boarding_house', 'dormitory', 'mini_house', 'full_house', 'homestay'], true);
    }

    /**
     * Alias used by updateBlock() – delegates to validateBlockCreation().
     */
    private function validateBlock(array $data): array
    {
        return $this->validateBlockCreation($data);
    }

    private function validateRoomData(array $data): array
    {
        $errors = [];
        
        // For boarding_house/dormitory, room name can be simple (room_number only)
        // For other types, name is required as before
        $blockType = $data['block_type'] ?? '';
        if (empty($data['property_id'])) {
            $errors['property_id'] = 'Phòng phải thuộc một nhà (property). Vui lòng chọn nhà trước.';
        }

        if (!in_array($blockType, ['boarding_house', 'dormitory'], true)) {
            if (empty(trim($data['name'] ?? $data['title'] ?? ''))) {
                $errors['name'] = 'Tên/Tiêu đề phòng là bắt buộc.';
            }
        }
        
        if (empty($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = 'Giá thuê không hợp lệ.';
        }
        if (empty($data['area']) || !is_numeric($data['area'])) {
            $errors['area'] = 'Diện tích không hợp lệ.';
        }
        
        // For boarding_house/dormitory/homestay, property_id is required and room_number must be unique
        if (in_array($blockType, ['boarding_house', 'dormitory', 'homestay'], true)) {
            if (empty($data['property_id'])) {
                $errors['property_id'] = 'Vui lòng chọn một nhà (property).';
            }
            if (empty($data['room_number'])) {
                $errors['room_number'] = 'Số phòng là bắt buộc.';
            } elseif (!empty($data['property_id'])) {
                // Check for duplicate room_number in property
                $existing = $this->rooms->getRoomNumberInProperty(
                    (int)$data['property_id'],
                    (int)$data['room_number']
                );
                
                // If updating, allow same room_number if it's the same room
                if ($existing && ($data['room_id'] ?? null) != $existing['id']) {
                    $errors['room_number'] = 'Số phòng này đã tồn tại trong nhà trọ.';
                }
            }
        }
        
        return $errors;
    }

    private function authorizeBlock(array $block): void
    {
        if (!$block || $block['user_id'] !== ($_SESSION['user_id'] ?? null)) {
            $this->setFlash('danger', 'Bạn không có quyền thao tác với nhà trọ này.');
            $this->redirect('/my-blocks');
        }
    }

    private function canUseBlockForRoom(string $roomType): bool
    {
        // Allow room block association for most room types (phòng trọ, ký túc xá, homestay, boarding_house, dormitory).
        return in_array($roomType, ['boarding_house', 'dormitory', 'homestay', 'phong_tro', 'ky_tuc_xa'], true);
    }

    public function createForm(): void
    {
        $this->view('room/form', [
            'room' => [],
            'room_types' => [
                'boarding_house' => 'Nhà trọ',
                'dormitory' => 'Ký túc xá',
                'phong_tro' => 'Phòng trọ',
                'mini_house' => 'Căn hộ mini',
                'full_house' => 'Nhà nguyên căn',
                'homestay' => 'Homestay'
            ],
            'room_types_list' => $this->roomTypes->getAll(),
            'room_blocks' => $this->blocks->getByUser($_SESSION['user_id']),
            'districts' => $this->locations->getAllDistricts(),
            'wards' => [],
            'streets' => [],
            'pageTitle' => 'Đăng tin phòng trọ',
            'mode' => 'create',
            'csrf' => $this->generateCsrf(),
        ]);
    }

    public function create(): void
    {
        $blockId = (int)$this->post('block_id', 0);
        if ($blockId > 0) {
            $block = $this->blocks->findById($blockId);
            if (!$block || ($block['user_id'] ?? null) !== $_SESSION['user_id']) {
                $this->setFlash('danger', 'Nhà (property) không tồn tại hoặc bạn không có quyền.');
                $this->redirect('/my-blocks');
                return;
            }
        }

        $data   = $this->collectRoomData();
        $errors = $this->validateRoom($data);

        if (!empty($errors)) {
            $this->view('room/form', [
                'room'      => $data,
                'errors'    => $errors,
                'districts' => $this->locations->getAllDistricts(),
                'pageTitle' => 'Đăng tin',
                'mode'      => 'create',
                'csrf'      => $this->generateCsrf(),
            ]);
            return;
        }

        if (in_array($data['room_type'], ['mini_house', 'full_house'], true)) {
            $this->setFlash('danger', 'Mini house / full house phải được tạo dưới dạng bất động sản. Vui lòng dùng trang Nhà trọ.');
            $this->redirect('/blocks/create');
            return;
        }

        $data['user_id'] = $_SESSION['user_id'];
        if (!$this->canUseBlockForRoom($data['room_type'] ?? '')) {
            $data['block_id'] = null;
            $data['property_id'] = null;
        } else {
            $data['property_id'] = $data['block_id'];
        }
        $data['status']  = 'pending';
        $data['moderation_status'] = 'pending';
        $roomId = $this->rooms->create($data);

        // spam check
        if ($this->checkSpamRoom($data, $_SESSION['user_id'])) {
            $this->rooms->markSpam($roomId, true);
        }

        foreach ($this->uploadImages('images') as $i => $img) {
            // Use loose comparison in case index keys are strings.
            $this->rooms->addImage($roomId, $img, $i == 0, $i);
        }

        $this->logActivity($_SESSION['user_id'], 'create_room', 'room', $roomId, ['title' => $data['title']]);

        $admins = Database::fetchAll("SELECT id FROM users WHERE role='admin'");
        $uModel = new UserModel();
        foreach ($admins as $admin) {
            $uModel->addNotification($admin['id'], 'Tin mới chờ duyệt', "Tin: {$data['title']}", 'info');
        }

        $this->setFlash('success', 'Đăng tin thành công! Đang chờ admin duyệt.');
        $this->redirect('/my-rooms');
    }

    public function editForm(string $id): void
    {
        $room = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);
        $this->view('room/form', [
            'room'            => $room,
            'images'          => $this->rooms->getImages((int)$id),
            'districts'       => $this->locations->getAllDistricts(),
            'room_types'      => [
                'boarding_house' => 'Nhà trọ',
                'dormitory'      => 'Ký túc xá',
                'phong_tro'      => 'Phòng trọ',
                'mini_house'     => 'Căn hộ mini',
                'full_house'     => 'Nhà nguyên căn',
                'homestay'       => 'Homestay',
            ],
            'room_types_list' => $this->roomTypes->getAll(),
            'room_blocks'     => $this->blocks->getByUser($_SESSION['user_id']),
            'wards'           => $room['district_id'] ? $this->locations->getWardsByDistrict($room['district_id']) : [],
            'streets'         => $room['ward_id'] ? $this->locations->getStreetsByWard($room['ward_id']) : [],
            'pageTitle'       => 'Chỉnh sửa tin',
            'mode'            => 'edit',
            'csrf'            => $this->generateCsrf(),
        ]);
    }

    public function update(string $id): void
    {
        $room   = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);
        $data   = $this->collectRoomData();
        // Carry the original room_id and block_id so validateRoom can skip re-checking
        $data['room_id']         = (int)$id;
        $data['original_block_id'] = $room['block_id'] ?? null;
        if (empty($data['block_id']) && !empty($room['block_id'])) {
            $data['block_id'] = $room['block_id'];
        }
        if (empty($data['room_number']) && !empty($room['room_number'])) {
            $data['room_number'] = $room['room_number'];
        }
        $errors = $this->validateRoom($data);

        if (!empty($errors)) {
            $this->view('room/form', [
                'room'            => array_merge($room, $data),
                'errors'          => $errors,
                'images'          => $this->rooms->getImages((int)$id),
                'districts'       => $this->locations->getAllDistricts(),
                'room_types'      => [
                    'boarding_house' => 'Nhà trọ',
                    'dormitory'      => 'Ký túc xá',
                    'phong_tro'      => 'Phòng trọ',
                    'mini_house'     => 'Căn hộ mini',
                    'full_house'     => 'Nhà nguyên căn',
                    'homestay'       => 'Homestay',
                ],
                'room_types_list' => $this->roomTypes->getAll(),
                'room_blocks'     => $this->blocks->getByUser($_SESSION['user_id']),
                'wards'           => $room['district_id'] ? $this->locations->getWardsByDistrict($room['district_id']) : [],
                'streets'         => $room['ward_id'] ? $this->locations->getStreetsByWard($room['ward_id']) : [],
                'pageTitle'       => 'Chỉnh sửa',
                'mode'            => 'edit',
                'csrf'            => $this->generateCsrf(),
            ]);
            return;
        }

        if (!$this->canUseBlockForRoom($data['room_type'] ?? '')) {
            $data['block_id'] = null;
        }

        if ($_SESSION['user_role'] !== 'admin') {
            $data['status'] = 'pending';
            $data['moderation_status'] = 'pending';
        }

        $oldData = $room;
        // Remove non-DB fields before saving
        unset($data['room_id'], $data['original_block_id']);
        $this->rooms->update((int)$id, $data);
        $this->rooms->saveEditHistory((int)$id, $_SESSION['user_id'], $oldData, array_merge($oldData, $data), 'Cập nhật tin đăng');

        $deleteIds = (array)$this->post('delete_images', []);
        foreach ($deleteIds as $imgId) {
            $img = $this->rooms->getImage((int)$imgId);
            if ($img && $img['room_id'] == (int)$id) {
                $path = UPLOAD_PATH . $img['image_path'];
                if (file_exists($path)) unlink($path);
                $this->rooms->deleteImage((int)$imgId);
            }
        }

        if ($this->checkSpamRoom($data, $_SESSION['user_id'])) {
            $this->rooms->markSpam((int)$id, true);
        }

        $hasPrimary = (bool) Database::fetch(
            'SELECT 1 FROM room_images WHERE room_id=? AND is_primary=TRUE LIMIT 1',
            [(int)$id]
        );

        foreach ($this->uploadImages('images') as $i => $img) {
            $makePrimary = !$hasPrimary && $i === 0;
            $this->rooms->addImage((int)$id, $img, $makePrimary, 99 + $i);
            if ($makePrimary) {
                $hasPrimary = true;
            }
        }

        if (!$hasPrimary) {
            $firstImage = Database::fetch('SELECT id FROM room_images WHERE room_id=? ORDER BY sort_order ASC LIMIT 1', [(int)$id]);
            if ($firstImage) {
                $this->rooms->setPrimaryImage((int)$id, (int)$firstImage['id']);
            }
        }

        // Cập nhật thông tin bài đăng liên quan đến phòng (if exists)
        $postModel = new PostModel();
        $postModel->updateByRoom((int)$id, [
            'title'      => 'Cho thuê ' . ($data['title'] ?: ($room['title'] ?? '')), 
            'price_low'  => (float)$data['price'], 
            'price_high' => (float)$data['price'], 
            'description' => $data['description'] ?? ''
        ]);

        $this->setFlash('success', 'Cập nhật thành công!');
        // Redirect back to block rooms page if this room belongs to a block
        $blockId = $room['property_id'] ?? $room['block_id'] ?? null;
        if ($blockId) {
            $this->redirect('/blocks/' . (int)$blockId . '/rooms');
        } else {
            $this->redirect('/my-rooms');
        }
    }

    public function delete(string $id): void
    {
        $room = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);
        foreach ($this->rooms->getImages((int)$id) as $img) {
            $path = UPLOAD_PATH . $img['image_path'];
            if (file_exists($path)) unlink($path);
        }
        $this->rooms->delete((int)$id);
        $this->setFlash('success', 'Đã xóa tin đăng.');
        $this->redirect('/my-rooms');
    }

    public function toggleAvailability(string $id): void
    {
        $room = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);
        if (!$room) {
            $this->setFlash('danger', 'Tin không tồn tại.');
            $this->redirect('/my-rooms');
        }

        $newStatus = $room['is_available'] ? 'FALSE' : 'TRUE';
        $this->rooms->update((int)$id, ['is_available' => $newStatus]);
        $this->setFlash('success', $room['is_available'] ? 'Đã ẩn tin đăng.' : 'Đã bật hiển thị tin đăng.');
        $this->redirect('/my-rooms');
    }

    private function checkSpamRoom(array $roomData, int $userId): bool
    {
        $title = trim($roomData['title'] ?? '');

        if ($title !== '') {
            $duplicateCnt = Database::fetch(
                "SELECT COUNT(*) AS c FROM rooms WHERE title ILIKE ? AND created_at >= NOW() - INTERVAL '1 day'",
                ["%{$title}%"]
            );
            if ((int)($duplicateCnt['c'] ?? 0) >= 3) {
                return true;
            }
        }

        $recentPosts = Database::fetch(
            "SELECT COUNT(*) AS c FROM rooms WHERE user_id=? AND created_at >= NOW() - INTERVAL '30 minutes'",
            [$userId]
        );
        if ((int)($recentPosts['c'] ?? 0) >= 3) {
            return true;
        }

        return false;
    }

    private function authorizeRoom(?array $room): void
    {
        if (!$room || ($room['user_id'] != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin')) {
            $this->setFlash('danger', 'Bạn không có quyền thực hiện hành động này.');
            $this->redirect('/my-rooms');
        }
    }

    private function collectFilters(): array
    {
        return [
            'district_id' => (int)$this->get('district_id') ?: null,
            'ward_id'     => (int)$this->get('ward_id')     ?: null,
            'street_id'   => (int)$this->get('street_id')   ?: null,
            'block_id'    => (int)$this->get('block_id')    ?: null,
            'room_type'   => $this->get('room_type', ''),
            'price_min'   => (float)$this->get('price_min') ?: null,
            'price_max'   => (float)$this->get('price_max') ?: null,
            'has_wifi'    => $this->get('has_wifi'),
            'has_ac'      => $this->get('has_ac'),
            'has_parking' => $this->get('has_parking'),
            'allow_pet'   => $this->get('allow_pet'),
            'keyword'     => trim($this->get('keyword', '')),
            'sort'        => $this->get('sort', 'newest'),
        ];
    }

    private function collectRoomData(): array
    {
        $title = trim($this->post('title', ''));
        if ($title === '') {
            $title = trim($this->post('name', ''));
        }

        return [
            'title'               => $title,
            'description'         => trim($this->post('description', '')),
            'room_type'           => $this->post('room_type', 'boarding_house'),
            'room_number'         => (int)$this->post('room_number') ?: null,
            'room_type_id'        => (int)$this->post('room_type_id') ?: null,
            'block_id'            => (int)$this->post('block_id') ?: null,
            'price'               => (float)$this->post('price', 0),
            'area'                => (float)$this->post('area', 0),
            'address'             => trim($this->post('address', '')),
            'district_id'         => (int)$this->post('district_id') ?: null,
            'ward_id'             => (int)$this->post('ward_id')     ?: null,
            'street_id'           => (int)$this->post('street_id')   ?: null,
            'max_people'          => (int)$this->post('max_people', 1),
            'floor'               => (int)$this->post('floor', 1),
            'total_floors'        => (int)$this->post('total_floors', 1),
            'has_wifi'            => $this->post('has_wifi')            ? 'TRUE' : 'FALSE',
            'has_ac'              => $this->post('has_ac')              ? 'TRUE' : 'FALSE',
            'has_parking'         => $this->post('has_parking')         ? 'TRUE' : 'FALSE',
            'has_kitchen'         => $this->post('has_kitchen')         ? 'TRUE' : 'FALSE',
            'has_washing_machine' => $this->post('has_washing_machine') ? 'TRUE' : 'FALSE',
            'has_fridge'          => $this->post('has_fridge')          ? 'TRUE' : 'FALSE',
            'has_private_bath'    => $this->post('has_private_bath')    ? 'TRUE' : 'FALSE',
            'allow_pet'           => $this->post('allow_pet')           ? 'TRUE' : 'FALSE',
            'allow_cooking'       => $this->post('allow_cooking')       ? 'TRUE' : 'FALSE',
            'electric_price'      => (float)$this->post('electric_price', 0),
            'water_price'         => (float)$this->post('water_price', 0),
            'internet_price'      => (float)$this->post('internet_price', 0),
            'contact_name'        => trim($this->post('contact_name', '')),
            'contact_phone'       => trim($this->post('contact_phone', '')),
            'deposit_months'      => (int)$this->post('deposit_months', 1),
            'available_from'      => $this->post('available_from', date('Y-m-d')),
            'is_available'        => 'TRUE',
        ];
    }

    private function validateRoom(array $data): array
    {
        $errors = [];
        
        // Check if block type is boarding_house or dormitory
        $blockType = null;
        if (!empty($data['block_id'])) {
            $block = $this->blocks->findById((int)$data['block_id']);
            if ($block) {
                $blockType = $block['type'] ?? null;
            }
        }
        
        // For boarding_house/dormitory/homestay, title can be shorter/more flexible
        // For other types, title must be at least 10 characters
        if (!in_array($blockType, ['boarding_house', 'dormitory', 'homestay'], true) && empty($data['block_id'])) {
            if (mb_strlen($data['title']) < 10) {
                $errors['title'] = 'Tiêu đề phải có ít nhất 10 ký tự';
            }
        }

        // room_number only required for block rooms
        if (!empty($data['block_id'])) {
            if (empty($data['room_number']) || $data['room_number'] <= 0) {
                $errors['room_number'] = 'Số phòng/ký hiệu phòng không hợp lệ.';
            }
        }
        if ($data['price'] <= 0) {
            $errors['price'] = 'Giá không hợp lệ';
        }
        if ($data['area'] <= 0) {
            $errors['area'] = 'Diện tích không hợp lệ';
        }
        if (empty($data['address'])) {
            $errors['address'] = 'Vui lòng nhập địa chỉ';
        }
        if (empty($data['contact_phone'])) {
            $errors['contact_phone'] = 'Vui lòng nhập số điện thoại';
        }

        // When editing a room that was already assigned to a block at creation,
        // skip the block-type vs room-type cross-check (trust the original assignment)
        $isEditing = !empty($data['room_id']) && !empty($data['original_block_id'])
                     && (int)$data['original_block_id'] === (int)($data['block_id'] ?? 0);

        if (in_array($data['room_type'] ?? '', ['mini_house', 'full_house'], true)) {
            $errors['room_type'] = 'Mini house hoặc full house phải được tạo dưới dạng bất động sản, không qua form thêm phòng.';
        } else {
            if (!empty($data['block_id'])) {
                $block = $this->blocks->findById((int)$data['block_id']);
                if (!$block) {
                    $errors['block_id'] = 'Nhà trọ không tồn tại.';
                } elseif (($block['user_id'] ?? null) !== $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
                    $errors['block_id'] = 'Bạn không có quyền trên nhà trọ này.';
                } elseif (!$isEditing && !$this->canUseBlockForRoom($data['room_type'] ?? '')) {
                    $errors['room_type'] = 'Loại phòng này không hỗ trợ gắn nhà trọ.';
                } elseif (!$isEditing && ($block['type'] ?? '') !== ($data['room_type'] ?? '')) {
                    $errors['block_id'] = 'Nhà trọ và loại phòng không khớp.';
                } else {
                    // For boarding_house/dormitory, check for duplicate room_number in property
                    if (in_array($block['type'], ['boarding_house', 'dormitory', 'homestay', 'phong_tro', 'ky_tuc_xa'], true) && !empty($data['room_number'])) {
                        $existing = $this->rooms->getRoomNumberInProperty(
                            (int)$data['block_id'],
                            (int)$data['room_number']
                        );
                        // If updating, allow same room_number if it's the same room
                        if ($existing && ($data['room_id'] ?? null) != $existing['id']) {
                            $errors['room_number'] = 'Số phòng này đã tồn tại trong nhà trọ / ký túc xá.';
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
