<?php
namespace App\Services;

use App\Models\LocationModel;
use App\Models\RoomBlockModel;

class ChatbotService
{
    private RoomBlockModel $blockModel;
    private LocationModel  $locationModel;

    private const SYSTEM_PROMPT =
        'Bạn là "Trợ lý PhòngTrọ" — trợ lý AI thân thiện của website cho thuê phòng trọ tại Cần Thơ, Việt Nam. '
        . 'Vai trò: tư vấn, giải đáp mọi thắc mắc liên quan đến thuê phòng, nhà trọ, căn hộ. '
        . 'Hướng dẫn: '
        . '(1) Luôn trả lời bằng tiếng Việt, thân thiện, tự nhiên như người thật. '
        . '(2) Câu chào hỏi, hỏi thăm, hỏi về website thì trả lời bình thường và ngắn gọn. '
        . '(3) Khi hỏi về phòng/nhà: nếu có dữ liệu từ database thì tư vấn cụ thể dựa vào đó. '
        . '(4) Khi không tìm thấy phòng phù hợp, hỏi thêm nhu cầu (giá, khu vực, tiện ích). '
        . '(5) Câu hỏi hoàn toàn ngoài lề (thời tiết, toán học,...) thì nhẹ nhàng hướng về chủ đề thuê phòng. '
        . '(6) Độ dài trả lời vừa phải, không quá dài dòng. Có thể dùng **in đậm** để nhấn mạnh.';

    public function __construct()
    {
        $this->blockModel    = new RoomBlockModel();
        $this->locationModel = new LocationModel();
    }

    public function processMessage(string $message): array
    {
        $message = trim($message);

        if ($message === '') {
            return [
                'success'       => true,
                'answer'        => 'Xin chào! Mình là **Trợ lý PhòngTrọ AI**. Bạn đang tìm loại phòng gì, khu vực nào, giá bao nhiêu?',
                'quick_replies' => $this->getQuickReplies(),
                'rooms'         => [],
            ];
        }

        $filters = $this->extractFilters(mb_strtolower($message));

        $blocks = [];
        if ($this->hasAnyFilter($filters)) {
            $blocks = $this->blockModel->getFiltered($filters);

            if (empty($blocks)) {
                $relaxed = array_filter([
                    'price_min' => $filters['price_min'],
                    'price_max' => $filters['price_max'],
                    'type'      => $filters['type'],
                ]);
                $blocks = $this->blockModel->getFiltered($relaxed);
            }

            $blocks = array_slice($blocks, 0, 4);
        }

        $answer = $this->callGemini($message, $blocks);

        return [
            'success'       => true,
            'answer'        => $answer,
            'filters'       => $filters,
            'quick_replies' => $this->getQuickReplies(),
            'rooms'         => array_map([$this, 'formatBlock'], $blocks),
        ];
    }

    private function extractFilters(string $q): array
    {
        $f = [
            'price_min'   => null,
            'price_max'   => null,
            'district_id' => null,
            'ward_id'     => null,
            'type'        => null,
            'has_wifi'    => false,
            'has_ac'      => false,
        ];

        if (preg_match('/dưới\s*([0-9]+(?:[.,][0-9]+)?)\s*(tr|triệu|m)\b/iu', $q, $m)) {
            $f['price_max'] = (int)((float)str_replace(',', '.', $m[1]) * 1000000);
        }
        if (preg_match('/trên\s*([0-9]+(?:[.,][0-9]+)?)\s*(tr|triệu|m)\b/iu', $q, $m)) {
            $f['price_min'] = (int)((float)str_replace(',', '.', $m[1]) * 1000000);
        }
        if (preg_match('/([0-9]+)\s*[-–]\s*([0-9]+)\s*(tr|triệu|m)\b/iu', $q, $m)) {
            $f['price_min'] = (int)((float)$m[1] * 1000000);
            $f['price_max'] = (int)((float)$m[2] * 1000000);
        }
        if (!$f['price_max'] && !$f['price_min']) {
            if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(tr|triệu|m)\b/iu', $q, $m)) {
                $f['price_max'] = (int)((float)str_replace(',', '.', $m[1]) * 1000000);
            }
        }

