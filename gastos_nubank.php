<?php
// Conexão com o banco
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filtro por data
    $filtros = ["IGNORA_GASTO = 'N'"]; // Sempre ignora os com 'S'

    if (isset($_POST['data_inicio'])) {
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'] ?? $data_inicio;
        $filtros[] = "DREALGASTO BETWEEN '$data_inicio' AND '$data_fim'";
    }

    // Monta cláusula WHERE
    $where = "";
    if (!empty($filtros)) {
        $where = "WHERE " . implode(" AND ", $filtros);
    }

    // Query principal
    $sql = "SELECT * 
              FROM gastos_nubank 
            $where ORDER BY DREALGASTO DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Controle de Gastos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>📊 Controle de Gastos</h1>
    
    <!-- Filtro por data -->
    <form method="POST" class="filtro">
        <label for="data_inicio">Data Início:</label>
        <input type="date" name="data_inicio" id="data_inicio">
        <label for="data_fim">Data Fim:</label>
        <input type="date" name="data_fim" id="data_fim">
        <button type="submit">Filtrar</button>
    </form>

    <!-- Formulário de adição -->
    <form method="POST" action="adicionar_gasto.php" class="form-gasto">
        <h2>➕ Adicionar Gasto</h2>
        <input type="date" name="data" required>
        <input type="text" name="descricao" placeholder="Descrição" required>
        <input type="number" step="0.01" name="valor" placeholder="Valor" required>
        <input type="text" name="categoria" placeholder="Categoria">
        <input type="text" name="pessoa" placeholder="pessoa" required>
        <button type="submit">Salvar</button>
    </form>

    <!-- Tabela de gastos -->
    <table>
        <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Descrição</th>
            <th>Valor</th>
            <th>Categoria</th>
            <th>Pessoa</th>
            <th>Ações</thr>
        </tr>
        <?php foreach ($resultados as $row): ?>
        <tr>
            <td><?= $row['NNUMEGASTO'] ?></td>
            <td><?= $row['DREALGASTO'] ?></td>
            <td><?= $row['CDESCGASTO'] ?></td>
            <td>R$ <?= number_format($row['NVALOGASTO'], 2, ',', '.') ?></td>
            <td><?= $row['CCATEGASTO'] ?></td>
            <td><?= $row['NNUMEPESS'] ?></td>
            <td class="acoes">
                <a href="editar_gasto.php?id=<?= $row['NNUMEGASTO'] ?>" class="btn-editar">Alterar ✏️</a>
                <a href="deletar_gasto.php?id=<?= $row['NNUMEGASTO'] ?>" class="btn-deletar" onclick="return confirm('Você tem certeza que vai excluir esse gasto?')">Excluir ❌</a>
            </td>
            
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- Gráfico -->
    <h2>📈 Gastos por Categoria</h2>
    <canvas id="grafico"></canvas>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const dados = <?= json_encode($resultados) ?>;
        const categorias = [...new Set(dados.map(item => item.CCATEGASTO))];
        const totais = categorias.map(cat => {
            return dados.filter(item => item.CCATEGASTO === cat)
                       .reduce((sum, item) => sum + parseFloat(item.NVALOGASTO), 0);
        });

        new Chart(document.getElementById('grafico'), {
            type: 'pie',
            data: {
                labels: categorias,
                datasets: [{
                    data: totais,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            }
        });
    </script>
</body>
</html>
<?php
} catch(PDOException $e) {
    echo "<h2>Erro: " . $e->getMessage() . "</h2>";
}
?>
