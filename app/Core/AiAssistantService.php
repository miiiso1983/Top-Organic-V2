<?php
namespace App\Core;

/**
 * AI Reservation Assistant - مساعد الحجز الذكي
 * يدعم قنوات متعددة ويمكن استبدال محرك النوايا بـ LLM API.
 */
class AiAssistantService
{
    public const CHANNELS = ['web','whatsapp','messenger','telegram','phone','api'];

    public static function receive(string $channel, string $channelId, string $message, array $meta = []): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \RuntimeException('قناة غير مدعومة');
        }
        $config = Database::query("SELECT * FROM ai_channel_configs WHERE channel = ?", [$channel])->fetch();
        if ($config && !$config['is_enabled']) {
            return self::reply('عذراً، هذه القناة معطلة حالياً.', $channel, $channelId);
        }

        $sessionId = $meta['session_id'] ?? ($channel . ':' . $channelId);
        $conversation = self::getOrCreateConversation($channel, $channelId, $sessionId, $meta);
        if ($conversation['status'] === 'handoff') {
            self::storeMessage($conversation['id'], 'in', 'user', $message, null, $meta);
            return self::reply('تم تحويل محادثتك لموظف. يرجى الانتظار.', $channel, $channelId, $conversation['id']);
        }

        self::storeMessage($conversation['id'], 'in', 'user', $message);
        $response = self::process($conversation, $message);
        self::storeMessage($conversation['id'], 'out', 'assistant', $response['text'], $response['intent'] ?? null, $response['payload'] ?? null);
        self::updateContext($conversation['id'], $response['context'] ?? []);

        return ['ok' => true, 'reply' => $response['text'], 'intent' => $response['intent'] ?? null, 'conversation_id' => $conversation['id']];
    }

    private static function getOrCreateConversation(string $channel, string $channelId, string $sessionId, array $meta): array
    {
        $row = Database::query(
            "SELECT * FROM ai_conversations WHERE channel = ? AND channel_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$channel, $channelId]
        )->fetch();
        if ($row) return $row;

        Database::query(
            "INSERT INTO ai_conversations (channel, channel_id, session_id, customer_id, customer_phone, customer_name, language, context_json)
             VALUES (?,?,?,?,?,?,?,?)",
            [$channel, $channelId, $sessionId, $meta['customer_id'] ?? null, $meta['phone'] ?? null, $meta['name'] ?? null, $meta['language'] ?? 'ar', json_encode([], JSON_UNESCAPED_UNICODE)]
        );
        return self::getOrCreateConversation($channel, $channelId, $sessionId, $meta);
    }

    private static function storeMessage(int $conversationId, string $direction, string $role, string $message, ?string $intent = null, ?array $payload = null): void
    {
        Database::query(
            "INSERT INTO ai_messages (conversation_id, direction, role, message, intent, payload_json) VALUES (?,?,?,?,?,?)",
            [$conversationId, $direction, $role, $message, $intent, $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]
        );
    }

    private static function updateContext(int $conversationId, array $context): void
    {
        Database::query(
            "UPDATE ai_conversations SET context_json = ?, last_message_at = NOW() WHERE id = ?",
            [json_encode($context, JSON_UNESCAPED_UNICODE), $conversationId]
        );
    }

    public static function process(array $conversation, string $message): array
    {
        $context = json_decode($conversation['context_json'] ?? '{}', true) ?: [];

        // 1. محاولة استخدام OpenAI LLM إذا كان مفعّلاً
        $llm = OpenAiService::isEnabled() ? OpenAiService::process($conversation, $message) : null;

        if ($llm) {
            $intent = $llm['intent'];
            $entities = array_merge($context['entities'] ?? [], $llm['entities'] ?? []);

            // الردود غير العملية: استخدم رد LLM مباشرة
            if (in_array($intent, ['greeting', 'faq', 'fallback'], true)) {
                self::logIntent($conversation['id'], $message, $intent, 0.9, $entities, $llm['text']);
                return ['text' => $llm['text'], 'context' => $context, 'intent' => $intent];
            }

            // التحويل: نفذ التحويل ثم استخدم رد LLM
            if ($intent === 'handoff') {
                self::handleHandoff($conversation, $message);
                return ['text' => $llm['text'], 'context' => ['pending_intent' => null], 'intent' => 'handoff'];
            }
        } else {
            // 2. الرجوع إلى محرك القواعد
            $intent = self::detectIntent($message, $context);
            $entities = self::extractEntities($message, $context);
        }

        // 3. احترام النية المعلقة من المحادثة السابقة
        $pendingIntent = $context['pending_intent'] ?? null;
        if ($pendingIntent) {
            $intent = $pendingIntent;
            $entities = array_merge($context['entities'] ?? [], $entities);
        }

        $response = match ($intent) {
            'availability' => self::handleAvailability($entities, $context),
            'book' => self::handleBooking($message, $entities, $context, $conversation),
            'modify' => self::handleModify($message, $entities, $context, $conversation),
            'cancel' => self::handleCancel($message, $entities, $context, $conversation),
            'payment' => self::handlePayment($entities, $context, $conversation),
            'handoff' => self::handleHandoff($conversation, $message),
            'faq' => self::handleFaq($message),
            'greeting' => self::handleGreeting($conversation),
            default => self::handleFallback($message, $context),
        };

        $response['intent'] = $intent;
        self::logIntent($conversation['id'], $message, $intent, $llm ? 0.9 : 0.7, $entities, $response['text']);
        return $response;
    }

    private static function detectIntent(string $message, array $context): string
    {
        $msg = self::normalize($message);
        $keywords = [
            'availability' => ['متاح','فاضي','فارغ','أوقات','مواعيد','هل يوجد','هل في'],
            'book' => ['احجز','أحجز','حجز','أريد طاولة','أبغى حجز','حجز طاولة','reserve','booking'],
            'modify' => ['عدل','تعديل','غير','غير الموعد','نقل الحجز','أجل','أخجل','أعدل'],
            'cancel' => ['إلغاء','الغاء',' cancel','ألغي','حذف الحجز'],
            'payment' => ['دفع','payment','عربون','فاتورة','السعر','كم يكلف','payment link','رابط دفع'],
            'handoff' => ['موظف','شخص','عاون','مساعد','تحدث مع','تواصل مع','human','agent'],
            'greeting' => ['مرحبا','السلام','هلا','أهلا','hello','hi','صباح','مساء'],
            'faq' => ['ساعات العمل','العنوان','الفرع','القائمة','طلبات','هل تقدمون','أين موقعكم'],
        ];
        foreach ($keywords as $intent => $words) {
            foreach ($words as $w) {
                if (str_contains($msg, $w)) return $intent;
            }
        }
        return $context['pending_intent'] ?? 'fallback';
    }

    private static function extractEntities(string $message, array $context): array
    {
        $entities = $context['entities'] ?? [];
        $msg = self::normalize($message);

        // عدد الأشخاص
        if (preg_match('/(\d+)\s*(شخص|أشخاص|نفر|نفرات|ضيف|ضيوف)/u', $msg, $m)) {
            $entities['guests'] = (int)$m[1];
        }
        // التاريخ
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $msg, $m)) {
            $entities['date'] = $m[1];
        } elseif (preg_match('/(اليوم|بكرة|بكره|غدا|غداً|الغد)/u', $msg, $m)) {
            $entities['date'] = match ($m[1]) {
                'اليوم' => date('Y-m-d'),
                'بكرة', 'بكره', 'غدا', 'غداً', 'الغد' => date('Y-m-d', strtotime('+1 day')),
                default => null,
            };
        }
        // الوقت
        if (preg_match('/(\d{1,2}):(\d{2})/', $msg, $m)) {
            $entities['time'] = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }
        // رقم الحجز
        if (preg_match('/(RES-\d+|\d{5,})/', $msg, $m)) {
            $entities['reservation_no'] = $m[1];
        }
        // الهاتف - العراق 07XXXXXXXXX أو السعودية 05XXXXXXXX
        if (preg_match('/(07\d{9}|05\d{8})/', $msg, $m)) {
            $entities['phone'] = $m[1];
        }
        // الاسم - إذا قال "اسمي/أنا" صراحةً
        if (preg_match('/(اسمي|أنا|انا)\s+(.{2,40})/u', $msg, $m)) {
            $entities['name'] = trim($m[2]);
        } elseif (empty($entities['name'])) {
            // fallback: استخراج اسم لاتيني أو عربي من الرسالة القصيرة
            $entities['name'] = self::extractNameFallback($message);
        }

        return $entities;
    }

    private static function extractNameFallback(string $message): ?string
    {
        $msg = self::normalize($message);
        // أزل الهاتف والوقت والتواريخ والأرقام والرموز
        $clean = preg_replace('/(07\d{9}|05\d{8})/', '', $message);
        $clean = preg_replace('/\d{1,2}:\d{2}/', '', $clean);
        $clean = preg_replace('/\d{4}-\d{2}-\d{2}/', '', $clean);
        $clean = preg_replace('/[^\p{L}\s]/u', ' ', $clean);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean));
        if (mb_strlen($clean) > 50 || mb_strlen($clean) < 3) return null;

        // اسم لاتيني: كلمتان-ثلاث مكونة من حروف
        if (preg_match('/([A-Za-z]{2,}(?:\s+[A-Za-z]{2,}){1,2})/u', $clean, $m)) {
            $name = $m[1];
            if (mb_strlen($name) <= 40) return $name;
        }

        // اسم عربي: كلمتان-ثلاث من الحروف العربية
        if (preg_match('/([\x{0600}-\x{06FF}]{2,}(?:\s+[\x{0600}-\x{06FF}]{2,}){1,2})/u', $clean, $m)) {
            $name = $m[1];
            $skip = ['احجز','أحجز','حجز','طاولة','موعد','اليوم','بكرة','غدا','الغد','الساعة','الوقت','رقم','الهاتف','الاسم','اتصل','تعديل','إلغاء','الغاء','مطعم','مطاعم','طعام'];
            if (in_array($name, $skip, true)) return null;
            if (mb_strlen($name) <= 40) return $name;
        }

        return null;
    }

    private static function handleBooking(string $message, array $entities, array $context, array $conversation): array
    {
        $missing = [];
        if (empty($entities['date'])) $missing[] = 'التاريخ';
        if (empty($entities['time'])) $missing[] = 'الوقت';
        if (empty($entities['guests'])) $missing[] = 'عدد الأشخاص';
        if (empty($entities['phone']) && empty($conversation['customer_phone'])) $missing[] = 'رقم الهاتف';
        if (empty($entities['name']) && empty($conversation['customer_name'])) $missing[] = 'الاسم';

        if (!empty($missing)) {
            $context['pending_intent'] = 'book';
            $context['entities'] = $entities;
            $acknowledged = [];
            if (!empty($entities['date'])) $acknowledged[] = 'التاريخ ' . $entities['date'];
            if (!empty($entities['time'])) $acknowledged[] = 'الوقت ' . $entities['time'];
            if (!empty($entities['guests'])) $acknowledged[] = 'عدد الضيوف ' . $entities['guests'];
            if (!empty($entities['phone'])) $acknowledged[] = 'رقم الهاتف ' . $entities['phone'];
            if (!empty($entities['name'])) $acknowledged[] = 'الاسم ' . $entities['name'];

            $text = '';
            if (!empty($acknowledged)) {
                $text = 'تمام، فهمت: ' . implode('، ', $acknowledged) . '. ';
            }
            $text .= 'أحتاج لإكمال الحجز: ' . implode('، ', $missing) . '.';
            return ['text' => $text, 'context' => $context];
        }

        $branchId = $entities['branch_id'] ?? (int)Settings::get('call_center_default_branch', 1);
        $typeCode = $entities['type'] ?? 'regular';
        $type = ReservationTypeService::getByCode($typeCode) ?: ['min_guests' => 1, 'max_guests' => 99, 'duration_minutes' => 90];
        $guests = (int)($entities['guests'] ?? 2);
        $reservedAt = $entities['date'] . ' ' . $entities['time'] . ':00';
        $customerName = $entities['name'] ?? $conversation['customer_name'] ?? 'عميل';
        $phone = $entities['phone'] ?? $conversation['customer_phone'] ?? '';

        try {
            $id = ReservationService::create([
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'branch_id' => $branchId,
                'guests' => $guests,
                'reserved_at' => $entities['date'] . ' ' . $entities['time'],
                'reservation_type' => $typeCode,
                'duration_minutes' => (int)($entities['duration'] ?? $type['duration_minutes']),
                'notes' => $entities['notes'] ?? '',
                'source' => 'ai_assistant',
                'force_status' => 'confirmed',
            ], 0);
            $res = ReservationService::getWithDetails($id);
            $context['pending_intent'] = null;
            $context['entities'] = [];
            return ['text' => 'تم إنشاء الحجز بنجاح! رقم الحجز: ' . $res['reservation_no'] . ' لـ ' . $guests . ' أشخاص يوم ' . $entities['date'] . ' الساعة ' . $entities['time'] . '.', 'context' => $context, 'payload' => ['reservation_id' => $id]];
        } catch (\Throwable $e) {
            return ['text' => 'لم أتمكن من إنشاء الحجز: ' . $e->getMessage() . '. هل تريد اقتراح وقت آخر؟', 'context' => $context];
        }
    }

    private static function handleAvailability(array $entities, array $context): array
    {
        $branchId = $entities['branch_id'] ?? (int)Settings::get('call_center_default_branch', 1);
        $date = $entities['date'] ?? date('Y-m-d');
        $typeCode = $entities['type'] ?? 'regular';
        $guests = (int)($entities['guests'] ?? 2);
        $slots = ReservationService::getPublicSlots($branchId, $date, $guests, $typeCode);
        $available = array_filter($slots, fn($s) => $s['available']);
        if (empty($available)) {
            return ['text' => 'لا توجد فترات متاحة يوم ' . $date . '. يمكنك تجربة يوم آخر أو تقليل عدد الأشخاص.', 'context' => $context];
        }
        $times = array_slice(array_column($available, 'time'), 0, 6);
        return ['text' => 'الفترات المتاحة يوم ' . $date . ' لـ ' . $guests . ' أشخاص: ' . implode('، ', $times) . '. هل تريد حجز إحداها؟', 'context' => $context];
    }

    private static function handleModify(string $message, array $entities, array $context, array $conversation): array
    {
        if (empty($entities['reservation_no'])) {
            $context['pending_intent'] = 'modify';
            return ['text' => 'يرجى إرسال رقم الحجز الذي تريد تعديله.', 'context' => $context];
        }
        // اقتراح بدائل إذا لم يكن هناك تاريخ/وقت جديد
        if (empty($entities['date']) || empty($entities['time'])) {
            $context['pending_intent'] = 'modify';
            $context['entities'] = $entities;
            return ['text' => 'ما هو الموعد الجديد لحجز ' . $entities['reservation_no'] . '؟', 'context' => $context];
        }
        return ['text' => 'تم تعديل الحجز ' . $entities['reservation_no'] . ' إلى ' . $entities['date'] . ' ' . $entities['time'] . ' (يتطلب تأكيد من النظام).', 'context' => ['pending_intent' => null]];
    }

    private static function handleCancel(string $message, array $entities, array $context, array $conversation): array
    {
        if (empty($entities['reservation_no'])) {
            $context['pending_intent'] = 'cancel';
            return ['text' => 'يرجى إرسال رقم الحجز الذي تريد إلغاءه.', 'context' => $context];
        }
        return ['text' => 'هل أنت متأكد من إلغاء الحجز ' . $entities['reservation_no'] . '؟ أرسل "نعم" للتأكيد.', 'context' => ['pending_intent' => 'cancel_confirm', 'entities' => $entities]];
    }

    private static function handlePayment(array $entities, array $context, array $conversation): array
    {
        $reservationNo = $entities['reservation_no'] ?? '';
        $link = 'https://www.toporganic.rest/pay?ref=' . urlencode($reservationNo) . '&channel=' . $conversation['channel'];
        return ['text' => 'يمكنك إتمام الدفع من خلال الرابط التالي: ' . $link, 'context' => $context, 'payload' => ['payment_link' => $link]];
    }

    private static function handleHandoff(array $conversation, string $message): array
    {
        Database::query(
            "UPDATE ai_conversations SET status='handoff', handoff_reason=? WHERE id=?",
            [$message, $conversation['id']]
        );
        self::storeMessage($conversation['id'], 'out', 'system', 'تم تحويل المحادثة لموظف.', 'handoff');
        // إشعار للموظفين المتوفرين
        self::notifyHandoff($conversation);
        return ['text' => 'تم تحويل محادثتك لموظف. سيتواصل معك قريباً.', 'context' => ['pending_intent' => null]];
    }

    private static function handleFaq(string $message): array
    {
        $msg = self::normalize($message);
        if (str_contains($msg, 'ساعات العمل') || str_contains($msg, 'مواعيد')) {
            return ['text' => 'أوقات العمل من ' . Settings::get('reservation_opening_time', '09:00') . ' إلى ' . Settings::get('reservation_closing_time', '23:00') . ' يومياً.'];
        }
        if (str_contains($msg, 'العنوان') || str_contains($msg, 'موقع')) {
            return ['text' => 'يمكنك زيارة صفحة التواصل على الموقع للحصول على العنوان والخرائط.'];
        }
        return ['text' => 'شكراً لسؤالك. يمكنني مساعدتك في الحجز، الأوقات المتاحة، أو التعديل. هل تريد حجز طاولة؟'];
    }

    private static function handleGreeting(array $conversation): array
    {
        $name = $conversation['customer_name'] ? ' ' . $conversation['customer_name'] : '';
        return ['text' => 'أهلاً وسهلاً' . $name . '! أنا مساعد الحجز الذكي في Top Organic Restaurant. كيف يمكنني مساعدتك؟ (احجز، أوقات، تعديل، إلغاء)'];
    }

    private static function handleFallback(string $message, array $context): array
    {
        return ['text' => 'لم أفهم طلبك. يمكنني مساعدتك في: معرفة الأوقات المتاحة، إنشاء حجز، تعديل حجز، إلغاء حجز، أو الإجابة عن الأسئلة. هل تريد التحدث مع موظف؟'];
    }

    private static function reply(string $text, string $channel, string $channelId, ?int $conversationId = null): array
    {
        return ['ok' => true, 'reply' => $text, 'intent' => 'system', 'conversation_id' => $conversationId];
    }

    private static function normalize(string $text): string
    {
        return strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    private static function logIntent(int $conversationId, string $message, string $intent, float $confidence, array $entities, string $response): void
    {
        Database::query(
            "INSERT INTO ai_intent_logs (conversation_id, message, detected_intent, confidence, extracted_entities, response) VALUES (?,?,?,?,?,?)",
            [$conversationId, $message, $intent, $confidence, json_encode($entities, JSON_UNESCAPED_UNICODE), $response]
        );
    }

    private static function notifyHandoff(array $conversation): void
    {
        $managers = Database::query("SELECT u.id FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id WHERE p.code = 'ai_assistant.handoff'")->fetchAll();
        foreach ($managers as $m) {
            Helpers::notify((int)$m['id'], 'محادثة محولة من AI', 'عميل من قناة ' . $conversation['channel'] . ' يحتاج لموظف.', '/admin/ai-conversations');
        }
    }
}
