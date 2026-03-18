<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;

class ReviewController extends BaseController
{
    public function store(): void
    {
        $roomId  = (int)$this->post('room_id', 0);
        $rating  = max(1, min(5, (int)$this->post('rating', 5)));
        $comment = trim($this->post('comment', ''));

        if ($roomId) {
            $existing = Database::fetch(
                "SELECT id FROM reviews WHERE room_id=? AND user_id=?",
                [$roomId, $_SESSION['user_id']]
            );
            if ($existing) {
                Database::execute("UPDATE reviews SET rating=?,comment=? WHERE id=?",
                    [$rating, $comment, $existing['id']]);
            } else {
                Database::insert("INSERT INTO reviews (room_id,user_id,rating,comment) VALUES (?,?,?,?)",
                    [$roomId, $_SESSION['user_id'], $rating, $comment]);
            }
            $this->setFlash('success', 'Đánh giá đã được ghi nhận!');
        }
        $this->redirect("/rooms/$roomId");
    }
}