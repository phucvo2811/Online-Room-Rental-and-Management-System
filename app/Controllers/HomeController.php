<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\RoomModel;
use App\Models\LocationModel;

class HomeController extends BaseController
{
    public function index(): void
    {
        $roomModel = new RoomModel();
        $this->view('home/index', [
            'featured'  => $roomModel->getFeatured(8),
            'stats'     => $roomModel->getStats(),
            'districts' => (new LocationModel())->getAllDistricts(),
            'pageTitle' => 'Trang chủ',
        ]);
    }
}