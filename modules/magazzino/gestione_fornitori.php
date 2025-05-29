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
    $fornitore_id = isset($_POST['fornitore_id']) ? intval($_POST['fornitore_id']) : 0;
    $nome = trim($_POST['nome']);
    $tipo = $_POST['tipo'];
    $partita_iva = trim($_POST['partita_iva']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $indirizzo = trim($_POST['indirizzo']);
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    
    if (empty($nome) || empty($tipo)) {
        $messaggio = 'Nome e tipo fornitore sono obbligatori!';
        $tipo_messaggio = 'danger';
    } else {
        try {
            if ($azione == 'inserisci') {
                // Controllo unicità nome
                $check_sql = "SELECT COUNT(*) as count FROM fornitori WHERE nome = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $nome);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_data = $check_result->fetch_assoc();
                
                if ($check_data['count'] > 0) {
                    throw new Exception("Nome fornitore già esistente!");
                }
                
                $sql = "INSERT INTO fornitori (nome, tipo, partita_iva, telefono, email, indirizzo, attivo) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $nome, $tipo, $partita_iva, $telefono, $email, $indirizzo, $attivo);
                
                if ($stmt->execute()) {
                    $messaggio = "Fornitore <strong>$nome</strong> creato con successo!";
                    $tipo_messaggio = 'success';
                    $_POST = array();
                }
            } else {
                // Modifica
                $sql = "UPDATE fornitori SET nome = ?, tipo = ?, partita_iva = ?, telefono = ?, email = ?, indirizzo = ?, attivo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssii", $nome, $tipo, $partita_iva, $telefono, $email, $indirizzo, $attivo, $fornitore_id);
                
                if ($stmt->execute()) {
                    $messaggio = "Fornitore aggiornato con successo!";
                    $tipo_messaggio = 'success';
                }
            }
        } catch (Exception $e) {
            $messaggio = 'Errore: ' . $e->getMessage();
            $tipo_messaggio = 'danger';
        }
    }
}

// Gestione attivazione/disattivazione
if (isset($_GET['toggle_attivo'])) {
    $fornitore_id = intval($_GET['toggle_attivo']);
    try {
        $sql_toggle = "UPDATE fornitori SET attivo = NOT attivo WHERE id = ?";
        $stmt_toggle = $conn->prepare($sql_toggle);
        $stmt_toggle->bind_param("i", $fornitore_id);
        
        if ($stmt_toggle->execute()) {
            $messaggio = 'Stato fornitore aggiornato!';
            $tipo_messaggio = 'success';
        }
    } catch (Exception $e) {
        $messaggio = 'Errore aggiornamento: ' . $e->getMessage();
        $tipo_messaggio = 'danger';
    }
}

// Gestione eliminazione
if (isset($_GET['elimina'])) {
    $fornitore_id = intval($_GET['elimina']);
    try {
        // Controllo se è utilizzato in lotti
        $check_uso_sql = "SELECT COUNT(*) as count FROM lotti_logistici WHERE fornitore_principale_id = ?";
        $check_uso_stmt = $conn->prepare($check_uso_sql);
        $check_uso_stmt->bind_param("i", $fornitore_id);
        $check_uso_stmt->execute();
        $check_uso_result = $check_uso_stmt->get_result();
        $check_uso_data = $check_uso_result->fetch_assoc();
        
        // Controllo se è utilizzato in lotto_fornitori
        $check_uso2_sql = "SELECT COUNT(*) as count FROM lotto_fornitori WHERE fornitore_id = ?";
        $check_uso2_stmt = $conn->prepare($check_uso2_sql);
        $check_uso2_stmt->bind_param("i", $fornitore_id);
        $check_uso2_stmt->execute();
        $check_uso2_result = $check_uso2_stmt->get_result();
        $check_uso2_data = $check_uso2_result->fetch_assoc();
        
        if ($check_uso_data['count'] > 0 || $check_uso2_data['count'] > 0) {
            throw new Exception("Impossibile eliminare: fornitore utilizzato in lotti esistenti! Disattivalo invece di eliminarlo.");
        }
        
        $sql_delete = "DELETE FROM fornitori WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $fornitore_id);
        
        if ($stmt_delete->execute()) {
            $messaggio = 'Fornitore eliminato con successo!';
            $tipo_messaggio = 'success';
        }
    } catch (Exception $e) {
        $messaggio = 'Errore eliminazione: ' . $e->getMessage();
        $tipo_messaggio = 'danger';
    }
}

