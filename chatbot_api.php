<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =================================================================
// CONFIGURAÇÃO
// =================================================================

// Insira sua Chave da API do Gemini aqui
$geminiApiKey = 'AIzaSyAcRbbgx3wM7W4sgnYAGBJJakpIrupTWFo';

// Configurações do Banco de Dados
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'chatbot_db.sql';

// =================================================================
// CONEXÃO COM O BANCO DE DADOS E LÓGICA
// =================================================================

// Estabelece conexão segura
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Falha na conexão com o banco de dados: ' . $conn->connect_error]));
}

// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Pega a mensagem do usuário enviada pelo JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['error' => 'Nenhuma mensagem recebida.']);
    exit;
}

// Salva a mensagem do usuário de forma segura
$stmt = $conn->prepare("INSERT INTO messages (sender, message) VALUES (?, ?)");
$senderUser = 'user';
$stmt->bind_param("ss", $senderUser, $userMessage);
$stmt->execute();

// =================================================================
// CHAMADA PARA A API DO GEMINI
// =================================================================

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $geminiApiKey;
$postData = ['contents' => [['parts' => [['text' => $userMessage]]]]];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// =================================================================
// PROCESSAMENTO DA RESPOSTA
// =================================================================

if ($httpcode != 200 || $response === false) {
    echo json_encode(['error' => 'Falha ao chamar a API do Gemini. Código: ' . $httpcode]);
    exit;
}

$responseData = json_decode($response, true);
$botReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Desculpe, não consegui processar sua solicitação.';

// Salva a resposta do bot no banco de dados
$senderBot = 'bot';
$stmt->bind_param("ss", $senderBot, $botReply);
$stmt->execute();

$stmt->close();
$conn->close();

// Envia a resposta do bot de volta para o front-end
echo json_encode(['reply' => $botReply]);