        if (preg_match('/wifi/i', $q))                      $f['has_wifi'] = true;
        if (preg_match('/(máy\s*lạnh|điều\s*hòa)/iu', $q)) $f['has_ac']   = true;

        $typeMap = [
            '/(ký\s*túc\s*xá|ktx|ở\s*ghép)/iu'          => 'dormitory',
            '/(nhà\s*trọ|phòng\s*trọ)/iu'                => 'boarding_house',
            '/(homestay)/iu'                              => 'homestay',
            '/(căn\s*hộ\s*mini|chung\s*cư\s*mini)/iu'    => 'mini_house',
            '/(nhà\s*nguyên\s*căn|nhà\s*riêng)/iu'       => 'full_house',
        ];
        foreach ($typeMap as $pattern => $type) {
            if (preg_match($pattern, $q)) { $f['type'] = $type; break; }
        }

        foreach ($this->locationModel->getAllDistricts() as $d) {
            if (mb_stripos($q, mb_strtolower($d['name'])) !== false) {
                $f['district_id'] = $d['id'];
                foreach ($this->locationModel->getWardsByDistrict($d['id']) as $w) {
                    if (mb_stripos($q, mb_strtolower($w['name'])) !== false) {
                        $f['ward_id'] = $w['id'];
                        break;
                    }
                }
                break;
            }
        }

        return $f;
    }

    private function hasAnyFilter(array $f): bool
    {
        return !empty($f['price_max'])   || !empty($f['price_min'])
            || !empty($f['district_id']) || !empty($f['type'])
            || !empty($f['has_wifi'])    || !empty($f['has_ac']);
    }

    private function callGemini(string $userMessage, array $blocks): string
    {
        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';

        if (empty($apiKey) || !function_exists('curl_init')) {
            return $this->fallbackAnswer($blocks);
        }

        if (!empty($blocks)) {
            $ctx = "\n[Kết quả từ database — " . count($blocks) . " bất động sản tìm được]\n";
            foreach ($blocks as $i => $b) {
                $price = number_format((float)($b['min_display_price'] ?? $b['price'] ?? 0), 0, ',', '.');
                $ctx  .= ($i + 1) . ". {$b['name']} — {$price}đ/tháng — {$b['address']}\n";
            }
        } else {
            $ctx = '';
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => self::SYSTEM_PROMPT]],
            ],
            'contents' => [[
                'parts' => [['text' => $userMessage . "\n" . $ctx]],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => 512,
                'temperature'     => 0.8,
                'topP'            => 0.95,
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='
             . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($result)) {
            return $this->fallbackAnswer($blocks);
        }

        $decoded = json_decode($result, true);
        $text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $text ? trim($text) : $this->fallbackAnswer($blocks);
    }

    private function fallbackAnswer(array $blocks): string
    {
        if (empty($blocks)) {
            return 'Mình chưa tìm thấy bất động sản phù hợp. Bạn thử điều chỉnh giá hoặc khu vực nhé!';
        }
        return 'Mình tìm được ' . count($blocks) . ' bất động sản phù hợp. Xem chi tiết bên dưới nhé!';
    }

    private function formatBlock(array $b): array
    {
        $price = $b['min_display_price'] ?? $b['price'] ?? 0;
        return [
            'id'            => $b['id'],
            'title'         => $b['name'],
            'price'         => number_format((float)$price, 0, ',', '.'),
            'address'       => $b['address'] ?? '',
            'district_name' => $b['district_name'] ?? null,
            'image'         => !empty($b['primary_image']) ? (UPLOAD_URL . $b['primary_image']) : '',
            'type'          => $b['type'] ?? '',
            'url'           => '/properties/' . $b['id'],
        ];
    }

    private function getQuickReplies(): array
    {
        return [
            ['label' => 'Dưới 2 triệu',  'value' => 'phòng dưới 2 triệu'],
            ['label' => 'Có máy lạnh',   'value' => 'phòng có máy lạnh'],
            ['label' => 'Gần Ninh Kiều', 'value' => 'phòng gần trung tâm Ninh Kiều'],
            ['label' => 'Ký túc xá',     'value' => 'ký túc xá giá rẻ'],
        ];
    }
}
