<?php
$host = 'localhost';
$dbname = 'GASTO';
$username = 'joao';
$password = 'joao';

if (!isset($_GET['id'])) {
    header("Location: gastos_nubank.php");
    exit();
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se o gasto existe e não está ignorado
    $stmt = $conn->prepare("SELECT * FROM gastos_nubank WHERE NNUMEGASTO = ? AND IGNORA_GASTO = 'N'");
    $stmt->execute([$_GET['id']]);
    $gasto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gasto) {
        header("Location: gastos_pessoa.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Gasto</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>✏️ Editar Gasto</h1>
    <form method="POST" action="atualizar_gasto.php">
        <input type="hidden" name="id" value="<?= $gasto['NNUMEGASTO'] ?>">
        <input type="date" name="data" value="<?= $gasto['DREALGASTO'] ?>" required>
        <input type="text" name="descricao" value="<?= $gasto['CDESCGASTO'] ?>" required>
        <input type="number" step="0.01" name="valor" value="<?= $gasto['NVALOGASTO'] ?>" required>
        <input type="text" name="categoria" value="<?= $gasto['CCATEGASTO'] ?>">
        <button type="submit">Atualizar</button>
    </form>
</body>
</html>
<?php
} catch(PDOException $e) {
    echo "<h2>Erro: " . $e->getMessage() . "</h2>";
}
?>
