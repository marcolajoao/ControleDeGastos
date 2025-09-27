<?php
// Conexão com o banco
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filtro por data e pessoa
    $filtros = ["g.IGNORA_GASTO = 'N'"]; // Sempre ignora os com 'S'

    // Filtro por data (só aplica se as datas não estiverem vazias)
    if (isset($_POST['data_inicio']) && !empty($_POST['data_inicio'])) {
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'] ?? $data_inicio;
        
        // Só adiciona o filtro se a data fim também não estiver vazia
        if (!empty($data_fim)) {
            $filtros[] = "g.DREALGASTO BETWEEN '$data_inicio' AND '$data_fim'";
        } else {
            $filtros[] = "g.DREALGASTO >= '$data_inicio'";
        }
    }

    // Filtro por pessoa (usa o ID NNUMEPESS internamente)
    if (isset($_POST['pessoa']) && !empty($_POST['pessoa'])) {
        $pessoa_id = $_POST['pessoa'];
        $filtros[] = "g.NNUMEPESS = '$pessoa_id'";
    }

    // Monta cláusula WHERE
    $where = "";
    if (!empty($filtros)) {
        $where = "WHERE " . implode(" AND ", $filtros);
    }

    // Query principal com JOIN para pegar o nome da pessoa da tabela HSSPESS
    $sql = "SELECT g.*, p.CNOMEPESS 
            FROM gastos_nubank g 
            LEFT JOIN HSSPESS p ON g.NNUMEPESS = p.NNUMEPESS 
            $where 
            ORDER BY g.DREALGASTO DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar pessoas disponíveis para o select da tabela HSSPESS
    $sql_pessoas = "SELECT NNUMEPESS, CNOMEPESS FROM HSSPESS ORDER BY CNOMEPESS";
    $stmt_pessoas = $conn->prepare($sql_pessoas);
    $stmt_pessoas->execute();
    $pessoas = $stmt_pessoas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Controle de Gastos</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filtro {
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .filtro label {
            margin-right: 10px;
        }
        .filtro input, .filtro select {
            margin-right: 15px;
            padding: 5px;
        }
        .form-gasto {
            margin-bottom: 20px;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 5px;
        }
        .form-gasto input, .form-gasto select {
            margin-right: 10px;
            padding: 5px;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .acoes a {
            margin-right: 10px;
            text-decoration: none;
        }
        .btn-editar {
            color: blue;
        }
        .btn-deletar {
            color: red;
        }
    </style>
</head>
<body>
    <h1>📊 Controle de Gastos</h1>
    
    <!-- Filtro por data e pessoa -->
    <form method="POST" class="filtro">
        <label for="data_inicio">Data Início:</label>
        <input type="date" name="data_inicio" id="data_inicio" value="<?= $_POST['data_inicio'] ?? '' ?>">
        
        <label for="data_fim">Data Fim:</label>
        <input type="date" name="data_fim" id="data_fim" value="<?= $_POST['data_fim'] ?? '' ?>">
        
        <label for="pessoa">Pessoa:</label>
        <select name="pessoa" id="pessoa">
            <option value="">Todas as pessoas</option>
            <?php foreach ($pessoas as $p): ?>
                <option value="<?= $p['NNUMEPESS'] ?>" 
                    <?= (isset($_POST['pessoa']) && $_POST['pessoa'] == $p['NNUMEPESS']) ? 'selected' : '' ?>>
                    <?= $p['CNOMEPESS'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">Filtrar</button>
        <button type="button" onclick="limparFiltros()">Limpar Filtros</button>
    </form>

    <!-- Formulário de adição -->
    <form method="POST" action="adicionar_gasto.php" class="form-gasto">
        <h2>➕ Adicionar Gasto</h2>
        <input type="date" name="data" required>
        <input type="text" name="descricao" placeholder="Descrição" required>
        <input type="number" step="0.01" name="valor" placeholder="Valor" required>
        <input type="text" name="categoria" placeholder="Categoria">
        
        <select name="pessoa" required>
            <option value="">Selecione a pessoa</option>
            <?php foreach ($pessoas as $p): ?>
                <option value="<?= $p['NNUMEPESS'] ?>"><?= $p['CNOMEPESS'] ?></option>
            <?php endforeach; ?>
        </select>
        
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
            <th>Ações</th>
        </tr>
        <?php if (empty($resultados)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">Nenhum gasto encontrado</td>
            </tr>
        <?php else: ?>
            <?php foreach ($resultados as $row): ?>
            <tr>
                <td><?= $row['NNUMEGASTO'] ?></td>
                <td><?= $row['DREALGASTO'] ?></td>
                <td><?= $row['CDESCGASTO'] ?></td>
                <td>R$ <?= number_format($row['NVALOGASTO'], 2, ',', '.') ?></td>
                <td><?= $row['CCATEGASTO'] ?></td>
                <td><?= $row['CNOMEPESS'] ?></td> <!-- Mostra o nome da pessoa -->
                <td class="acoes">
                    <a href="editar_gasto.php?id=<?= $row['NNUMEGASTO'] ?>" class="btn-editar">Alterar ✏️</a>
                    <a href="deletar_gasto.php?id=<?= $row['NNUMEGASTO'] ?>" class="btn-deletar" onclick="return confirm('Você tem certeza que vai excluir esse gasto?')">Excluir ❌</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <!-- Gráfico -->
    <h2>📈 Gastos por Categoria</h2>
    <canvas id="grafico"></canvas>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const dados = <?= json_encode($resultados) ?>;
        
        // Só cria o gráfico se houver dados
        if (dados.length > 0) {
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
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#9966FF'
                        ]
                    }]
                }
            });
        } else {
            document.getElementById('grafico').getContext('2d').fillText('Nenhum dado para exibir', 10, 10);
        }

        function limparFiltros() {
            document.getElementById('data_inicio').value = '';
            document.getElementById('data_fim').value = '';
            document.getElementById('pessoa').value = '';
            document.querySelector('form.filtro').submit();
        }
    </script>
</body>
</html>
<?php
} catch(PDOException $e) {
    echo "<h2>Erro: " . $e->getMessage() . "</h2>";
}