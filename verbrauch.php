<?php
/**
 * Utility Tracker - Fokus auf monatliches Delta (Verbrauch)
 */
$jsonFile = 'verbrauch_data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// 1. Speichern & Löschen (wie zuvor)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $date = $_POST['date'];
    $type = $_POST['type'];
    if (!isset($data[$date])) { $data[$date] = ['strom_verb' => null, 'strom_einsp' => null, 'wasser' => null]; }
    if ($type === 'strom') {
        $data[$date]['strom_verb'] = (float)$_POST['strom_verb'];
        $data[$date]['strom_einsp'] = (float)$_POST['strom_einsp'];
    } else { $data[$date]['wasser'] = (float)$_POST['wasser']; }
    ksort($data);
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}
if (isset($_GET['delete'])) {
    unset($data[$_GET['delete']]);
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// 2. Berechnung der monatlichen Differenzen für das Diagramm
$chartLabels = [];
$chartDeltaVerb = [];
$chartDeltaEinsp = [];
$chartDeltaWasser = [];

for ($i = 23; $i >= 0; $i--) {
    $dCurrent = new DateTime("first day of -$i months");
    $dPrev = new DateTime("first day of -".($i+1)." months");
    
    $monthKey = $dCurrent->format('Y-m');
    $prevMonthKey = $dPrev->format('Y-m');
    $chartLabels[] = $dCurrent->format('M Y');

    // Hilfsfunktion: Letzten Wert eines Monats finden
    $getLastOf = function($mKey) use ($data) {
        $last = null;
        if (!empty($data)) {
            foreach($data as $date => $vals) { 
                if (strpos($date, $mKey) === 0) $last = $vals; 
            }
        }
        return $last;
    };

    $curr = $getLastOf($monthKey);
    $prev = $getLastOf($prevMonthKey);

    // Sicherheitsprüfung: Nur rechnen, wenn sowohl aktueller als auch Vorjahreswert existieren
    $chartDeltaVerb[] = (isset($curr['strom_verb']) && isset($prev['strom_verb'])) 
                        ? $curr['strom_verb'] - $prev['strom_verb'] : null;
                        
    $chartDeltaEinsp[] = (isset($curr['strom_einsp']) && isset($prev['strom_einsp'])) 
                         ? $curr['strom_einsp'] - $prev['strom_einsp'] : null;
                         
    $chartDeltaWasser[] = (isset($curr['wasser']) && isset($prev['wasser'])) 
                          ? $curr['wasser'] - $prev['wasser'] : null;
}

// 3. Historie für Tabelle (wie zuvor)
$sortedData = $data; ksort($sortedData);
$historyWithDelta = [];
$pSTV = null; $pSTE = null; $pWAS = null;
foreach ($sortedData as $date => $v) {
    $row = $v; $row['date'] = $date;
    $row['dV'] = ($pSTV !== null && $v['strom_verb'] !== null) ? $v['strom_verb'] - $pSTV : null;
    if ($v['strom_verb'] !== null) $pSTV = $v['strom_verb'];
    $row['dE'] = ($pSTE !== null && $v['strom_einsp'] !== null) ? $v['strom_einsp'] - $pSTE : null;
    if ($v['strom_einsp'] !== null) $pSTE = $v['strom_einsp'];
    $row['dW'] = ($pWAS !== null && $v['wasser'] !== null) ? $v['wasser'] - $pWAS : null;
    if ($v['wasser'] !== null) $pWAS = $v['wasser'];
    $historyWithDelta[] = $row;
}
$displayHistory = array_reverse($historyWithDelta);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Utility Delta Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        form { background: #f8f9fa; padding: 20px; border-radius: 8px; display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px; }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        input, select, button { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        button { background: #007bff; color: white; border: none; cursor: pointer; font-weight: bold; }
        .chart-box { height: 400px; margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .delta { font-size: 0.85em; color: #28a745; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>Verbrauchsanalyse (Monatliche Differenz)</h1>

    <form method="POST">
        <input type="hidden" name="action" value="save">
        <div class="input-group"><label>Datum</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="input-group"><label>Typ</label>
            <select name="type" id="tS" onchange="document.getElementById('sF').style.display=(this.value==='strom')?'block':'none';document.getElementById('wF').style.display=(this.value==='wasser')?'block':'none';">
                <option value="strom">Strom (kWh)</option><option value="wasser">Wasser (m³)</option>
            </select>
        </div>
        <div id="sF" class="input-group"><label>Verb. / Einsp.</label>
            <div style="display:flex; gap:5px;"><input type="number" step="0.01" name="strom_verb" placeholder="Verb."><input type="number" step="0.01" name="strom_einsp" placeholder="Einsp."></div>
        </div>
        <div id="wF" class="input-group" style="display:none;"><label>Zählerstand Wasser</label><input type="number" step="0.001" name="wasser" placeholder="m³"></div>
        <button type="submit">Speichern</button>
    </form>

    <div class="chart-box"><canvas id="deltaChart"></canvas></div>

    <h2>Historie</h2>
    <table>
        <thead><tr><th>Datum</th><th>Strom Verb.</th><th>Strom Einsp.</th><th>Wasser</th><th>Aktion</th></tr></thead>
        <tbody>
            <?php foreach ($displayHistory as $r): ?>
            <tr>
                <td><?= $r['date'] ?></td>
                <td><?= $r['strom_verb'] ?> <span class="delta"><?= $r['dV']?"(+{$r['dV']})":'' ?></span></td>
                <td><?= $r['strom_einsp'] ?> <span class="delta"><?= $r['dE']?"(".($r['dE']>0?'+':'')."{$r['dE']})":'' ?></span></td>
                <td><?= $r['wasser'] ?> <span class="delta"><?= $r['dW']?"(+{$r['dW']})":'' ?></span></td>
                <td><a href="?delete=<?= $r['date'] ?>" style="color:red;" onclick="return confirm('Löschen?')">Löschen</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    new Chart(document.getElementById('deltaChart'), {
        type: 'line', // Balkendiagramm eignet sich oft besser für Monatsvergleiche
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                { label: 'Verbrauch (kWh)', data: <?= json_encode($chartDeltaVerb) ?>, backgroundColor: '#dc3545', yAxisID: 'y' },
                { label: 'Einspeisung (kWh)', data: <?= json_encode($chartDeltaEinsp) ?>, backgroundColor: '#ffc107', yAxisID: 'y' },
                { label: 'Wasser (m³)', data: <?= json_encode($chartDeltaWasser) ?>, backgroundColor: '#007bff', yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, position: 'left', title: { display: true, text: 'kWh / Monat' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'm³ / Monat' } }
            }
        }
    });
</script>
</body>
</html>