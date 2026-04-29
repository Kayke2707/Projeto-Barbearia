<?php
session_start();
require_once 'db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$adminJsonPath = __DIR__ . '/admin.json';
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

if ($adminCount === 0 && file_exists($adminJsonPath)) {
    $adminData = json_decode(file_get_contents($adminJsonPath), true);
    if (is_array($adminData) && !empty($adminData['username']) && !empty($adminData['password'])) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
        $stmt->execute([$adminData['username'], $adminData['password']]);
        $adminCount = 1;
    }
}

$setupMode = ($adminCount === 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($setupMode && isset($_POST['setup'])) {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');

        if ($newUsername === '' || $newPassword === '') {
            $error = 'Informe usuário e senha para criar o admin.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $stmt->execute([$newUsername, password_hash($newPassword, PASSWORD_DEFAULT)]);
            $_SESSION['admin'] = true;
            header('Location: admin.php?sucesso=' . urlencode('Login do admin criado com sucesso.'));
            exit;
        }
    } elseif (isset($_POST['login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->query('SELECT * FROM admins LIMIT 1');
        $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminRow && $username === $adminRow['username'] && password_verify($password, $adminRow['password'])) {
            $_SESSION['admin'] = true;
            header('Location: admin.php');
            exit;
        }

        $error = 'Usuário ou senha incorretos.';
    } elseif (!empty($_SESSION['admin'])) {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_barbeiro') {
            $nome = trim($_POST['nome'] ?? '');
            if ($nome === '') {
                $error = 'Informe o nome do barbeiro.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO barbeiros (nome) VALUES (?)');
                $stmt->execute([$nome]);
                $success = 'Barbeiro adicionado com sucesso.';
            }
        }

        if ($action === 'add_servico') {
            $nome = trim($_POST['nome_servico'] ?? '');
            $valor = str_replace(',', '.', trim($_POST['valor_servico'] ?? ''));
            if ($nome === '' || $valor === '' || !is_numeric($valor)) {
                $error = 'Informe o nome e valor válidos do serviço.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO servicos (nome, valor) VALUES (?, ?)');
                $stmt->execute([$nome, $valor]);
                $success = 'Serviço adicionado com sucesso.';
            }
        }

        if ($action === 'delete_barbeiro') {
            $barbeiroId = $_POST['barbeiro_id'] ?? null;
            if (!$barbeiroId) {
                $error = 'Barbeiro inválido.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM atendimentos WHERE barbeiro_id = ?');
                $stmt->execute([$barbeiroId]);
                $qtdAtendimentos = $stmt->fetchColumn();

                if ($qtdAtendimentos > 0) {
                    $error = 'Não é possível remover barbeiro com atendimentos registrados. Remova ou altere os atendimentos primeiro.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM barbeiros WHERE id = ?');
                    $stmt->execute([$barbeiroId]);
                    $success = 'Barbeiro removido com sucesso.';
                }
            }
        }

        if ($action === 'update_admin_credentials') {
            $newUsername = trim($_POST['new_username'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');

            if ($newUsername === '') {
                $error = 'Informe um usuário válido para o admin.';
            } else {
                if ($newPassword !== '') {
                    $stmt = $pdo->prepare('UPDATE admins SET username = ?, password = ? WHERE id = (SELECT id FROM (SELECT id FROM admins LIMIT 1) AS temp)');
                    $stmt->execute([$newUsername, password_hash($newPassword, PASSWORD_DEFAULT)]);
                } else {
                    $stmt = $pdo->prepare('UPDATE admins SET username = ? WHERE id = (SELECT id FROM (SELECT id FROM admins LIMIT 1) AS temp)');
                    $stmt->execute([$newUsername]);
                }
                $success = 'Login do admin atualizado com sucesso.';
            }
        }

        if ($action === 'update_servico') {
            $servicoId = $_POST['servico_id'] ?? null;
            $nome = trim($_POST['nome_servico'] ?? '');
            $valor = str_replace(',', '.', trim($_POST['valor_servico'] ?? ''));
            if (!$servicoId || $nome === '' || $valor === '' || !is_numeric($valor)) {
                $error = 'Informe um nome e valor válidos para o serviço.';
            } else {
                $stmt = $pdo->prepare('UPDATE servicos SET nome = ?, valor = ? WHERE id = ?');
                $stmt->execute([$nome, $valor, $servicoId]);
                $success = 'Serviço atualizado com sucesso.';
            }
        }

        if ($action === 'delete_servico') {
            $servicoId = $_POST['servico_id'] ?? null;
            if (!$servicoId) {
                $error = 'Serviço inválido.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM atendimentos WHERE servico_id = ?');
                $stmt->execute([$servicoId]);
                $qtdAtendimentos = $stmt->fetchColumn();

                if ($qtdAtendimentos > 0) {
                    $error = 'Não é possível remover serviço com atendimentos registrados. Remova ou altere os atendimentos primeiro.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM servicos WHERE id = ?');
                    $stmt->execute([$servicoId]);
                    $success = 'Serviço removido com sucesso.';
                }
            }
        }

        if ($action === 'add_info') {
            $success = 'Você pode adicionar mais funcionalidades aqui conforme precisar.';
        }
    }
}

