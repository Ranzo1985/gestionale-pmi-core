<?php
// Connessione al database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'magazzino';

$conn = new mysqli($host, $username, $password, $database);

// Controllo connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

$messaggio = '';
$tipo_messaggio = '';

// Gestione modifica esistente
$prodotto_modifica = null;
$modalita_modifica = false;

if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $prodotto_id = intval($_GET['edit']);
    $sql_modifica = "SELECT * FROM prodotti WHERE id = ?";
    $stmt_modifica = $conn->prepare($sql_modifica);
    $stmt_modifica->bind_param("i", $prodotto_id);
    $stmt_modifica->execute();
    $result_modifica = $stmt_modifica->get_result();
    
    if ($result_modifica->num_rows > 0) {
        $prodotto_modifica = $result_modifica->fetch_assoc();
        $modalita_modifica = true;
    }
}

// Gestione invio form
if ($_POST) {
    $azione = $_POST['azione'] ?? 'inserisci';
    $prodotto_id = isset($_POST['prodotto_id']) ? intval($_POST['prodotto_id']) : 0;
    
    $codice = trim($_POST['codice']);
    $nome = trim($_POST['nome']);
    $descrizione = trim($_POST['descrizione']);
    $prezzo = floatval($_POST['prezzo']);
    $tipologia = $_POST['tipologia'];
    
    // Nuovi campi unità di misura
    $unita_misura_principale = $_POST['unita_misura_principale'];
    $unita_misura_secondaria_1 = !empty($_POST['unita_misura_secondaria_1']) ? $_POST['unita_misura_secondaria_1'] : null;
    $unita_misura_secondaria_2 = !empty($_POST['unita_misura_secondaria_2']) ? $_POST['unita_misura_secondaria_2'] : null;
    $unita_misura_secondaria_3 = !empty($_POST['unita_misura_secondaria_3']) ? $_POST['unita_misura_secondaria_3'] : null;
    
    $conversione_ump_um1 = !empty($_POST['conversione_ump_um1']) ? floatval($_POST['conversione_ump_um1']) : null;
    $conversione_ump_um2 = !empty($_POST['conversione_ump_um2']) ? floatval($_POST['conversione_ump_um2']) : null;
    $conversione_ump_um3 = !empty($_POST['conversione_ump_um3']) ? floatval($_POST['conversione_ump_um3']) : null;
    
    // Validazione semplice
    if (empty($codice) || empty($nome) || $prezzo <= 0 || empty($tipologia) || empty($unita_misura_principale)) {
        $messaggio = 'Compila tutti i campi obbligatori e inserisci un prezzo valido!';
        $tipo_messaggio = 'danger';
    } else {
        try {
            if ($azione == 'inserisci') {
                // Controllo se il codice esiste già
                $check_sql = "SELECT id FROM prodotti WHERE codice = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $codice);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $messaggio = 'Codice prodotto già esistente! Usa un codice diverso.';
                    $tipo_messaggio = 'warning';
                } else {
                    // Inserimento prodotto completo
                    $insert_sql = "INSERT INTO prodotti (codice, nome, descrizione, prezzo_unitario, tipologia, unita_misura_principale, unita_misura_secondaria_1, unita_misura_secondaria_2, unita_misura_secondaria_3, conversione_ump_um1, conversione_ump_um2, conversione_ump_um3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("sssdsssssddd", $codice, $nome, $descrizione, $prezzo, $tipologia, $unita_misura_principale, $unita_misura_secondaria_1, $unita_misura_secondaria_2, $unita_misura_secondaria_3, $conversione_ump_um1, $conversione_ump_um2, $conversione_ump_um3);
                    
                    if ($insert_stmt->execute()) {
                        $messaggio = 'Prodotto inserito con successo!';
                        $tipo_messaggio = 'success';
                        // Pulisci i campi dopo l'inserimento
                        $_POST = array();
                    } else {
                        $messaggio = 'Errore durante l\'inserimento: ' . $conn->error;
                        $tipo_messaggio = 'danger';
                    }
                }
            } else {
                // Modifica prodotto esistente
                $update_sql = "UPDATE prodotti SET nome = ?, descrizione = ?, prezzo_unitario = ?, tipologia = ?, unita_misura_principale = ?, unita_misura_secondaria_1 = ?, unita_misura_secondaria_2 = ?, unita_misura_secondaria_3 = ?, conversione_ump_um1 = ?, conversione_ump_um2 = ?, conversione_ump_um3 = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssdssssssddi", $nome, $descrizione, $prezzo, $tipologia, $unita_misura_principale, $unita_misura_secondaria_1, $unita_misura_secondaria_2, $unita_misura_secondaria_3, $conversione_ump_um1, $conversione_ump_um2, $conversione_ump_um3, $prodotto_id);
                
                if ($update_stmt->execute()) {
                    $messaggio = 'Prodotto aggiornato con successo!';
                    $tipo_messaggio = 'success';
                    // Ricarica dati aggiornati
                    $stmt_modifica->execute();
                    $result_modifica = $stmt_modifica->get_result();
                    $prodotto_modifica = $result_modifica->fetch_assoc();
                } else {
                    $messaggio = 'Errore durante l\'aggiornamento: ' . $conn->error;
                    $tipo_messaggio = 'danger';
                }
            }
        } catch (Exception $e) {
            $messaggio = 'Errore: ' . $e->getMessage();
            $tipo_messaggio = 'danger';
        }
    }
}

