<?php
session_start();
echo "FUNCIONÁRIO LOGADO - Store ID: " . ($_SESSION['store_id'] ?? 'NULL');
echo "<br>Tipo: " . ($_SESSION['user_type'] ?? 'NULL');
echo "<br><a href='/store/dashboard/'>Tentar dashboard normal</a>";
?>