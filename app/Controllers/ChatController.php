<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Models\ConversationModel;
use App\Models\MessageModel;

class ChatController extends BaseController
{
    private ConversationModel $convs;
    private MessageModel $messages;

    public function __construct()
    {
        parent::__construct();
        $this->convs    = new ConversationModel();
        $this->messages = new MessageModel();
    }

    public function index(): void
    {
        $userId        = $_SESSION['user_id'];
        $conversations = $this->convs->getByUser($userId);

        $this->view('chat/index', [
            'conversations' => $conversations,
            'activeConv'    => null,
            'messages'      => [],
            'pageTitle'     => 'Tin nhắn',
            'csrf'          => $this->generateCsrf(),
        ]);
    }

    public function conversation(string $id): void
    {
        $userId = $_SESSION['user_id'];
        $conv   = $this->convs->findWithPartner((int)$id, $userId);

        if (!$conv) {
            $this->setFlash('danger', 'Cuộc trò chuyện không tồn tại.');
            $this->redirect('/chat');
        }

        $this->messages->markRead((int)$id, $userId);

        $this->view('chat/index', [
            'conversations' => $this->convs->getByUser($userId),
            'activeConv'    => $conv,
            'messages'      => $this->messages->getByConversation((int)$id),
            'pageTitle'     => 'Trò chuyện với ' . $conv['partner_name'],
            'csrf'          => $this->generateCsrf(),
        ]);
    }

    public function startWith(string $userId): void
    {
        $myId = (int)$_SESSION['user_id'];
        $uid  = (int)$userId;

        if ($uid === $myId || $uid <= 0) {
            $this->redirect('/chat');
        }

        $conv = $this->convs->findOrCreate($myId, $uid);
        $this->redirect('/chat/' . $conv['id']);
    }

    public function send(): void
    {
        header('Content-Type: application/json');
        $userId  = (int)$_SESSION['user_id'];

        // CSRF check
        $csrfToken = $this->post('csrf_token', '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            echo json_encode(['ok' => false, 'error' => 'Yêu cầu không hợp lệ']);
            return;
        }

        $convId  = (int)$this->post('conversation_id', 0);
        $content = trim($this->post('content', ''));

        if (!$convId || $content === '' || mb_strlen($content) > 5000) {
            echo json_encode(['ok' => false, 'error' => 'Dữ liệu không hợp lệ']);
            return;
        }

        $conv = $this->convs->findWithPartner($convId, $userId);
        if (!$conv) {
            echo json_encode(['ok' => false, 'error' => 'Không có quyền']);
            return;
        }

        $msg = $this->messages->send($convId, $userId, $content);
        $this->convs->updateLastMessage($convId, $content);

        echo json_encode(['ok' => true, 'message' => $msg]);
    }

    public function poll(string $id): void
    {
        header('Content-Type: application/json');
        $userId  = (int)$_SESSION['user_id'];
        $convId  = (int)$id;
        $afterId = (int)$this->get('after', 0);

        $conv = $this->convs->findWithPartner($convId, $userId);
        if (!$conv) {
            echo json_encode(['ok' => false]);
            return;
        }

        $newMessages = $this->messages->getAfter($convId, $afterId);
        if (!empty($newMessages)) {
            $this->messages->markRead($convId, $userId);
        }

        $fresh    = $this->convs->findById($convId);
        $isUser1  = (int)($fresh['user1_id'] ?? 0) === $userId;
        $typingCol = $isUser1 ? 'user2_typing_at' : 'user1_typing_at';
        $typingAt  = $fresh[$typingCol] ?? null;
        $typing    = $typingAt && (time() - strtotime($typingAt)) < 4;

        echo json_encode([
            'ok'       => true,
            'messages' => $newMessages,
            'typing'   => $typing,
        ]);
    }

    public function typing(string $id): void
    {
        header('Content-Type: application/json');
        $userId = (int)$_SESSION['user_id'];
        $conv   = $this->convs->findById((int)$id);

        if ($conv && ((int)$conv['user1_id'] === $userId || (int)$conv['user2_id'] === $userId)) {
            $isUser1 = (int)$conv['user1_id'] === $userId;
            $this->convs->updateTyping((int)$id, $isUser1);
        }

        echo json_encode(['ok' => true]);
    }

    public function unreadCount(): void
    {
        header('Content-Type: application/json');
        $count = isset($_SESSION['user_id'])
            ? $this->convs->getTotalUnread((int)$_SESSION['user_id'])
            : 0;
        echo json_encode(['count' => $count]);
    }

    public function conversations(): void
    {
        header('Content-Type: application/json');
        $userId = (int)$_SESSION['user_id'];
        $convs  = $this->convs->getByUser($userId);
        echo json_encode(['conversations' => array_slice($convs, 0, 8)]);
    }

    public function openWithUser(string $userId): void
    {
        header('Content-Type: application/json');
        $myId = (int)$_SESSION['user_id'];
        $uid  = (int)$userId;

        if ($uid <= 0 || $uid === $myId) {
            echo json_encode(['ok' => false, 'error' => 'Invalid user']);
            return;
        }

        $conv            = $this->convs->findOrCreate($myId, $uid);
        $convId          = (int)$conv['id'];
        $convWithPartner = $this->convs->findWithPartner($convId, $myId);
        $messages        = $this->messages->getByConversation($convId, 30);
        $this->messages->markRead($convId, $myId);

        echo json_encode([
            'ok'       => true,
            'conv'     => $convWithPartner,
            'messages' => $messages,
        ]);
    }
}
