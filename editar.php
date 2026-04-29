<?php
session_start();
require_once 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php?erro=' . urlencode('Atendimento não encontrado.'));
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barbeiro_id = $_POST['barbeiro'] ?? null;
    $cliente = trim($_POST['cliente'] ?? '');
    $servico_id = $_POST['servico'] ?? null;
    $valor = str_replace(',', '.', trim($_POST['valor'] ?? '0'));
    $data = $_POST['data'] ?? '';

    if (!$barbeiro_id || !$cliente || !$servico_id || $valor === '' || !$data) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif (!is_numeric($valor)) {
        $error = 'Informe um valor válido.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE atendimentos SET barbeiro_id = ?, cliente_nome = ?, servico_id = ?, valor = ?, data_atendimento = ? WHERE id = ?');
            $stmt->execute([$barbeiro_id, $cliente, $servico_id, $valor, $data, $id]);
            header('Location: index.php?sucesso=' . urlencode('Atendimento atualizado com sucesso.'));
            exit;
        } catch (Exception $e) {
            $error = 'Erro ao atualizar atendimento: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM atendimentos WHERE id = ?');
$stmt->execute([$id]);
$atendimento = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$atendimento) {
    header('Location: index.php?erro=' . urlencode('Atendimento não encontrado.'));
    exit;
}

$barbeiros = $pdo->query('SELECT * FROM barbeiros ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$servicos = $pdo->query('SELECT * FROM servicos ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br" class="bg-gray-900 text-gray-100">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Editar Atendimento - Barbearia Três Lâminas</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-950">
  <main class="max-w-5xl mx-auto px-4 py-10">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold">Editar Atendimento</h1>
          <p class="text-sm text-gray-400">Altere os dados do atendimento e salve.</p>
        </div>
        <a href="index.php" class="text-amber-400 hover:underline">Voltar ao Painel</a>
      </div>

      <?php if (!empty($error)): ?>
        <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="editar.php?id=<?= htmlspecialchars($id) ?>" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium mb-1">Barbeiro</label>
          <select name="barbeiro" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
            <option value="" disabled>Selecione o barbeiro</option>
            <?php foreach ($barbeiros as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $atendimento['barbeiro_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Nome do Cliente</label>
          <input type="text" name="cliente" required value="<?= htmlspecialchars($atendimento['cliente_nome']) ?>" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Serviço</label>
          <select name="servico" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
            <option value="" disabled>Selecione o serviço</option>
            <?php foreach ($servicos as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $atendimento['servico_id'] == $s['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['valor'], 2, ',', '.') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Valor (R$)</label>
          <input type="text" name="valor" required value="<?= number_format($atendimento['valor'], 2, ',', '.') ?>" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600" />
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Data</label>
          <input type="date" name="data" required value="<?= htmlspecialchars($atendimento['data_atendimento']) ?>" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600" />
        </div>

        <div class="md:col-span-2 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <a href="index.php" class="text-amber-400 hover:underline">Cancelar</a>
          <button type="submit" class="w-full md:w-auto bg-amber-600 hover:bg-amber-700 text-white font-bold py-4 px-6 rounded-lg transition">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
