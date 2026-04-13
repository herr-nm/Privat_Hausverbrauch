# Hausverbrauch: Verbrauchs-Tracker

Ein effizientes, webbasiertes Tool zur monatlichen Erfassung und Auswertung von Stromverbrauch, Stromeinspeisung (z. B. Photovoltaik) und Wasserverbrauch. Die Anwendung berechnet automatisch die Differenzen (Deltas) zum Vormonat und visualisiert die Verbräuche in einem Diagramm.

## 🚀 Features

  - **Strom-Tracking:** Erfassung von Zählerständen für Verbrauch (Bezug) und Einspeisung (Einspeisung) in kWh.
  - **Wasser-Tracking:** Erfassung des Wasserzählers in m³.
  - **Automatische Delta-Berechnung:** Das Tool berechnet automatisch den tatsächlichen Verbrauch im Vergleich zum letzten Eintrag.
  - **Visualisierung:** Ein interaktives Kombi-Diagramm (Chart.js) zeigt die Verläufe von Strom und Wasser auf zwei Achsen an.
  - **Dateibasierte Speicherung:** Alle Daten werden in einer `verbrauch_data.json` gespeichert. Keine SQL-Datenbank notwendig.
  - **Responsive UI:** Übersichtliche Tabellendarstellung mit Löschfunktion, optimiert für verschiedene Bildschirmgrößen.

## 🛠️ Installation

1.  **Voraussetzungen:**

      - Ein Webserver mit PHP (z. B. XAMPP, Docker mit PHP-Image oder klassisches Webhosting).
      - Schreibrechte im Verzeichnis für die JSON-Datei.

2.  **Dateien bereitstellen:**
    Lade die `verbrauch.php` auf deinen Server. Die Datei `verbrauch_data.json` wird beim ersten Speichern automatisch erstellt, sofern Schreibrechte vorliegen.

3.  **Konfiguration (Optional):**
    Sollte deine Datendatei anders heißen, kannst du den Namen oben in der `verbrauch.php` anpassen:

    ```php
    $jsonFile = 'verbrauch_data.json';
    ```

## 📊 Funktionsweise

Das Tool ist darauf ausgelegt, die Differenz zwischen zwei Zählerständen zu ermitteln:

  - Gibt man einen neuen Zählerstand ein, sucht das Script den chronologisch vorangegangenen Eintrag.
  - In der Tabelle wird der absolute Stand sowie in Klammern das berechnete Delta (der tatsächliche Monatsverbrauch) angezeigt.
  - Das Diagramm nutzt diese Delta-Werte, um Trends im Zeitverlauf sichtbar zu machen.

## 📂 Dateistruktur

  - `verbrauch.php`: Enthält die gesamte Logik (Eingabe, Berechnung, Anzeige & Chart).
  - `verbrauch_data.json`: Speichert die Rohdaten im JSON-Format.
  - `LICENSE`: Das Projekt unterliegt der **GNU Affero General Public License v3.0**.

## 🖥️ Verwendete Technologien

  - **PHP**: Backend-Logik und Datenverarbeitung.
  - **Chart.js**: Zur grafischen Darstellung der Verbräuche.
  - **CSS**: Minimalistisches Design (angelehnt an das GitHub-Theme).
  - **JSON**: Einfache und portable Datenhaltung.

## 📄 Lizenz

Dieses Projekt ist unter der GNU Affero General Public License v3.0 lizenziert – siehe die [LICENSE](LICENSE) Datei für Details. Erstellt von Herr-NM.

-----

*Ideal für die monatliche Kontrolle von Nebenkosten und Energieeffizienz.*