<?php
/**
 * Utility Tracker - PKV Design Edition (Fokus auf monatliches Delta)
 */
$jsonFile = 'data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// 1. Speichern & Löschen
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

// 2. Berechnung der monatlichen Differenzen
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

    $chartDeltaVerb[] = (isset($curr['strom_verb']) && isset($prev['strom_verb'])) 
                        ? $curr['strom_verb'] - $prev['strom_verb'] : null;
    $chartDeltaEinsp[] = (isset($curr['strom_einsp']) && isset($prev['strom_einsp'])) 
                         ? $curr['strom_einsp'] - $prev['strom_einsp'] : null;
    $chartDeltaWasser[] = (isset($curr['wasser']) && isset($prev['wasser'])) 
                          ? $curr['wasser'] - $prev['wasser'] : null;
}

// 3. Historie für Tabelle
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
    <title>Verbrauchsanalyse</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --pkv-blue: #007bff; 
            --bg-gray: #f0f2f5; 
            --dark-gray: #343a40;
            --danger-red: #dc3545;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-gray); 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto 30px auto; 
            padding: 0 20px; 
            flex: 1; 
            width: 100%; 
            box-sizing: border-box; 
        }

        .content-box { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
        }

        h2, h3 { color: #333; margin-top: 0; }

        form { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            align-items: flex-end; 
            margin-bottom: 30px; 
            border: 1px solid #eee;
        }

        .input-group { display: flex; flex-direction: column; gap: 4px; }
        .input-group label { font-size: 0.75rem; color: #666; font-weight: bold; text-transform: uppercase; }
        
        input, select, button { padding: 10px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; }
        button { background: var(--pkv-blue); color: white; border: none; cursor: pointer; font-weight: bold; padding: 10px 20px; }
        button:hover { background: #0056b3; }

        .chart-box { height: 400px; margin-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 0.75rem; color: #666; border-bottom: 2px solid #eee; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }
        
        .delta { font-size: 0.85em; color: #28a745; font-weight: bold; }
        .btn-delete { color: var(--danger-red); text-decoration: none; font-weight: bold; }

        footer { 
            background: var(--dark-gray); 
            color: #bbb; 
            padding: 30px; 
            text-align: center; 
            margin-top: 40px; 
            font-size: 0.85rem; 
        }
        footer a { color: white; text-decoration: none; border-bottom: 1px solid #555; }
    </style>
</head>
<body>

<!-- Globaler Header -->
<style>
    .main-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 30px;
        background-color: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: 'Segoe UI', sans-serif;
        margin-bottom: 20px;
    }

    .header-logo img {
        height: 50px; /* Größe nach Bedarf anpassen */
        width: auto;
        display: block;
    }

    .header-title-center h1 {
        margin: 0;
        font-size: 1.5rem;
        color: #333;
        text-align: center;
    }

    .header-nav-right .btn-dashboard {
        text-decoration: none;
        background-color: #007bff;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: bold;
        transition: background 0.3s;
    }

    .header-nav-right .btn-dashboard:hover {
        background-color: #0056b3;
    }
</style>

<header class="main-header">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <div class="header-logo">
        <img src="../logo.png" alt="Logo">
    </div>
    
    <div class="header-title-center">
        <h1>Verbrauchsanalyse</h1>
    </div>

    <div class="header-nav-right">
		<a href="../index.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a>
    </div>
</header>

<div class="container">
    <div class="content-box">
        <h2>Zählerstand erfassen</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            
            <div class="input-group">
                <label>Datum</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="input-group">
                <label>Typ</label>
                <select name="type" id="tS" onchange="document.getElementById('sF').style.display=(this.value==='strom')?'flex':'none';document.getElementById('wF').style.display=(this.value==='wasser')?'flex':'none';">
                    <option value="strom">Strom (kWh)</option>
                    <option value="wasser">Wasser (m³)</option>
                </select>
            </div>

            <div id="sF" class="input-group">
                <label>Verb. / Einsp.</label>
                <div style="display:flex; gap:5px;">
                    <input type="number" step="0.01" name="strom_verb" placeholder="Verbrauch" style="width:110px;">
                    <input type="number" step="0.01" name="strom_einsp" placeholder="Einspeisung" style="width:110px;">
                </div>
            </div>

            <div id="wF" class="input-group" style="display:none;">
                <label>Zählerstand Wasser</label>
                <input type="number" step="0.001" name="wasser" placeholder="m³" style="width:225px;">
            </div>

            <button type="submit">Speichern</button>
        </form>

        <div class="chart-box">
            <canvas id="deltaChart"></canvas>
        </div>
    </div>

    <div class="content-box">
        <h2>Historie & Differenzen</h2>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Strom Verb.</th>
                    <th>Strom Einsp.</th>
                    <th>Wasser</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayHistory as $r): ?>
                <tr>
                    <td><?= date("d.m.Y", strtotime($r['date'])) ?></td>
                    <td><?= $r['strom_verb'] ?> <span class="delta"><?= $r['dV']?"(+".number_format($r['dV'],1).")":'' ?></span></td>
                    <td><?= $r['strom_einsp'] ?> <span class="delta"><?= $r['dE']?"(".($r['dE']>0?'+':'').number_format($r['dE'],1).")":'' ?></span></td>
                    <td><?= $r['wasser'] ?> <span class="delta"><?= $r['dW']?"(+".number_format($r['dW'],3).")":'' ?></span></td>
                    <td><a href="?delete=<?= $r['date'] ?>" class="btn-delete" onclick="return confirm('Löschen?')">🗑️</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p><strong>Verbrauchsanalyse</strong> | Lizenziert unter <a href="https://www.gnu.org/licenses/agpl-3.0.de.html" target="_blank">AGPL-3.0</a> | Source: <a href="https://github.com/herr-nm/Privat_Hausverbrauch" target="_blank">GitHub</a></p>
</footer>

<script>
    new Chart(document.getElementById('deltaChart'), {
        type: 'line', 
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                { 
                    label: 'Verbrauch (kWh)', 
                    data: <?= json_encode($chartDeltaVerb) ?>, 
                    borderColor: '#dc3545', 
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y' 
                },
                { 
                    label: 'Einspeisung (kWh)', 
                    data: <?= json_encode($chartDeltaEinsp) ?>, 
                    borderColor: '#ffc107', 
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y' 
                },
                { 
                    label: 'Wasser (m³)', 
                    data: <?= json_encode($chartDeltaWasser) ?>, 
                    borderColor: '#007bff', 
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1' 
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true, 
                    position: 'left', 
                    title: { display: true, text: 'kWh / Monat' },
                    grid: { color: '#f0f0f0' }
                },
                y1: { 
                    beginAtZero: true, 
                    position: 'right', 
                    grid: { drawOnChartArea: false }, 
                    title: { display: true, text: 'm³ / Monat' } 
                }
            }
        }
    });
</script>
</body>
</html>