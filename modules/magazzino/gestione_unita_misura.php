<?php
// Configurazione database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "magazzino";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

$messaggio = '';
$tipo_messaggio = '';

// Gestione form inserimento/modifica
if ($_POST) {
    $azione = $_POST['azione'] ?? 'inserisci';
    $um_id = isset($_POST['um_id']) ? intval($_POST['um_id']) : 0;
    $codice = strtoupper(trim($_POST['codice']));
    $nome = trim($_POST['nome']);
    $tipologia = $_POST['tipologia'];
    
    if (empty($codice) || empty($nome) || empty($tipologia)) {
        $messaggio = 'Compila tutti i campi obbligatori!';
        $tipo_messaggio = 'danger';
    } else {
        try {
            if ($azione == 'inserisci') {
                // Controllo unicità codice
                $check_sql = "SELECT COUNT(*) as count FROM unita_misura WHERE codice = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $codice);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_data = $check_result->fetch_assoc();
                
                if ($check_data['count'] > 0) {
                    throw new Exception("Codice unità di misura già esistente!");
                }
                
                $sql = "INSERT INTO unita_misura (codice, nome, tipologia) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $codice, $nome, $tipologia);
                
                if ($stmt->execute()) {
                    $messaggio = "Unità di misura <strong>$codice</strong> creata con successo!";
                    $tipo_messaggio = 'success';
                    $_POST = array();
                }
            } else {
                // Modifica
                $sql = "UPDATE unita_misura SET nome = ?, tipologia = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $nome, $tipologia, $um_id);
                
                if ($stmt->execute()) {
                    $messaggio = "Unità di misura aggiornata con successo!";
                    $tipo_messaggio = 'success';
                }
            }
        } catch (Exception $e) {
            $messaggio = 'Errore: ' . $e->getMessage();
            $tipo_messaggio = 'danger';
        }
    }
}

// Gestione eliminazione
if (isset($_GET['elimina'])) {
    $um_id = intval($_GET['elimina']);
    try {
        // Controllo se è utilizzata in prodotti
        $check_uso_sql = "SELECT COUNT(*) as count FROM prodotti WHERE 
                          unita_misura_principale = (SELECT codice FROM unita_misura WHERE id = ?) OR
                          unita_misura_secondaria_1 = (SELECT codice FROM unita_misura WHERE id = ?) OR
                          unita_misura_secondaria_2 = (SELECT codice FROM unita_misura WHERE id = ?) OR
                          unita_misura_secondaria_3 = (SELECT codice FROM unita_misura WHERE id = ?)";
        $check_uso_stmt = $conn->prepare($check_uso_sql);
        $check_uso_stmt->bind_param("iiii", $um_id, $um_id, $um_id, $um_id);
        $check_uso_stmt->execute();
        $check_uso_result = $check_uso_stmt->get_result();
        $check_uso_data = $check_uso_result->fetch_assoc();
        
        if ($check_uso_data['count'] > 0) {
            throw new Exception("Impossibile eliminare: unità di misura utilizzata in " . $check_uso_data['count'] . " prodotti!");
        }
        
        $sql_delete = "DELETE FROM unita_misura WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $um_id);
        
        if ($stmt_delete->execute()) {
            $messaggio = 'Unità di misura eliminata con successo!';
            $tipo_messaggio = 'success';
        }
    } catch (Exception $e) {
        $messaggio = 'Errore eliminazione: ' . $e->getMessage();
        $tipo_messaggio = 'danger';
    }
}

// Recupera dati per modifica
$um_modifica = null;
if (isset($_GET['modifica'])) {
    $um_id = intval($_GET['modifica']);
    $sql_modifica = "SELECT * FROM unita_misura WHERE id = ?";
    $stmt_modifica = $conn->prepare($sql_modifica);
    $stmt_modifica->bind_param("i", $um_id);
    $stmt_modifica->execute();
    $result_modifica = $stmt_modifica->get_result();
    $um_modifica = $result_modifica->fetch_assoc();
}

// Query lista unità di misura
$sql_unita = "SELECT um.*, 
              (SELECT COUNT(*) FROM prodotti p WHERE 
               p.unita_misura_principale = um.codice OR
               p.unita_misura_secondaria_1 = um.codice OR
               p.unita_misura_secondaria_2 = um.codice OR
               p.unita_misura_secondaria_3 = um.codice) as utilizzi
              FROM unita_misura um
              ORDER BY um.tipologia, um.codice";
$result_unita = $conn->query($sql_unita);

