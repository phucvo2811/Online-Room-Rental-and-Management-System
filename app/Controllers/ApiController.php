<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\LocationModel;

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
}