// Recupera dati per modifica
$fornitore_modifica = null;
if (isset($_GET['modifica'])) {
    $fornitore_id = intval($_GET['modifica']);
    $sql_modifica = "SELECT * FROM fornitori WHERE id = ?";
    $stmt_modifica = $conn->prepare($sql_modifica);
    $stmt_modifica->bind_param("i", $fornitore_id);
    $stmt_modifica->execute();
    $result_modifica = $stmt_modifica->get_result();
    $fornitore_modifica = $result_modifica->fetch_assoc();
}

// Query lista fornitori con statistiche
$sql_fornitori = "SELECT f.*, 
                  (SELECT COUNT(*) FROM lotti_logistici ll WHERE ll.fornitore_principale_id = f.id) as lotti_principali,
                  (SELECT COUNT(*) FROM lotto_fornitori lf WHERE lf.fornitore_id = f.id) as forniture_totali,
                  (SELECT SUM(lf.costo_totale) FROM lotto_fornitori lf WHERE lf.fornitore_id = f.id) as fatturato_totale
                  FROM fornitori f
                  ORDER BY f.attivo DESC, f.tipo, f.nome";
$result_fornitori = $conn->query($sql_fornitori);

// Statistiche generali
$sql_stats = "SELECT 
              COUNT(*) as totale_fornitori,
              COUNT(CASE WHEN attivo = 1 THEN 1 END) as fornitori_attivi,
              COUNT(CASE WHEN tipo = 'principale' THEN 1 END) as fornitori_principali,
              COUNT(CASE WHEN tipo = 'servizio' THEN 1 END) as fornitori_servizio
              FROM fornitori";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Fornitori - Gestionale PMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-success text-white p-3 mb-4">
            <div class="col">
                <h1><i class="fas fa-truck"></i> Gestione Fornitori</h1>
                <p class="mb-0">Anagrafica completa fornitori materiali e servizi</p>
            </div>
        </div>

        <!-- Navigazione -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="anagrafica_lotti.php">Sistema Logistico</a></li>
                        <li class="breadcrumb-item active">Fornitori</li>
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
            <div class="col-md-3">
                <div class="card bg-primary text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['totale_fornitori']; ?></h4>
                        <p class="mb-0">Fornitori Totali</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['fornitori_attivi']; ?></h4>
                        <p class="mb-0">Fornitori Attivi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['fornitori_principali']; ?></h4>
                        <p class="mb-0">Materiali</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white text-center">
                    <div class="card-body">
                        <h4><?php echo $stats['fornitori_servizio']; ?></h4>
                        <p class="mb-0">Servizi</p>
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
                            <?php echo $fornitore_modifica ? 'Modifica Fornitore' : 'Nuovo Fornitore'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($fornitore_modifica): ?>
                                <input type="hidden" name="azione" value="modifica">
                                <input type="hidden" name="fornitore_id" value="<?php echo $fornitore_modifica['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="azione" value="inserisci">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    Nome Fornitore <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nome" 
                                       name="nome" 
                                       placeholder="es. Ferramenta Rossi S.r.l."
                                       value="<?php echo $fornitore_modifica ? htmlspecialchars($fornitore_modifica['nome']) : (isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''); ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="tipo" class="form-label">
                                    Tipo Fornitore <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="">Seleziona tipo...</option>
                                    <option value="principale" <?php echo ($fornitore_modifica && $fornitore_modifica['tipo'] == 'principale') || (isset($_POST['tipo']) && $_POST['tipo'] == 'principale') ? 'selected' : ''; ?>>
                                        🏭 Principale (Materiali)
                                    </option>
                                    <option value="servizio" <?php echo ($fornitore_modifica && $fornitore_modifica['tipo'] == 'servizio') || (isset($_POST['tipo']) && $_POST['tipo'] == 'servizio') ? 'selected' : ''; ?>>
                                        🔧 Servizio (Trasporti, Lavorazioni, etc.)
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="partita_iva" class="form-label">Partita IVA</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="partita_iva" 
                                       name="partita_iva" 
                                       placeholder="IT12345678901"
                                       value="<?php echo $fornitore_modifica ? htmlspecialchars($fornitore_modifica['partita_iva']) : (isset($_POST['partita_iva']) ? htmlspecialchars($_POST['partita_iva']) : ''); ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Telefono</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="telefono" 
                                               name="telefono" 
                                               placeholder="+39 123 456789"
                                               value="<?php echo $fornitore_modifica ? htmlspecialchars($fornitore_modifica['telefono']) : (isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               placeholder="info@fornitore.it"
                                               value="<?php echo $fornitore_modifica ? htmlspecialchars($fornitore_modifica['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="indirizzo" class="form-label">Indirizzo</label>
                                <textarea class="form-control" 
                                          id="indirizzo" 
                                          name="indirizzo" 
                                          rows="2"
                                          placeholder="Via Roma 123, 12345 Città (PR)"><?php echo $fornitore_modifica ? htmlspecialchars($fornitore_modifica['indirizzo']) : (isset($_POST['indirizzo']) ? htmlspecialchars($_POST['indirizzo']) : ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="attivo" 
                                           name="attivo" 
                                           <?php echo ($fornitore_modifica && $fornitore_modifica['attivo']) || (!$fornitore_modifica && !isset($_POST['attivo'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="attivo">
                                        Fornitore Attivo
                                    </label>
                                </div>
                                <div class="form-text">Fornitori disattivi non appaiono nelle selezioni</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $fornitore_modifica ? 'Aggiorna Fornitore' : 'Crea Fornitore'; ?>
                                </button>
                                <?php if ($fornitore_modifica): ?>
                                <a href="gestione_fornitori.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annulla Modifica
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista Fornitori -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Fornitori Registrati</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result_fornitori->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Contatti</th>
                                        <th>Utilizzi</th>
                                        <th>Fatturato</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($fornitore = $result_fornitori->fetch_assoc()): ?>
                                    <tr class="<?php echo !$fornitore['attivo'] ? 'table-light text-muted' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($fornitore['nome']); ?></strong>
                                            <?php if (!empty($fornitore['partita_iva'])): ?>
                                                <br><small class="text-muted">P.IVA: <?php echo htmlspecialchars($fornitore['partita_iva']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $fornitore['tipo'] == 'principale' ? 'info' : 'warning'; ?>">
                                                <?php echo $fornitore['tipo'] == 'principale' ? '🏭 Principale' : '🔧 Servizio'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if (!empty($fornitore['telefono'])): ?>
                                                    📞 <?php echo htmlspecialchars($fornitore['telefono']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($fornitore['email'])): ?>
                                                    ✉️ <?php echo htmlspecialchars($fornitore['email']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if ($fornitore['lotti_principali'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $fornitore['lotti_principali']; ?> lotti</span><br>
                                                <?php endif; ?>
                                                <?php if ($fornitore['forniture_totali'] > 0): ?>
                                                    <span class="badge bg-info"><?php echo $fornitore['forniture_totali']; ?> forniture</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Non utilizzato</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($fornitore['fatturato_totale'] > 0): ?>
                                                <strong>€ <?php echo number_format($fornitore['fatturato_totale'], 2, ',', '.'); ?></strong>
                                            <?php else: ?>
                                                <small class="text-muted">€ 0,00</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fornitore['attivo']): ?>
                                                <span class="badge bg-success">Attivo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disattivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <a href="?modifica=<?php echo $fornitore['id']; ?>" 
                                                   class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle_attivo=<?php echo $fornitore['id']; ?>" 
                                                   class="btn btn-outline-<?php echo $fornitore['attivo'] ? 'secondary' : 'success'; ?> btn-sm">
                                                    <i class="fas fa-<?php echo $fornitore['attivo'] ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <?php if ($fornitore['lotti_principali'] == 0 && $fornitore['forniture_totali'] == 0): ?>
                                                <a href="?elimina=<?php echo $fornitore['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Eliminare <?php echo htmlspecialchars($fornitore['nome']); ?>?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm" disabled title="In uso, non eliminabile">
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
                            <i class="fas fa-info-circle"></i> Nessun fornitore registrato.
                            <br><br>Inizia creando i tuoi fornitori principali!
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
                <a href="gestione_unita_misura.php" class="btn btn-info me-2">
                    <i class="fas fa-ruler-combined"></i> Unità di Misura
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