// Statistiche
$sql_stats = "SELECT 
              COUNT(*) as totale_unita,
              COUNT(DISTINCT tipologia) as tipologie_diverse,
              (SELECT COUNT(*) FROM prodotti WHERE unita_misura_principale IS NOT NULL) as prodotti_configurati
              FROM unita_misura";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Unità di Misura - Gestionale PMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-info text-white p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-ruler-combined"></i> Gestione Unità di Misura</h1>
                <p class="mb-0">Configura le unità di misura base per prodotti e conversioni</p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="anagrafica_lotti.php">Sistema Logistico</a></li>
                        <li class="breadcrumb-item active">Unità di Misura</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Messaggio feedback -->
        <?php if (!empty($messaggio)): ?>
        <div class="alert alert-<?php echo $tipo_messaggio; ?> alert-dismissible fade show" role="alert">
            <?php echo $messaggio; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['totale_unita']; ?></h4>
                        <p class="mb-0">Unità Configurate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['tipologie_diverse']; ?></h4>
                        <p class="mb-0">Tipologie Diverse</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['prodotti_configurati']; ?></h4>
                        <p class="mb-0">Prodotti Configurati</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Form Inserimento/Modifica -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-plus"></i> 
                            <?php echo $um_modifica ? 'Modifica Unità di Misura' : 'Nuova Unità di Misura'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($um_modifica): ?>
                                <input type="hidden" name="azione" value="modifica">
                                <input type="hidden" name="um_id" value="<?php echo $um_modifica['id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Codice</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($um_modifica['codice']); ?>" readonly>
                                    <div class="form-text">Il codice non può essere modificato</div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="azione" value="inserisci">
                                
                                <div class="mb-3">
                                    <label for="codice" class="form-label">
                                        Codice <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="codice" 
                                           name="codice" 
                                           placeholder="es. MC, KG, PZ"
                                           maxlength="10"
                                           style="text-transform: uppercase;"
                                           value="<?php echo isset($_POST['codice']) ? htmlspecialchars($_POST['codice']) : ''; ?>"
                                           required>
                                    <div class="form-text">Max 10 caratteri, sarà convertito in maiuscolo</div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    Nome Completo <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nome" 
                                       name="nome" 
                                       placeholder="es. Metro Cubo, Chilogrammo"
                                       value="<?php echo $um_modifica ? htmlspecialchars($um_modifica['nome']) : (isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''); ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="tipologia" class="form-label">
                                    Tipologia <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="tipologia" name="tipologia" required>
                                    <option value="">Seleziona tipologia...</option>
                                    <option value="volume" <?php echo ($um_modifica && $um_modifica['tipologia'] == 'volume') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'volume') ? 'selected' : ''; ?>>
                                        📦 Volume (MC, LT, etc.)
                                    </option>
                                    <option value="peso" <?php echo ($um_modifica && $um_modifica['tipologia'] == 'peso') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'peso') ? 'selected' : ''; ?>>
                                        ⚖️ Peso (KG, T, etc.)  
                                    </option>
                                    <option value="lunghezza" <?php echo ($um_modifica && $um_modifica['tipologia'] == 'lunghezza') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'lunghezza') ? 'selected' : ''; ?>>
                                        📏 Lunghezza (MT, ML, etc.)
                                    </option>
                                    <option value="superficie" <?php echo ($um_modifica && $um_modifica['tipologia'] == 'superficie') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'superficie') ? 'selected' : ''; ?>>
                                        📐 Superficie (MQ, etc.)
                                    </option>
                                    <option value="quantita" <?php echo ($um_modifica && $um_modifica['tipologia'] == 'quantita') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'quantita') ? 'selected' : ''; ?>>
                                        🔢 Quantità (PZ, CF, SC, etc.)
                                    </option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $um_modifica ? 'Aggiorna Unità' : 'Crea Unità'; ?>
                                </button>
                                <?php if ($um_modifica): ?>
                                <a href="gestione_unita_misura.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annulla Modifica
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Esempi comuni -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-lightbulb"></i> Esempi Comuni</h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <strong>Volume:</strong> MC, LT, HL<br>
                            <strong>Peso:</strong> KG, T, G<br>
                            <strong>Lunghezza:</strong> MT, ML, CM<br>
                            <strong>Superficie:</strong> MQ, HA<br>
                            <strong>Quantità:</strong> PZ, CF, SC, KIT
                        </small>
                    </div>
                </div>
            </div>

            <!-- Lista Unità di Misura -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Unità di Misura Configurate</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result_unita->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Codice</th>
                                        <th>Nome</th>
                                        <th>Tipologia</th>
                                        <th>Utilizzi</th>
                                        <th>Data Creazione</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $tipologia_corrente = '';
                                    while($um = $result_unita->fetch_assoc()): 
                                        if ($tipologia_corrente != $um['tipologia']) {
                                            $tipologia_corrente = $um['tipologia'];
                                            $badge_color = ['volume' => 'primary', 'peso' => 'success', 'lunghezza' => 'warning', 'superficie' => 'info', 'quantita' => 'secondary'][$tipologia_corrente] ?? 'dark';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($um['codice']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($um['nome']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($um['tipologia']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($um['utilizzi'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $um['utilizzi']; ?> prodotti</span>
                                            <?php else: ?>
                                                <span class="text-muted">Non utilizzata</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($um['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?modifica=<?php echo $um['id']; ?>" 
                                                   class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($um['utilizzi'] == 0): ?>
                                                <a href="?elimina=<?php echo $um['id']; ?>" 
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirm('Eliminare <?php echo htmlspecialchars($um['codice']); ?>?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled title="In uso, non eliminabile">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Nessuna unità di misura configurata.
                            <br><br>Inizia creando le unità base come MC, KG, PZ...
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link navigazione -->
        <div class="row mt-4">
            <div class="col text-center">
                <a href="anagrafica_lotti.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Torna ai Lotti
                </a>
                <a href="gestione_fornitori.php" class="btn btn-success me-2">
                    <i class="fas fa-truck"></i> Gestione Fornitori
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Dashboard
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