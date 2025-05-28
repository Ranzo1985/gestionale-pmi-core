<?php
// Connessione al database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'magazzino';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

$messaggio = '';
$tipo_messaggio = '';

// Recupera lotti con scorte disponibili
$lotti_sql = "SELECT l.id, l.quantita_attuale, l.data_arrivo, l.fornitore,
              p.codice, p.nome, p.prezzo_unitario
              FROM lotti l 
              JOIN prodotti p ON l.prodotto_id = p.id 
              WHERE l.quantita_attuale > 0 
              ORDER BY p.nome, l.data_arrivo";
$lotti_result = $conn->query($lotti_sql);

// Gestione invio form
if ($_POST) {
    $lotto_id = intval($_POST['lotto_id']);
    $tipo = $_POST['tipo'];
    $quantita = intval($_POST['quantita']);
    $note = trim($_POST['note']);
    
    // Validazione
    if ($lotto_id <= 0 || empty($tipo) || $quantita <= 0) {
        $messaggio = 'Compila tutti i campi obbligatori!';
        $tipo_messaggio = 'danger';
    } else {
        // Controlla disponibilità
        $check_sql = "SELECT quantita_attuale FROM lotti WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $lotto_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $lotto_data = $check_result->fetch_assoc();
        
        if (!$lotto_data) {
            $messaggio = 'Lotto non trovato!';
            $tipo_messaggio = 'danger';
        } elseif ($tipo == 'uscita' && $quantita > $lotto_data['quantita_attuale']) {
            $messaggio = 'Quantità non disponibile! Disponibili: ' . $lotto_data['quantita_attuale'] . ' pezzi';
            $tipo_messaggio = 'warning';
        } else {
            // Inizia transazione
            $conn->begin_transaction();
            
            try {
                // Inserisci movimento
                $movimento_sql = "INSERT INTO movimenti (lotto_id, data_movimento, tipo, quantita, note) VALUES (?, NOW(), ?, ?, ?)";
                $movimento_stmt = $conn->prepare($movimento_sql);
                $movimento_stmt->bind_param("isis", $lotto_id, $tipo, $quantita, $note);
                $movimento_stmt->execute();
                
                // Aggiorna quantità lotto
                if ($tipo == 'uscita') {
                    $update_sql = "UPDATE lotti SET quantita_attuale = quantita_attuale - ? WHERE id = ?";
                } else {
                    $update_sql = "UPDATE lotti SET quantita_attuale = quantita_attuale + ? WHERE id = ?";
                }
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $quantita, $lotto_id);
                $update_stmt->execute();
                
                // Conferma transazione
                $conn->commit();
                
                $messaggio = 'Movimento registrato con successo! Scorte aggiornate.';
                $tipo_messaggio = 'success';
                $_POST = array();
                
                // Ricarica i lotti
                $lotti_result = $conn->query($lotti_sql);
                
            } catch (Exception $e) {
                $conn->rollback();
                $messaggio = 'Errore durante il movimento: ' . $e->getMessage();
                $tipo_messaggio = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimento - Gestione Magazzino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>📝 Movimento Magazzino</h1>
                    <a href="index.php" class="btn btn-secondary">
                        ⬅️ Torna alla Lista
                    </a>
                </div>

                <!-- Messaggio di feedback -->
                <?php if (!empty($messaggio)): ?>
                <div class="alert alert-<?php echo $tipo_messaggio; ?> alert-dismissible fade show" role="alert">
                    <?php echo $messaggio; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Controllo se ci sono lotti -->
                <?php if ($lotti_result->num_rows == 0): ?>
                <div class="alert alert-warning">
                    <h4>⚠️ Nessun lotto disponibile</h4>
                    <p>Non ci sono prodotti in magazzino o scorte disponibili.</p>
                    <a href="nuovo_lotto.php" class="btn btn-primary">📦 Inserisci Nuovo Lotto</a>
                </div>
                <?php else: ?>

                <div class="row">
                    <!-- Form movimento -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3>Registra Movimento</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="tipo" class="form-label">
                                            Tipo Movimento <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="tipo" name="tipo" required>
                                            <option value="">Seleziona tipo...</option>
                                            <option value="uscita" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'uscita') ? 'selected' : ''; ?>>
                                                ⬇️ Uscita (Vendita/Uso)
                                            </option>
                                            <option value="entrata" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'entrata') ? 'selected' : ''; ?>>
                                                ⬆️ Entrata (Correzione/Reso)
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="lotto_id" class="form-label">
                                            Prodotto/Lotto <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="lotto_id" name="lotto_id" required>
                                            <option value="">Seleziona prodotto...</option>
                                            <?php 
                                            $lotti_result->data_seek(0);
                                            while($lotto = $lotti_result->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $lotto['id']; ?>" 
                                                    data-disponibile="<?php echo $lotto['quantita_attuale']; ?>"
                                                    <?php echo (isset($_POST['lotto_id']) && $_POST['lotto_id'] == $lotto['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lotto['codice'] . ' - ' . $lotto['nome']); ?> 
                                                (Disp: <?php echo $lotto['quantita_attuale']; ?> - 
                                                Forn: <?php echo htmlspecialchars($lotto['fornitore']); ?> - 
                                                <?php echo date('d/m/Y', strtotime($lotto['data_arrivo'])); ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">Scegli il lotto da cui prelevare/aggiungere</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="quantita" class="form-label">
                                            Quantità <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="quantita" 
                                               name="quantita" 
                                               min="1" 
                                               placeholder="Quanti pezzi?"
                                               value="<?php echo isset($_POST['quantita']) ? $_POST['quantita'] : ''; ?>"
                                               required>
                                        <div class="form-text" id="disponibilita-info"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="note" class="form-label">
                                            Note
                                        </label>
                                        <textarea class="form-control" 
                                                  id="note" 
                                                  name="note" 
                                                  rows="3" 
                                                  placeholder="es. Vendita a cliente Rossi, Materiale difettoso, ecc."><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note']) : ''; ?></textarea>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-warning">
                                            💾 Registra Movimento
                                        </button>
                                        <a href="index.php" class="btn btn-secondary">
                                            ❌ Annulla
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabella lotti disponibili -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>📦 Scorte Disponibili</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Prodotto</th>
                                                <th>Fornitore</th>
                                                <th>Data</th>
                                                <th>Disp.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $lotti_result->data_seek(0);
                                            while($lotto = $lotti_result->fetch_assoc()): 
                                            ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <strong><?php echo htmlspecialchars($lotto['codice']); ?></strong><br>
                                                        <?php echo htmlspecialchars($lotto['nome']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($lotto['fornitore']); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo date('d/m/Y', strtotime($lotto['data_arrivo'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $lotto['quantita_attuale']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Info box -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6>💡 Tipi di Movimento</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <strong>⬇️ Uscita:</strong> Vendita, uso interno, rotture<br>
                                    <strong>⬆️ Entrata:</strong> Correzioni, resi clienti
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Mostra disponibilità quando selezioni un lotto
    document.getElementById('lotto_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const disponibile = selectedOption.getAttribute('data-disponibile');
        const infoDiv = document.getElementById('disponibilita-info');
        
        if (disponibile) {
            infoDiv.innerHTML = '<strong>Disponibili: ' + disponibile + ' pezzi</strong>';
            infoDiv.className = 'form-text text-success';
        } else {
            infoDiv.innerHTML = '';
        }
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>