// Query per unità di misura disponibili
$sql_unita_misura = "SELECT codice, nome, tipologia FROM unita_misura ORDER BY tipologia, codice";
$result_unita_misura = $conn->query($sql_unita_misura);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $modalita_modifica ? 'Modifica' : 'Nuovo'; ?> Prodotto - Gestionale PMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        <i class="fas fa-cube"></i> 
                        <?php echo $modalita_modifica ? 'Modifica Prodotto' : 'Nuovo Prodotto'; ?>
                    </h1>
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

                <!-- Form inserimento prodotto -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <?php echo $modalita_modifica ? 'Modifica Dati Prodotto' : 'Inserimento Nuovo Prodotto'; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($modalita_modifica): ?>
                                <input type="hidden" name="azione" value="modifica">
                                <input type="hidden" name="prodotto_id" value="<?php echo $prodotto_modifica['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="azione" value="inserisci">
                            <?php endif; ?>

                            <!-- Sezione Dati Base -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="fas fa-info-circle"></i> Dati Base Prodotto
                                    </h5>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="codice" class="form-label">
                                            Codice Prodotto <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="codice" 
                                               name="codice" 
                                               placeholder="es. PROD001" 
                                               value="<?php echo $prodotto_modifica ? htmlspecialchars($prodotto_modifica['codice']) : (isset($_POST['codice']) ? htmlspecialchars($_POST['codice']) : ''); ?>"
                                               <?php echo $modalita_modifica ? 'readonly' : 'required'; ?>>
                                        <?php if ($modalita_modifica): ?>
                                        <div class="form-text">Il codice non può essere modificato</div>
                                        <?php else: ?>
                                        <div class="form-text">Codice univoco per identificare il prodotto</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="tipologia" class="form-label">
                                            Tipologia <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="tipologia" name="tipologia" required>
                                            <option value="">Seleziona tipologia...</option>
                                            <option value="materia_prima" <?php echo ($prodotto_modifica && $prodotto_modifica['tipologia'] == 'materia_prima') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'materia_prima') ? 'selected' : ''; ?>>
                                                🌱 Materia Prima
                                            </option>
                                            <option value="semilavorato" <?php echo ($prodotto_modifica && $prodotto_modifica['tipologia'] == 'semilavorato') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'semilavorato') ? 'selected' : ''; ?>>
                                                ⚙️ Semilavorato
                                            </option>
                                            <option value="prodotto_finito" <?php echo ($prodotto_modifica && $prodotto_modifica['tipologia'] == 'prodotto_finito') || (isset($_POST['tipologia']) && $_POST['tipologia'] == 'prodotto_finito') ? 'selected' : ''; ?>>
                                                📦 Prodotto Finito
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="prezzo" class="form-label">
                                            Prezzo Unitario (€) <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="prezzo" 
                                               name="prezzo" 
                                               step="0.01" 
                                               min="0.01" 
                                               placeholder="0.00"
                                               value="<?php echo $prodotto_modifica ? $prodotto_modifica['prezzo_unitario'] : (isset($_POST['prezzo']) ? $_POST['prezzo'] : ''); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    Nome Prodotto <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nome" 
                                       name="nome" 
                                       placeholder="es. Martello da carpentiere"
                                       value="<?php echo $prodotto_modifica ? htmlspecialchars($prodotto_modifica['nome']) : (isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''); ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="descrizione" class="form-label">
                                    Descrizione (opzionale)
                                </label>
                                <textarea class="form-control" 
                                          id="descrizione" 
                                          name="descrizione" 
                                          rows="2" 
                                          placeholder="Descrizione dettagliata del prodotto..."><?php echo $prodotto_modifica ? htmlspecialchars($prodotto_modifica['descrizione']) : (isset($_POST['descrizione']) ? htmlspecialchars($_POST['descrizione']) : ''); ?></textarea>
                            </div>

                            <!-- Sezione Unità di Misura -->
                            <div class="row mb-4 mt-5">
                                <div class="col-12">
                                    <h5 class="text-success border-bottom pb-2">
                                        <i class="fas fa-ruler-combined"></i> Configurazione Unità di Misura
                                    </h5>
                                    <p class="text-muted">Configura le unità di misura e i coefficienti di conversione per questo prodotto</p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="unita_misura_principale" class="form-label">
                                            Unità di Misura Principale (UMP) <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="unita_misura_principale" name="unita_misura_principale" required>
                                            <option value="">Seleziona UMP...</option>
                                            <?php
                                            $result_unita_misura->data_seek(0);
                                            while($um = $result_unita_misura->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $um['codice']; ?>" 
                                                    <?php echo ($prodotto_modifica && $prodotto_modifica['unita_misura_principale'] == $um['codice']) || (isset($_POST['unita_misura_principale']) && $_POST['unita_misura_principale'] == $um['codice']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($um['codice'] . ' - ' . $um['nome'] . ' (' . $um['tipologia'] . ')'); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">Unità principale per calcoli costi e inventario</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Esempio Conversioni</label>
                                        <div class="card bg-light">
                                            <div class="card-body py-2">
                                                <small>
                                                    <strong>Tronchi Legno:</strong> UMP=MC, UM1=MT (1 MT = 0.5 MC)<br>
                                                    <strong>Metallo:</strong> UMP=KG, UM1=PZ (1 PZ = 2.5 KG)<br>
                                                    <strong>Tessuto:</strong> UMP=MQ, UM1=MT (1 MT = 1.4 MQ)
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Unità secondarie -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="unita_misura_secondaria_1" class="form-label">Unità Secondaria 1</label>
                                        <select class="form-select" id="unita_misura_secondaria_1" name="unita_misura_secondaria_1">
                                            <option value="">Nessuna</option>
                                            <?php
                                            $result_unita_misura->data_seek(0);
                                            while($um = $result_unita_misura->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $um['codice']; ?>" 
                                                    <?php echo ($prodotto_modifica && $prodotto_modifica['unita_misura_secondaria_1'] == $um['codice']) || (isset($_POST['unita_misura_secondaria_1']) && $_POST['unita_misura_secondaria_1'] == $um['codice']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($um['codice'] . ' - ' . $um['nome']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="conversione_ump_um1" class="form-label">Conversione: 1 UM1 = ? UMP</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="conversione_ump_um1" 
                                               name="conversione_ump_um1" 
                                               step="0.0001" 
                                               min="0.0001" 
                                               placeholder="es. 0.5000"
                                               value="<?php echo $prodotto_modifica ? $prodotto_modifica['conversione_ump_um1'] : (isset($_POST['conversione_ump_um1']) ? $_POST['conversione_ump_um1'] : ''); ?>">
                                        <div class="form-text">Fattore di conversione da UM1 a UMP</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div id="esempio_conversione_1" class="mt-4 text-muted small">
                                        Seleziona UM1 per vedere esempio
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="unita_misura_secondaria_2" class="form-label">Unità Secondaria 2</label>
                                        <select class="form-select" id="unita_misura_secondaria_2" name="unita_misura_secondaria_2">
                                            <option value="">Nessuna</option>
                                            <?php
                                            $result_unita_misura->data_seek(0);
                                            while($um = $result_unita_misura->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $um['codice']; ?>" 
                                                    <?php echo ($prodotto_modifica && $prodotto_modifica['unita_misura_secondaria_2'] == $um['codice']) || (isset($_POST['unita_misura_secondaria_2']) && $_POST['unita_misura_secondaria_2'] == $um['codice']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($um['codice'] . ' - ' . $um['nome']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="conversione_ump_um2" class="form-label">Conversione: 1 UM2 = ? UMP</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="conversione_ump_um2" 
                                               name="conversione_ump_um2" 
                                               step="0.0001" 
                                               min="0.0001" 
                                               placeholder="es. 2.5000"
                                               value="<?php echo $prodotto_modifica ? $prodotto_modifica['conversione_ump_um2'] : (isset($_POST['conversione_ump_um2']) ? $_POST['conversione_ump_um2'] : ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div id="esempio_conversione_2" class="mt-4 text-muted small">
                                        Seleziona UM2 per vedere esempio
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="unita_misura_secondaria_3" class="form-label">Unità Secondaria 3</label>
                                        <select class="form-select" id="unita_misura_secondaria_3" name="unita_misura_secondaria_3">
                                            <option value="">Nessuna</option>
                                            <?php
                                            $result_unita_misura->data_seek(0);
                                            while($um = $result_unita_misura->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo $um['codice']; ?>" 
                                                    <?php echo ($prodotto_modifica && $prodotto_modifica['unita_misura_secondaria_3'] == $um['codice']) || (isset($_POST['unita_misura_secondaria_3']) && $_POST['unita_misura_secondaria_3'] == $um['codice']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($um['codice'] . ' - ' . $um['nome']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="conversione_ump_um3" class="form-label">Conversione: 1 UM3 = ? UMP</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="conversione_ump_um3" 
                                               name="conversione_ump_um3" 
                                               step="0.0001" 
                                               min="0.0001" 
                                               placeholder="es. 1.0000"
                                               value="<?php echo $prodotto_modifica ? $prodotto_modifica['conversione_ump_um3'] : (isset($_POST['conversione_ump_um3']) ? $_POST['conversione_ump_um3'] : ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div id="esempio_conversione_3" class="mt-4 text-muted small">
                                        Seleziona UM3 per vedere esempio
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="index.php" class="btn btn-secondary me-md-2">
                                    ❌ Annulla
                                </a>
                                <button type="submit" class="btn btn-success">
                                    💾 <?php echo $modalita_modifica ? 'Aggiorna Prodotto' : 'Salva Prodotto'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Link rapidi -->
                <div class="text-center mt-4">
                    <a href="gestione_unita_misura.php" class="btn btn-outline-info me-2">
                        <i class="fas fa-ruler-combined"></i> Gestione Unità di Misura
                    </a>
                    <a href="anagrafica_lotti.php" class="btn btn-outline-success">
                        <i class="fas fa-layer-group"></i> Lotti Logistici
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Esempi conversioni dinamici
    function aggiornaEsempioConversione(umSecondaria, conversione, esempioDivId) {
        const umSecondariaSelect = document.getElementById(umSecondaria);
        const conversioneInput = document.getElementById(conversione);
        const esempioDiv = document.getElementById(esempioDivId);
        
        const umPrincipale = document.getElementById('unita_misura_principale').value;
        const umSecondariaTesto = umSecondariaSelect.options[umSecondariaSelect.selectedIndex].text;
        const fattoreConversione = conversioneInput.value;
        
        if (umSecondariaSelect.value && fattoreConversione && umPrincipale) {
            const umSecondariaCodice = umSecondariaSelect.value;
            esempioDiv.innerHTML = `<strong>Esempio:</strong> 1 ${umSecondariaCodice} = ${fattoreConversione} ${umPrincipale}`;
            esempioDiv.className = 'mt-4 text-success small';
        } else {
            esempioDiv.innerHTML = 'Configura UM e fattore per vedere esempio';
            esempioDiv.className = 'mt-4 text-muted small';
        }
    }
    
    // Event listeners per esempi dinamici
    ['unita_misura_secondaria_1', 'conversione_ump_um1'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            aggiornaEsempioConversione('unita_misura_secondaria_1', 'conversione_ump_um1', 'esempio_conversione_1');
        });
    });
    
    ['unita_misura_secondaria_2', 'conversione_ump_um2'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            aggiornaEsempioConversione('unita_misura_secondaria_2', 'conversione_ump_um2', 'esempio_conversione_2');
        });
    });
    
    ['unita_misura_secondaria_3', 'conversione_ump_um3'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            aggiornaEsempioConversione('unita_misura_secondaria_3', 'conversione_ump_um3', 'esempio_conversione_3');
        });
    });
    
    // Aggiorna esempi al caricamento se ci sono valori
    document.addEventListener('DOMContentLoaded', function() {
        aggiornaEsempioConversione('unita_misura_secondaria_1', 'conversione_ump_um1', 'esempio_conversione_1');
        aggiornaEsempioConversione('unita_misura_secondaria_2', 'conversione_ump_um2', 'esempio_conversione_2');
        aggiornaEsempioConversione('unita_misura_secondaria_3', 'conversione_ump_um3', 'esempio_conversione_3');
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>