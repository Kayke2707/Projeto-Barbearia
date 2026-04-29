<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_atendimento') {
    $atendimentoId = $_POST['atendimento_id'] ?? null;
    if (!$atendimentoId) {
        header('Location: index.php?erro=' . urlencode('Atendimento inválido.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM atendimentos WHERE id = ?');
        $stmt->execute([$atendimentoId]);
        header('Location: index.php?sucesso=' . urlencode('Atendimento excluído com sucesso.'));
    } catch (Exception $e) {
        header('Location: index.php?erro=' . urlencode('Erro ao excluir atendimento: ' . $e->getMessage()));
    }
    exit;
}

// Período de comissões: segunda a sábado; domingo zera para iniciar nova semana
$hoje = new DateTime();
if ($hoje->format('N') === '7') {
    $inicioSemana = (clone $hoje)->modify('monday next week')->format('Y-m-d');
    $fimSemana = (clone $hoje)->modify('saturday next week')->format('Y-m-d');
} else {
    $inicioSemana = (clone $hoje)->modify('monday this week')->format('Y-m-d');
    $fimSemana = (clone $hoje)->modify('saturday this week')->format('Y-m-d');
}

// Busca dados
$atendimentos = $pdo->query("
    SELECT a.*, b.nome AS barbeiro, s.nome AS servico 
    FROM atendimentos a
    JOIN barbeiros b ON a.barbeiro_id = b.id
    JOIN servicos s ON a.servico_id = s.id
    WHERE a.data_atendimento BETWEEN '$inicioSemana' AND '$fimSemana'
    ORDER BY a.data_atendimento DESC, a.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalFaturado = $pdo->query("SELECT SUM(valor) FROM atendimentos WHERE data_atendimento BETWEEN '$inicioSemana' AND '$fimSemana'")->fetchColumn() ?? 0;
$totalAtendimentos = count($atendimentos);
$totalComissao = $totalFaturado * 0.5;

$porBarbeiro = $pdo->query("
    SELECT b.nome, COUNT(a.id) as qtd, SUM(a.valor) as total
    FROM barbeiros b
    LEFT JOIN atendimentos a ON b.id = a.barbeiro_id 
        AND a.data_atendimento BETWEEN '$inicioSemana' AND '$fimSemana'
    GROUP BY b.id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br" class="bg-gray-900 text-gray-100">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barbearia Três Lâminas - Comissões</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: { extend: { colors: { primary: '#d97706' } } }
    }
  </script>
</head>
<body class="min-h-screen bg-gray-950">

  <!-- Cabeçalho -->
  <header class="bg-gray-800 border-b border-gray-700">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-amber-600 flex items-center justify-center text-xl font-bold">R</div>
        <div>
          <h1 class="text-xl font-bold">Barbearia Três Lâminas</h1>
          <p class="text-sm text-gray-400">Controle de Comissões</p>
          <p class="mt-2"><a href="admin.php" class="text-amber-400 hover:underline">Área do Admin</a></p>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 py-8">

    <div class="mb-6 text-sm text-gray-400">
      Ciclo de comissões: segunda a sábado. Domingo o sistema zera para preparar a próxima semana.
      <br />Período atual: <?= date('d/m/Y', strtotime($inicioSemana)) ?> até <?= date('d/m/Y', strtotime($fimSemana)) ?>.
    </div>

    <!-- Formulário Novo Atendimento -->
    <div class="bg-gray-800 rounded-xl shadow-xl p-6 mb-10 border border-gray-700">
      <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <span class="text-3xl">+</span> Novo Atendimento
      </h2>

      <?php if(isset($_GET['sucesso'])): ?>
        <div class="bg-green-900/50 border border-green-700 text-green-300 px-4 py-3 rounded mb-6">
          <?= htmlspecialchars($_GET['sucesso']) ?>
        </div>
      <?php endif; ?>
      <?php if(isset($_GET['erro'])): ?>
        <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded mb-6">
          <?= htmlspecialchars($_GET['erro']) ?>
        </div>
      <?php endif; ?>

      <form action="registrar.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Barbeiro -->
        <div>
          <label class="block text-sm font-medium mb-1">Barbeiro</label>
          <select name="barbeiro" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
            <option value="" disabled selected>Selecione o barbeiro</option>
            <?php
            $barbeiros = $pdo->query("SELECT * FROM barbeiros ORDER BY nome")->fetchAll();
            foreach($barbeiros as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Cliente -->
        <div>
          <label class="block text-sm font-medium mb-1">Nome do Cliente</label>
          <input type="text" name="cliente" required placeholder="Nome do cliente" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
        </div>

        <!-- Serviço -->
        <div>
          <label class="block text-sm font-medium mb-1">Serviço</label>
          <select name="servico" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600" onchange="atualizaValor(this)">
            <option value="" disabled selected>Selecione o serviço</option>
            <?php
            $servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome")->fetchAll();
            foreach($servicos as $s): ?>
              <option value="<?= $s['id'] ?>" data-valor="<?= $s['valor'] ?>">
                <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['valor'],2,',','.') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Valor -->
        <div>
          <label class="block text-sm font-medium mb-1">Valor (R$)</label>
          <input type="text" name="valor" id="valor" required value="0,00" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
        </div>

        <!-- Data -->
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Data</label>
          <input type="date" name="data" required value="<?= date('Y-m-d') ?>" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-600">
        </div>

        <div class="md:col-span-2">
          <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-4 px-6 rounded-lg transition">
            Registrar Atendimento
          </button>
        </div>
      </form>
    </div>

    <!-- Resumo da Semana -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 text-center">
        <div class="text-4xl mb-2">R$</div>
        <h3 class="text-xl font-bold">Total Faturado</h3>
        <p class="text-3xl font-black text-amber-500 mt-3">
          <?= number_format($totalFaturado, 2, ',', '.') ?>
        </p>
      </div>

      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 text-center">
        <div class="text-4xl mb-2">%</div>
        <h3 class="text-xl font-bold">Total Comissões (50%)</h3>
        <p class="text-3xl font-black text-green-500 mt-3">
          <?= number_format($totalComissao, 2, ',', '.') ?>
        </p>
      </div>

      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 text-center">
        <div class="text-4xl mb-2">👤</div>
        <h3 class="text-xl font-bold">Atendimentos</h3>
        <p class="text-3xl font-black text-blue-400 mt-3"><?= $totalAtendimentos ?></p>
      </div>
    </div>

    <!-- Por Barbeiro -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
      <?php foreach($porBarbeiro as $bar): ?>
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-2xl font-bold mb-4"><?= htmlspecialchars($bar['nome']) ?></h3>
          <div class="space-y-2">
            <p><strong>Atendimentos:</strong> <?= $bar['qtd'] ?? 0 ?></p>
            <p><strong>Total Faturado:</strong> <span class="font-bold">R$ <?= number_format($bar['total'] ?? 0, 2, ',', '.') ?></span></p>
            <p><strong>Comissão (50%):</strong> <span class="text-green-400 font-bold">R$ <?= number_format(($bar['total'] ?? 0) * 0.5, 2, ',', '.') ?></span></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Lista de Atendimentos -->
    <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
      <div class="bg-gray-700 px-6 py-4">
        <h2 class="text-xl font-bold">Atendimentos da Semana</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="bg-gray-900">
            <tr>
              <th class="px-6 py-4">Data</th>
              <th class="px-6 py-4">Barbeiro</th>
              <th class="px-6 py-4">Cliente</th>
              <th class="px-6 py-4">Serviço</th>
              <th class="px-6 py-4">Valor</th>
              <th class="px-6 py-4 w-32">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <?php if (empty($atendimentos)): ?>
              <tr>
                <td colspan="6" class="px-6 py-10 text-center text-gray-500">Nenhum atendimento nesta semana</td>
              </tr>
            <?php else: ?>
              <?php foreach($atendimentos as $at): ?>
                <tr class="hover:bg-gray-700/50 transition">
                  <td class="px-6 py-4"><?= date('d/m/Y', strtotime($at['data_atendimento'])) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($at['barbeiro']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($at['cliente_nome']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($at['servico']) ?></td>
                  <td class="px-6 py-4 font-medium">R$ <?= number_format($at['valor'], 2, ',', '.') ?></td>
                  <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <a href="editar.php?id=<?= $at['id'] ?>" class="text-amber-300 hover:text-amber-100">✏️</a>
                      <form action="index.php" method="POST" onsubmit="return confirm('Deseja excluir este atendimento?');" class="inline">
                        <input type="hidden" name="action" value="delete_atendimento">
                        <input type="hidden" name="atendimento_id" value="<?= $at['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-300">🗑</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <script>
    function atualizaValor(select) {
      const opt = select.options[select.selectedIndex];
      const valor = opt.dataset.valor || '0.00';
      document.getElementById('valor').value = Number(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }
  </script>

</body>
</html> 