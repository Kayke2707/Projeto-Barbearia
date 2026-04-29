<?php
// registrar.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barbeiro_id   = $_POST['barbeiro']   ?? null;
    $cliente       = trim($_POST['cliente'] ?? '');
    $servico_id    = $_POST['servico']    ?? null;
    $valor         = str_replace(',', '.', $_POST['valor'] ?? '0');
    $data          = $_POST['data']       ?? date('Y-m-d');

    if (!$barbeiro_id || !$cliente || !$servico_id || !$valor || !$data) {
        header("Location: index.php?erro=Campos obrigatórios");
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO atendimentos 
            (barbeiro_id, cliente_nome, servico_id, valor, data_atendimento)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$barbeiro_id, $cliente, $servico_id, $valor, $data]);

        header("Location: index.php?sucesso=Atendimento registrado!");
    } catch (Exception $e) {
        header("Location: index.php?erro=" . urlencode($e->getMessage()));
    }
    exit;
}

header("Location: index.php");