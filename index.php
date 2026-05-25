<?php
define('TOKEN', '8590790661:AAF4TAEn_W0I98NYAwKKibuFEMpUMBV2Gyw');
define('OPENROUTER_KEY', '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');

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$text = $message['text'] ?? null;
if (!$chat_id) exit;

// Vercel uchun status faylini vaqtinchalik papkada saqlaymiz
$user_status_file = "/tmp/status_" . $chat_id . ".txt";
$user_status = file_exists($user_status_file) ? file_get_contents($user_status_file) : 'menu';

function sendMessage($chat_id, $text, $keyboard = null) {
    $url = "https://api.telegram.org/bot" . TOKEN . "/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown', 'disable_web_page_preview' => true];
    if ($keyboard) $data['reply_markup'] = json_encode($keyboard);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function callOpenRouter($model, $prompt) {
    $url = "https://openrouter.ai/api/v1/chat/completions";
    $headers = [
        "Authorization: Bearer " . OPENROUTER_KEY,
        "Content-Type: application/json"
    ];
    $post_data = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => $prompt]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($res, true);
    return $result['choices'][0]['message']['content'] ?? null;
}

$main_keyboard = [
    'keyboard' => [
        [['text' => '📝 Matnli Reja'], ['text' => '🎨 Rasm (Oblozhka)']],
        [['text' => '🎬 Video Kreativ']],
        [['text' => '📊 Profilim']]
    ],
    'resize_keyboard' => true
];

if ($text == '/start') {
    sendMessage($chat_id, "👋 *Kontent Reja AI* botiga xush keldingiz! Quyidagi menyudan birini tanlang:", $main_keyboard);
    file_put_contents($user_status_file, 'menu');
    exit;
}

if ($text == '📊 Profilim') {
    sendMessage($chat_id, "👤 *Profil:*\n\nID: `$chat_id`\nStatus: 💎 Premium\nLimit: Cheksiz", $main_keyboard);
    exit;
}

if (in_array($text, ['📝 Matnli Reja', '🎨 Rasm (Oblozhka)', '🎬 Video Kreativ'])) {
    $status_map = ['📝 Matnli Reja' => 'w_text', '🎨 Rasm (Oblozhka)' => 'w_img', '🎬 Video Kreativ' => 'w_vid'];
    $msg_map = [
        '📝 Matnli Reja' => "✍️ SMM reja kerak bo'lgan soha nomini kiriting:",
        '🎨 Rasm (Oblozhka)' => "🖼 Rasm uchun g'oya tavsifini yozing:",
        '🎬 Video Kreativ' => "📹 Video ssenariy uchun mavzu yozing:"
    ];
    sendMessage($chat_id, $msg_map[$text]);
    file_put_contents($user_status_file, $status_map[$text]);
    exit;
}

if (in_array($user_status, ['w_text', 'w_img', 'w_vid'])) {
    if ($user_status == 'w_text') {
        $prompt = "Siz tajribali marketolog va SMM mutaxassisiz. '$text' sohasi uchun jozibador, qiziqarli va odamlarni jalb qiladigan 3 ta kreativ post g'oyasini va matnini chiroyli formatda o'zbek tilida yozib bering.";
    } elseif ($user_status == 'w_img') {
        $prompt = "Foydalanuvchi rasm yaratmoqchi. Uning g'oyasi: '$text'. Ushbu g'oya asosida Midjourney yoki Flux neyrotarmoqlari uchun ingliz tilida professional va juda batafsil rasm yaratish 'Promt' matnini yozib bering. Ortiqcha gap qo'shmang, faqat promtni o'zini bering.";
    } elseif ($user_status == 'w_vid') {
        $prompt = "Kreativ qisqa video (Reels/Shorts) uchun g'oya: '$text'. Ushbu g'oya bo'yicha ketma-ketlik kadrlar ko'rinishi (visual) va diktor matni bilan to'liq o'zbekcha video ssenariy tuzib bering.";
    }

    $res = callOpenRouter("meta-llama/llama-3-8b-instruct:free", $prompt);

    if ($res) {
        sendMessage($chat_id, $res, $main_keyboard);
    } else {
        sendMessage($chat_id, "❌ Llama neyrotarmog'i band yoki xatolik berdi. Iltimos, qaytadan urinib ko'ring.", $main_keyboard);
    }
    
    file_put_contents($user_status_file, 'menu');
    exit;
}
?>