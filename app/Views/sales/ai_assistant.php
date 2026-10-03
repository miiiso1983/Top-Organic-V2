<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>مساعد الحجز الذكي | Top Organic Restaurant</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Cairo', Tahoma, sans-serif; margin: 0; background: #f3f4f6; }
  .ai-wrap { max-width: 600px; margin: 40px auto; height: calc(100vh - 80px); display: flex; flex-direction: column; }
  .ai-header { background: linear-gradient(135deg, #10914d, #0a6e3a); color: #fff; padding: 20px; border-radius: 16px 16px 0 0; text-align: center; }
  .ai-header h1 { margin: 0; font-size: 20px; }
  .ai-header p { margin: 6px 0 0; font-size: 13px; opacity: .9; }
  .ai-chat { flex: 1; background: #fff; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
  .ai-msg { max-width: 85%; padding: 12px 14px; border-radius: 14px; font-size: 14px; line-height: 1.5; }
  .ai-msg.user { align-self: flex-end; background: #10914d; color: #fff; border-bottom-right-radius: 4px; }
  .ai-msg.bot { align-self: flex-start; background: #f3f4f6; color: #1f2937; border-bottom-left-radius: 4px; }
  .ai-input { display: flex; gap: 8px; padding: 12px; background: #fff; border-top: 1px solid #e5e7eb; border-radius: 0 0 16px 16px; }
  .ai-input input { flex: 1; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; }
  .ai-input button { padding: 12px 18px; background: #10914d; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; }
  .ai-suggestions { display: flex; gap: 8px; flex-wrap: wrap; padding: 8px 16px; background: #fff; }
  .ai-suggestion { padding: 6px 12px; border: 1px solid #10914d; color: #10914d; border-radius: 20px; font-size: 12px; cursor: pointer; }
  .ai-typing { align-self: flex-start; background: #f3f4f6; padding: 10px 14px; border-radius: 14px; font-size: 12px; color: #6b7280; }
</style>
</head>
<body>
<div class="ai-wrap">
  <div class="ai-header">
    <h1><i class="fa-solid fa-robot"></i> مساعد الحجز الذكي</h1>
    <p>أستطيع مساعدتك في الحجز، الأوقات، التعديل، الإلغاء، والدفع</p>
  </div>
  <div class="ai-chat" id="chatBox"></div>
  <div class="ai-suggestions">
    <span class="ai-suggestion" onclick="sendSuggestion('أهلاً')"><i class="fa-solid fa-hand"></i> أهلاً</span>
    <span class="ai-suggestion" onclick="sendSuggestion('ما الأوقات المتاحة اليوم؟')"><i class="fa-solid fa-calendar-days"></i> الأوقات</span>
    <span class="ai-suggestion" onclick="sendSuggestion('أريد حجز طاولة لـ 4 أشخاص اليوم الساعة 8 المساء')"><i class="fa-solid fa-utensils"></i> حجز طاولة</span>
    <span class="ai-suggestion" onclick="sendSuggestion('أريد التحدث مع موظف')"><i class="fa-solid fa-user"></i> موظف</span>
  </div>
  <div class="ai-input">
    <input type="text" id="userInput" placeholder="اكتب رسالتك هنا..." onkeydown="if(event.key==='Enter') sendMessage()">
    <button onclick="sendMessage()">إرسال</button>
  </div>
</div>

<script>
const chatBox = document.getElementById('chatBox');
const userInput = document.getElementById('userInput');
let channelId = localStorage.getItem('ai_channel_id') || 'web_' + Math.random().toString(36).substr(2, 9);
localStorage.setItem('ai_channel_id', channelId);

function addMessage(text, role) {
  const div = document.createElement('div');
  div.className = 'ai-msg ' + (role === 'user' ? 'user' : 'bot');
  div.textContent = text;
  chatBox.appendChild(div);
  chatBox.scrollTop = chatBox.scrollHeight;
}

function showTyping() {
  const div = document.createElement('div');
  div.className = 'ai-typing';
  div.id = 'typing';
  div.textContent = 'جاري الكتابة...';
  chatBox.appendChild(div);
  chatBox.scrollTop = chatBox.scrollHeight;
}

function hideTyping() {
  const t = document.getElementById('typing');
  if (t) t.remove();
}

async function sendMessage() {
  const text = userInput.value.trim();
  if (!text) return;
  addMessage(text, 'user');
  userInput.value = '';
  showTyping();
  const res = await fetch('/ai-assistant/chat', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({message: text, channel_id: channelId})
  }).then(r => r.json());
  hideTyping();
  if (res.ok) addMessage(res.reply, 'bot');
  else addMessage('عذراً، حدث خطأ. حاول مرة أخرى.', 'bot');
}

function sendSuggestion(text) {
  userInput.value = text;
  sendMessage();
}

addMessage('أهلاً وسهلاً! أنا مساعد الحجز الذكي. كيف يمكنني مساعدتك؟', 'bot');
</script>
</body>
</html>
