<?php
// config.php (inclua este arquivo se já tiver)
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filtro padrão: últimos 6 meses
    $data_fim = date('Y-m-d');
    $data_inicio = date('Y-m-d', strtotime('-6 months'));

    // Se usuário enviar filtro
    if (isset($_POST['data_inicio'])) {
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'] ?? $data_fim;
    }

    // Query para somar gastos por mês
    $sql = "SELECT 
                DATE_FORMAT(DREALGASTO, '%Y-%m') AS mes,
                SUM(NVALOGASTO) AS total
            FROM gastos_nubank
            WHERE DREALGASTO BETWEEN :data_inicio AND :data_fim
              AND IGNORA_GASTO = 'N'
            GROUP BY mes
            ORDER BY mes";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':data_inicio' => $data_inicio, ':data_fim' => $data_fim]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar dados para o gráfico
    $labels = [];
    $valores = [];

    foreach ($dados as $item) {
        $labels[] = date('M/Y', strtotime($item['mes'] . '-01'));
        $valores[] = floatval($item['total']);
    }

} catch(PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Gastos Mensais</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filtro { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
        .grafico-container { width: 80%; margin: 0 auto; }
    </style>
</head>
<body>
    <h1>📆 Relatório de Gastos Mensais</h1>
    
    <!-- Filtro por Data -->
    <div class="filtro">
        <form method="post">
            <label for="data_inicio">De:</label>
            <input type="date" name="data_inicio" value="<?= $data_inicio ?>" required>
            
            <label for="data_fim">Até:</label>
            <input type="date" name="data_fim" value="<?= $data_fim ?>" required>
            
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <!-- Gráfico -->
    <div class="grafico-container">
        <canvas id="graficoGastos"></canvas>
    </div>

    <script>
        // Dados do PHP para JS
        const ctx = document.getElementById('graficoGastos').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Gastos por Mês (R$)',
                    data: <?= json_encode($valores) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Valor (R$)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mês/Ano'
                        }
                    }
                }
            }
        });
    </script>

    <!-- Tabela com dados brutos (opcional) -->
    <h2>📊 Dados Detalhados</h2>
    <table border="1">
        <tr>
            <th>Mês/Ano</th>
            <th>Total Gasto (R$)</th>
        </tr>
        <?php foreach ($dados as $linha): ?>
        <tr>
            <td><?= date('m/Y', strtotime($linha['mes'] . '-01')) ?></td>
            <td><?= number_format($linha['total'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>