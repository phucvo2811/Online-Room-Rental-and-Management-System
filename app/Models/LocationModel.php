<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class LocationModel extends BaseModel
{
    protected string $table = 'districts';

    public function getAllDistricts(): array
    {
        return Database::fetchAll("SELECT * FROM districts ORDER BY name");
    }

    public function getWardsByDistrict(int $districtId): array
    {
        return Database::fetchAll(
            "SELECT * FROM wards WHERE district_id=? ORDER BY name", [$districtId]
        );
    }

    public function getStreetsByWard(int $wardId): array
    {
        return Database::fetchAll(
            "SELECT * FROM streets WHERE ward_id=? ORDER BY name", [$wardId]
        );
    }

    public function searchStreets(string $keyword): array
    {
        $kw = "%$keyword%";
        return Database::fetchAll(
            "SELECT s.*, w.name AS ward_name, d.name AS district_name
             FROM streets s
             JOIN wards w ON s.ward_id=w.id
             JOIN districts d ON w.district_id=d.id
             WHERE s.name ILIKE ? OR w.name ILIKE ? OR d.name ILIKE ?
             LIMIT 10",
            [$kw, $kw, $kw]
        );
    }
}