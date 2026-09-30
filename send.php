<?php
/* Форма «Напишите нам письмо» сайта STRONG: принимает POST от index.html, шлёт письмо, отвечает JSON.
   Настройка — три строки ниже. Требуется PHP 7.4+ и работающая отправка почты с сервера (mail()). */
$TO      = 'rastrong@ros-tv.com';                    // куда приходят заявки (можно несколько через запятую)
$FROM    = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'rastrong.ru');   // от кого: адрес на домене сайта, иначе письма падают в спам
$SUBJECT = 'Заявка с сайта STRONG';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Только POST']); exit; }

$f = function ($k, $max) { $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; return mb_substr(strip_tags($v), 0, $max, 'UTF-8'); };
$name = $f('name', 120); $email = $f('email', 160); $msg = $f('message', 4000); $trap = $f('site', 50); $consent = !empty($_POST['consent']);

if ($trap !== '') { echo json_encode(['ok' => true]); exit; }                                   // бот заполнил скрытое поле — делаем вид, что отправили
if ($name === '' || $email === '' || $msg === '') { echo json_encode(['ok' => false, 'error' => 'Заполните имя, почту и сообщение.']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'error' => 'Проверьте адрес почты.']); exit; }
if (!$consent) { echo json_encode(['ok' => false, 'error' => 'Нужно согласие на обработку персональных данных.']); exit; }
if (preg_match('/[\r\n]/', $name . $email)) { echo json_encode(['ok' => false, 'error' => 'Недопустимые символы.']); exit; }   // защита от подмены заголовков письма

/* простая защита от потока: не чаще раза в 20 секунд с одного IP */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0'; $lock = sys_get_temp_dir() . '/strong-form-' . md5($ip);
if (file_exists($lock) && time() - filemtime($lock) < 20) { echo json_encode(['ok' => false, 'error' => 'Слишком часто. Попробуйте через минуту.']); exit; }
@touch($lock);

$body = "Имя: $name\nПочта: $email\nIP: $ip\nВремя: " . date('d.m.Y H:i') . "\n\nСообщение:\n$msg\n";
$enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
$headers = "From: " . $enc('Сайт STRONG') . " <$FROM>\r\nReply-To: " . $enc($name) . " <$email>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nX-Mailer: PHP/" . phpversion();
$sent = @mail($TO, $enc($SUBJECT), $body, $headers, '-f' . $FROM);
if (!$sent) $sent = @mail($TO, $enc($SUBJECT), $body, $headers);    // некоторые хостинги не принимают -f

if ($sent) echo json_encode(['ok' => true]);
else { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Сервер не смог отправить письмо. Напишите нам на ' . $TO]); }