$adminRow = null;
if (!$setupMode) {
    $stmt = $pdo->query('SELECT * FROM admins LIMIT 1');
    $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$barbeiros = [];
$servicos = [];
if (!empty($_SESSION['admin'])) {
    $barbeiros = $pdo->query('SELECT * FROM barbeiros ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    $servicos = $pdo->query('SELECT * FROM servicos ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="bg-gray-900 text-gray-100">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Barbearia</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-950">
  <main class="max-w-5xl mx-auto px-4 py-10">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold">Área do Admin</h1>
          <p class="text-sm text-gray-400">Adicione barbeiros, altere preços e gerencie serviços.</p>
        </div>
        <a href="index.php" class="text-amber-400 hover:underline">Voltar ao Painel</a>
      </div>

      <?php if (!empty($error)): ?>
        <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="bg-green-900/50 border border-green-700 text-green-300 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if (empty($_SESSION['admin'])): ?>
        <?php if ($setupMode): ?>
          <form action="admin.php" method="POST" class="grid gap-6">
            <input type="hidden" name="setup" value="1" />
            <div>
              <label class="block text-sm font-medium mb-1">Escolha um usuário</label>
              <input type="text" name="new_username" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Escolha uma senha</label>
              <input type="password" name="new_password" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3" />
            </div>
            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-3 font-bold">Criar login do Admin</button>
          </form>
          <p class="mt-4 text-sm text-gray-400">Este é o primeiro acesso. Crie seu usuário e senha de administrador.</p>
        <?php else: ?>
          <form action="admin.php" method="POST" class="grid gap-6">
            <input type="hidden" name="login" value="1" />
            <div>
              <label class="block text-sm font-medium mb-1">Usuário</label>
              <input type="text" name="username" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Senha</label>
              <input type="password" name="password" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3" />
            </div>
            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-3 font-bold">Entrar como Admin</button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="flex items-center justify-between mb-6 gap-4">
          <div>
            <p class="text-sm text-gray-400">Você está conectado como administrador.</p>
          </div>
          <a href="logout.php" class="text-red-400 hover:underline">Sair</a>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Adicionar Barbeiro</h2>
            <form action="admin.php" method="POST" class="space-y-4">
              <input type="hidden" name="action" value="add_barbeiro" />
              <div>
                <label class="block text-sm font-medium mb-1">Nome do Barbeiro</label>
                <input type="text" name="nome" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3" />
              </div>
              <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-3 font-bold">Salvar Barbeiro</button>
            </form>
          </div>

          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Adicionar Serviço</h2>
            <form action="admin.php" method="POST" class="space-y-4">
              <input type="hidden" name="action" value="add_servico" />
              <div>
                <label class="block text-sm font-medium mb-1">Nome do Serviço</label>
                <input type="text" name="nome_servico" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Valor (R$)</label>
                <input type="text" name="valor_servico" required placeholder="Ex: 45,00" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3" />
              </div>
              <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-3 font-bold">Salvar Serviço</button>
            </form>
          </div>

          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Login do Admin</h2>
            <form action="admin.php" method="POST" class="space-y-4">
              <input type="hidden" name="action" value="update_admin_credentials" />
              <div>
                <label class="block text-sm font-medium mb-1">Novo usuário</label>
                <input type="text" name="new_username" value="<?= htmlspecialchars($adminRow['username'] ?? '') ?>" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Nova senha</label>
                <input type="password" name="new_password" placeholder="Deixe em branco para manter a atual" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3" />
              </div>
              <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-3 font-bold">Salvar login do Admin</button>
            </form>
          </div>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Barbeiros existentes</h2>
            <?php if (empty($barbeiros)): ?>
              <p class="text-gray-400">Nenhum barbeiro cadastrado ainda.</p>
            <?php else: ?>
              <div class="space-y-4">
                <?php foreach ($barbeiros as $barbeiro): ?>
                  <form action="admin.php" method="POST" class="bg-gray-800 rounded-xl border border-gray-700 p-4 flex items-center justify-between gap-4">
                    <div>
                      <p class="font-semibold"><?= htmlspecialchars($barbeiro['nome']) ?></p>
                      <p class="text-sm text-gray-400">ID: <?= htmlspecialchars($barbeiro['id']) ?></p>
                    </div>
                    <input type="hidden" name="action" value="delete_barbeiro" />
                    <input type="hidden" name="barbeiro_id" value="<?= htmlspecialchars($barbeiro['id']) ?>" />
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 font-bold">Excluir</button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Serviços existentes</h2>
            <?php if (empty($servicos)): ?>
              <p class="text-gray-400">Nenhum serviço cadastrado ainda.</p>
            <?php else: ?>
              <div class="space-y-4">
                <?php foreach ($servicos as $servico): ?>
                  <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
                    <form action="admin.php" method="POST" class="space-y-4">
                      <input type="hidden" name="action" value="update_servico" />
                      <input type="hidden" name="servico_id" value="<?= htmlspecialchars($servico['id']) ?>" />

                      <div>
                        <label class="block text-sm font-medium mb-1">Nome do Serviço</label>
                        <input type="text" name="nome_servico" value="<?= htmlspecialchars($servico['nome']) ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2" />
                      </div>

                      <div>
                        <label class="block text-sm font-medium mb-1">Valor</label>
                        <input type="text" name="valor_servico" value="<?= number_format($servico['valor'], 2, ',', '.') ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2" />
                      </div>

                      <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded-lg py-2 font-bold">Salvar Serviço</button>
                    </form>
                    <form action="admin.php" method="POST" class="mt-3">
                      <input type="hidden" name="action" value="delete_servico" />
                      <input type="hidden" name="servico_id" value="<?= htmlspecialchars($servico['id']) ?>" />
                      <button type="submit" onclick="return confirm('Deseja excluir este serviço?');" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg py-2 font-bold">Excluir Serviço</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="bg-gray-900 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Informações e lembretes</h2>
            <p class="text-gray-400 mb-4">Aqui o administrador pode cadastrar barbeiros, cadastrar e atualizar preços de serviços e estender o sistema conforme necessário.</p>
            <p class="text-gray-400">As comissões são apuradas de segunda a sábado e, no domingo, o sistema zera para iniciar a nova semana.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
