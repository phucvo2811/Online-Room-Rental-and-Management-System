<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;

class ContactController extends BaseController
{
    public function send(): void
    {
        $roomId = (int)$this->post('room_id', 0);
        $name   = trim($this->post('sender_name', ''));
        $msg    = trim($this->post('message', ''));

        if (empty($name) || empty($msg)) {
            $this->setFlash('danger', 'Vui lòng điền đầy đủ thông tin.');
            $this->redirect("/rooms/$roomId");
            return;
        }
        Database::insert(
            "INSERT INTO contacts (room_id,sender_name,sender_email,sender_phone,message) VALUES (?,?,?,?,?)",
            [$roomId, $name, trim($this->post('sender_email','')), trim($this->post('sender_phone','')), $msg]
        );
        $this->setFlash('success', 'Gửi liên hệ thành công!');
        $this->redirect("/rooms/$roomId");
    }
}