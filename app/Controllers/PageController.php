<?php
namespace App\Controllers;

use App\Core\BaseController;

class PageController extends BaseController
{
    public function terms(): void
    {
        $this->view('pages/terms', [
            'pageTitle' => 'Điều khoản sử dụng',
        ]);
    }

    public function privacy(): void
    {
        $this->view('pages/privacy', [
            'pageTitle' => 'Chính sách bảo mật',
        ]);
    }
}
