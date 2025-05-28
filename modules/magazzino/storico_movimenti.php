<?php
// Configurazione database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "magazzino";

// Connessione al database
$conn = new mysqli($servername, $username, $password, $dbname);

// Controllo connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Gestione filtri
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_prodotto = isset($_GET['prodotto']) ? $_GET['prodotto'] : '';
$filtro_data_da = isset($_GET['data_da']) ? $_GET['data_da'] : '';
$filtro_data_a = isset($_GET['data_a']) ? $_GET['data_a'] : '';

// Query base con JOIN completo
$sql = "SELECT 
    m.id,
    m.data_movimento,
    m.tipo,
    m.quantita,
    m.note,
    p.codice as codice_prodotto,
    p.nome as nome_prodotto,
    p.id as prodotto_id
FROM movimenti m
JOIN lotti l ON m.lotto_id = l.id
JOIN prodotti p ON l.prodotto_id = p.id
WHERE 1=1";

$params = array();
$types = "";

// Applicazione filtri
if (!empty($filtro_tipo)) {
    $sql .= " AND m.tipo = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
}

if (!empty($filtro_prodotto)) {
    $sql .= " AND p.id = ?";
    $params[] = $filtro_prodotto;
    $types .= "i";
}

if (!empty($filtro_data_da)) {
    $sql .= " AND m.data_movimento >= ?";
    $params[] = $filtro_data_da;
    $types .= "s";
}

if (!empty($filtro_data_a)) {
    $sql .= " AND m.data_movimento <= ?";
    $params[] = $filtro_data_a;
    $types .= "s";
}

$sql .= " ORDER BY m.data_movimento DESC, m.id DESC";

// Preparazione ed esecuzione query
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Query per ottenere lista prodotti per il filtro
$sql_prodotti = "SELECT id, nome FROM prodotti ORDER BY nome";
$result_prodotti = $conn->query($sql_prodotti);

// Calcolo statistiche
$sql_stats = "SELECT 
    SUM(CASE WHEN m.tipo = 'entrata' THEN m.quantita ELSE 0 END) as totale_entrate,
    SUM(CASE WHEN m.tipo = 'uscita' THEN m.quantita ELSE 0 END) as totale_uscite,
    COUNT(*) as totale_movimenti
FROM movimenti m
JOIN lotti l ON m.lotto_id = l.id
JOIN prodotti p ON l.prodotto_id = p.id
WHERE 1=1";

$stats_params = array();
$stats_types = "";

// Applicazione stessi filtri per le statistiche
if (!empty($filtro_tipo)) {
    $sql_stats .= " AND m.tipo = ?";
    $stats_params[] = $filtro_tipo;
    $stats_types .= "s";
}

if (!empty($filtro_prodotto)) {
    $sql_stats .= " AND p.id = ?";
    $stats_params[] = $filtro_prodotto;
    $stats_types .= "i";
}

if (!empty($filtro_data_da)) {
    $sql_stats .= " AND m.data_movimento >= ?";
    $stats_params[] = $filtro_data_da;
    $stats_types .= "s";
}

if (!empty($filtro_data_a)) {
    $sql_stats .= " AND m.data_movimento <= ?";
    $stats_params[] = $filtro_data_a;
    $stats_types .= "s";
}

$stmt_stats = $conn->prepare($sql_stats);

if (!empty($stats_params)) {
    $stmt_stats->bind_param($stats_types, ...$stats_params);
}

$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storico Movimenti - Gestionale Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-primary text-white p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-history"></i> Storico Movimenti</h1>
                <p class="mb-0">Visualizza e filtra tutti i movimenti di magazzino</p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Storico Movimenti</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">Entrate</h5>
                        <h3><?php echo $stats['totale_entrate'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger">Uscite</h5>
                        <h3><?php echo $stats['totale_uscite'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">Movimenti</h5>
                        <h3><?php echo $stats['totale_movimenti'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">Saldo</h5>
                        <h3><?php echo ($stats['totale_entrate'] ?? 0) - ($stats['totale_uscite'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filtri</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="tipo" class="form-label">Tipo Movimento</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Tutti</option>
                                <option value="entrata" <?php echo $filtro_tipo == 'entrata' ? 'selected' : ''; ?>>Entrata</option>
                                <option value="uscita" <?php echo $filtro_tipo == 'uscita' ? 'selected' : ''; ?>>Uscita</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="prodotto" class="form-label">Prodotto</label>
                            <select class="form-select" id="prodotto" name="prodotto">
                                <option value="">Tutti i prodotti</option>
                                <?php while($prodotto = $result_prodotti->fetch_assoc()): ?>
                                    <option value="<?php echo $prodotto['id']; ?>" 
                                            <?php echo $filtro_prodotto == $prodotto['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prodotto['nome']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="data_da" class="form-label">Data Da</label>
                            <input type="date" class="form-control" id="data_da" name="data_da" value="<?php echo htmlspecialchars($filtro_data_da); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="data_a" class="form-label">Data A</label>
                            <input type="date" class="form-control" id="data_a" name="data_a" value="<?php echo htmlspecialchars($filtro_data_a); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtra
                                </button>
                                <a href="storico_movimenti.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella movimenti -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Movimenti</h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Prodotto</th>
                                    <th>Codice</th>
                                    <th>Quantità</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($row['data_movimento'])); ?></td>
                                        <td>
                                            <?php if($row['tipo'] == 'entrata'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-arrow-up"></i> Entrata
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-arrow-down"></i> Uscita
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['nome_prodotto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['codice_prodotto']); ?></td>
                                        <td><strong><?php echo $row['quantita']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['note']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nessun movimento trovato con i filtri selezionati.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Azioni -->
        <div class="row mt-4">
            <div class="col">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                </a>
                <a href="movimento.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Movimento
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>