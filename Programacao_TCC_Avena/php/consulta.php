<?php
include('conexao.php');

$sql = "SELECT * FROM cadastro";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Telefone</th><th>Ações</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['nome'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['telefone'] . "</td>";
        echo "<td><a href='php/excluir.php?id=" . $row['id'] . "'>Excluir</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Nenhum registro encontrado.";
}

$mysqli->close();
?>
