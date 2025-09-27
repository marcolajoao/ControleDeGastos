<?php
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

try {
    // Conexão com o banco
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Filtros
    $filtros = ["IGNORA_GASTO = 'N'"];
    if (isset($_POST['data_inicio'])) {
        $data_fim = $_POST['data_fim'] ?? $_POST['data_inicio'];
        $filtros[] = "DREALGASTO BETWEEN '{$_POST['data_inicio']}' AND '$data_fim'";
    }
    
    // Query e resultados
    $where = $filtros ? "WHERE " . implode(" AND ", $filtros) : "";
    $stmt = $conn->prepare("SELECT * FROM gastos_nubank $where ORDER BY DREALGASTO DESC");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cálculo de totais
    $totalGeral = 0;
    $totaisCategorias = [];
    
    foreach ($resultados as $row) {
        $valor = floatval($row['NVALOGASTO']);
        $totalGeral += $valor;
        $categoria = $row['CCATEGASTO'] ?: 'Sem Categoria';
        $totaisCategorias[$categoria] = ($totaisCategorias[$categoria] ?? 0) + $valor;
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Controle de Gastos</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f0f0f0; }
        .card { 
            background: #f9f9f9; 
            padding: 10px; 
            margin: 5px; 
            display: inline-block; 
            min-width: 150px; 
        }
        .valor { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Controle de Gastos</h1>
    
    <form method="POST">
        <input type="date" name="data_inicio">
        <input type="date" name="data_fim">
        <button type="submit">Filtrar</button>
    </form>

    <div>
        <div class="card">
            <h3>Total Geral</h3>
            <div class="valor">R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
        </div>
        <?php foreach ($totaisCategorias as $cat => $total): ?>
        <div class="card">
            <h3><?= $cat ?></h3>
            <div class="valor">R$ <?= number_format($total, 2, ',', '.') ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Descrição</th>
            <th>Valor</th>
            <th>Categoria</th>
        </tr>
        <?php foreach ($resultados as $row): ?>
        <tr>
            <td><?= $row['NNUMEGASTO'] ?></td>
            <td><?= $row['DREALGASTO'] ?></td>
            <td><?= $row['CDESCGASTO'] ?></td>
            <td>R$ <?= number_format($row['NVALOGASTO'], 2, ',', '.') ?></td>
            <td><?= $row['CCATEGASTO'] ?: 'Sem Categoria' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
<?php } catch(PDOException $e) { 
    echo "<h2>Erro: " . $e->getMessage() . "</h2>"; 
} ?>