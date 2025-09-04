<?php
use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// ===== 0. Charger le .env =====
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ===== CONFIG =====
$openaiApiKey = $_ENV['OPENAI_API_KEY'];
$smtpUser     = $_ENV['SMTP_USER'];
$smtpPass     = $_ENV['SMTP_PASS'];
$smtpHost     = "smtp.gmail.com";
$smtpPort     = 587;
$toEmail      = $_ENV['TO_EMAIL'];

if (!$openaiApiKey || !$smtpUser || !$smtpPass || !$toEmail) {
    die("❌ Une ou plusieurs variables d'environnement manquent.\n");
}

// ===== 1. Générer la veille via OpenAI =====
$client = new Client([
    'base_uri' => 'https://api.openai.com/v1/',
    'headers'  => [
        'Authorization' => "Bearer $openaiApiKey",
        'Content-Type'  => 'application/json',
    ]
]);

$prompt = <<<EOT
Génère une veille quotidienne de 5 minutes pour un développeur junior.
Inclure toujours :
1) Concept PHP avec exemple de code.
2) Concept SQL avec requête.
3) Concept JavaScript moderne avec exemple.
4) Nouveauté framework (Laravel/Symfony).
5) Un petit exercice pratique (PHP/SQL/JS).
6) Concept théorique de base sur interface, container, services, design pattern ...

Format : HTML lisible pour email.
EOT;

$response = $client->post('chat/completions', [
    'json' => [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Tu es un assistant pour développeur junior.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 800
    ]
]);

$data = json_decode($response->getBody(), true);
$veilleContent = $data['choices'][0]['message']['content'] ?? "<p>⚠️ Erreur génération veille.</p>";

// ===== 2. Envoi email =====
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpPort;

    $mail->setFrom($smtpUser, 'Veille Dev Junior');
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = "🚀 Veille Dev Junior - " . date("d/m/Y");
    $mail->Body    = $veilleContent;

    $mail->send();
    echo "✅ Email envoyé avec succès.\n";
} catch (Exception $e) {
    echo "❌ Erreur : {$mail->ErrorInfo}\n";
}
