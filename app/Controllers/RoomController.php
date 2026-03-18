<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;
use App\Models\RoomModel;
use App\Models\LocationModel;
use App\Models\UserModel;

class RoomController extends BaseController
{
    private RoomModel     $rooms;
    private LocationModel $locations;

    public function __construct()
    {
        parent::__construct();
        $this->rooms     = new RoomModel();
        $this->locations = new LocationModel();
    }

    public function index(): void
    {
        $filters = $this->collectFilters();
        $page    = max(1, (int)$this->get('page', 1));
        $total   = $this->rooms->countFiltered($filters);

        $this->view('room/index', [
            'rooms'     => $this->rooms->getFiltered($filters, $page),
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int)ceil($total / ITEMS_PER_PAGE),
            'filters'   => $filters,
            'districts' => $this->locations->getAllDistricts(),
            'pageTitle' => 'Tìm phòng trọ',
        ]);
    }

    public function show(string $id): void
    {
        $room = $this->rooms->getDetail((int)$id);
        if (!$room || $room['status'] !== 'approved') {
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

        $this->view('room/show', [
            'room'       => $room,
            'images'     => $this->rooms->getImages((int)$id),
            'reviews'    => $this->rooms->getReviews((int)$id),
            'related'    => $related,
            'isFavorite' => $isFav,
            'pageTitle'  => $room['title'],
        ]);
    }

    public function myRooms(): void
    {
        $this->view('room/my_rooms', [
            'rooms'     => $this->rooms->getByUser($_SESSION['user_id']),
            'pageTitle' => 'Phòng của tôi',
        ]);
    }

    public function createForm(): void
    {
        $this->view('room/form', [
            'room'      => [],
            'districts' => $this->locations->getAllDistricts(),
            'pageTitle' => 'Đăng tin phòng trọ',
            'mode'      => 'create',
            'csrf'      => $this->generateCsrf(),
        ]);
    }

    public function create(): void
    {
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

        $data['user_id'] = $_SESSION['user_id'];
        $data['status']  = 'pending';
        $roomId = $this->rooms->create($data);

        foreach ($this->uploadImages('images') as $i => $img) {
            $this->rooms->addImage($roomId, $img, $i === 0, $i);
        }

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
            'room'      => $room,
            'images'    => $this->rooms->getImages((int)$id),
            'districts' => $this->locations->getAllDistricts(),
            'wards'     => $room['district_id'] ? $this->locations->getWardsByDistrict($room['district_id']) : [],
            'streets'   => $room['ward_id']     ? $this->locations->getStreetsByWard($room['ward_id'])       : [],
            'pageTitle' => 'Chỉnh sửa tin',
            'mode'      => 'edit',
            'csrf'      => $this->generateCsrf(),
        ]);
    }

    public function update(string $id): void
    {
        $room   = $this->rooms->findById((int)$id);
        $this->authorizeRoom($room);
        $data   = $this->collectRoomData();
        $errors = $this->validateRoom($data);

        if (!empty($errors)) {
            $this->view('room/form', [
                'room'      => array_merge($room, $data),
                'errors'    => $errors,
                'districts' => $this->locations->getAllDistricts(),
                'pageTitle' => 'Chỉnh sửa',
                'mode'      => 'edit',
                'csrf'      => $this->generateCsrf(),
            ]);
            return;
        }

        if ($_SESSION['user_role'] !== 'admin') $data['status'] = 'pending';
        $this->rooms->update((int)$id, $data);

        foreach ($this->uploadImages('images') as $i => $img) {
            $this->rooms->addImage((int)$id, $img, false, 99 + $i);
        }
        $this->setFlash('success', 'Cập nhật thành công!');
        $this->redirect('/my-rooms');
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
        return [
            'title'               => trim($this->post('title', '')),
            'description'         => trim($this->post('description', '')),
            'room_type'           => $this->post('room_type', 'phong_tro'),
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
        if (mb_strlen($data['title']) < 10) $errors['title']         = 'Tiêu đề phải có ít nhất 10 ký tự';
        if ($data['price'] <= 0)            $errors['price']         = 'Giá không hợp lệ';
        if ($data['area']  <= 0)            $errors['area']          = 'Diện tích không hợp lệ';
        if (empty($data['address']))        $errors['address']       = 'Vui lòng nhập địa chỉ';
        if (empty($data['contact_phone']))  $errors['contact_phone'] = 'Vui lòng nhập số điện thoại';
        return $errors;
    }
}