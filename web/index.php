<?php

require_once '../vendor/autoload.php';

use ExtendedStrings\Strings\Cent;
use ExtendedStrings\Strings\Harmonic;
use ExtendedStrings\Strings\HarmonicCalculator;
use ExtendedStrings\Strings\Instrument;
use ExtendedStrings\Strings\InstrumentStringInterface;
use ExtendedStrings\Strings\Math;
use ExtendedStrings\Strings\Note;

$self = $_SERVER['PHP_SELF'];
$self = $self === '/index.php' ? '/' : $self;
?>
<html lang="en">
<head>
  <title>Find harmonics</title>
  <style type="text/css">
    body {
      font-family: sans-serif;
      background: white;
      margin: 40px 80px;
    }
    input, select {
      font-size: 1em;
      margin: 5px 0;
      background-color: white;
    }
    code {
      font-size: 1.3em;
    }
    .error {
      color: red;
    }
    .string-length.short {
      color: red;
    }
  </style>
</head>
<body>

  <h1>String harmonics calculator</h1>

  <form action="<?php echo htmlentities($self); ?>" method="POST">
    <div>
    Instrument: <select name="instrument">
        <?php
        $options = [
          'violin' => 'Violin',
          'viola' => 'Viola',
          'cello' => 'Cello',
        ];
        foreach ($options as $name => $option) {
          echo '<option value="' . $name . '"';
          if (isset($_REQUEST['instrument']) && $_REQUEST['instrument'] === $name) {
            echo ' selected="selected"';
          }
          echo '>' . $option . '</option>';
        }
        ?>
    </select>
    </div>
    <div>
    Sounding note: <input type="text" name="note" placeholder="Note name (e.g. A4)" value="<?php echo isset($_REQUEST['note']) ? $_REQUEST['note'] : ''; ?>" />
    </div>
    <div>
      <input type="submit" class="submit" value="Find harmonics" />
      &nbsp;&nbsp;<a href="<?php echo htmlentities($self); ?>">Reset</a>
    </div>
  </form>
  <?php
if (!empty($_REQUEST['instrument']) && !empty($_REQUEST['note'])):

  try {
    $instrumentName = isset($_REQUEST['instrument']) ? $_REQUEST['instrument'] : 'violin';
    $instrument = Instrument::fromPreset($instrumentName);
    $soundingNoteName = isset($_REQUEST['note']) ? $_REQUEST['note'] : 'A4';
    $soundingNote = Note::fromName($soundingNoteName);
    $harmonics = (new HarmonicCalculator())
      ->findHarmonics($soundingNote, $instrument);
  } catch (\Exception $e) {
    echo "<p>Error: <span class=\"error\">" . $e->getMessage() . "</span></p>";
  }

  $stringNames = [];
  if (!empty($harmonics)) {

    echo "<p>Sounding note: <code>" . $soundingNote . "</code></p>";

    foreach ($harmonics as $harmonic) {
      $string = $harmonic->getString();
      $stringName = Note::fromFrequency($string->getFrequency())->__toString();
      if (!in_array($stringName, $stringNames, true)) {
        echo sprintf("<h3>String: %s</h3>\n", $stringName);
        $stringNames[] = $stringName;
      }

      $physicalLength = $string->getPhysicalLength();
      $remainingLength = $harmonic->getHalfStop()->getStringLength();
      $shortRemainingPhysicalLength = 100;

      echo '<p>';
      if ($harmonic->isNatural()) {
        $length = 1 - $remainingLength;
        $gcd = Math::gcd(1, $length);
        $numerator = $length / $gcd;
        echo "<b>Natural harmonic";
        if ($harmonic->getNumber() !== 1) {
          echo ", $numerator/" . 1 / $gcd . " along string";
        }
        echo ":  </b>\n";
        if ($harmonic->getNumber() === 1) {
          echo "<br />    fundamental / open string";
        } else {
          echo sprintf("<br />    sounding: <code>%s</code>\n", Note::fromFrequency($harmonic->getSoundingFrequency(), 440.0, [$soundingNote->getAccidental()]));
          echo sprintf("<br />    harmonic-pressure stop: <code>%s</code>\n", Note::fromFrequency($harmonic->getHalfStop()->getFrequency($string), 440.0, [$soundingNote->getAccidental()]));
          echo sprintf("<br />    remaining string length: <span class=\"string-length%s\">%d%% (%d mm)</span>", $remainingLength * $physicalLength < $shortRemainingPhysicalLength ? ' short' : '', $remainingLength * 100, $remainingLength * $physicalLength);
        }
      } else {
        $halfCents = Cent::frequenciesToCents($harmonic->getHalfStop()->getStringLength(), 1);
        $baseCents = Cent::frequenciesToCents($harmonic->getBaseStop()->getStringLength(), 1);
        $intervalCents = $halfCents - $baseCents;
        $intervalLength = $harmonic->getBaseStop()->getStringLength() - $harmonic->getHalfStop()->getStringLength();
        echo sprintf("<b>Artificial harmonic, %s apart:</b>\n", getIntervalName($intervalCents));
        echo sprintf("<br />    lower stop: <code>%s</code>\n", Note::fromFrequency($harmonic->getBaseStop()->getFrequency($string), 440.0, [$soundingNote->getAccidental()]));
        echo sprintf("<br />    upper (harmonic-pressure) stop: <code>%s</code>\n", Note::fromFrequency($harmonic->getHalfStop()->getFrequency($string), 440.0, [$soundingNote->getAccidental()]));
        echo sprintf("<br />    distance between stops: <span class=\"string-length%s\">%d mm</span>", $intervalLength * $physicalLength < 20 ? ' short' : '', $intervalLength * $physicalLength);
        echo sprintf("<br />    remaining string length: <span class=\"string-length%s\">%d%% (%d mm)</span>", $remainingLength * $physicalLength < $shortRemainingPhysicalLength ? ' short' : '', $remainingLength * 100, $remainingLength * $physicalLength);
      }
      echo '</p>';
    }
  } else {
    printf("<p>No harmonics found for sounding note <code>%s</code> on a %s</p>\n", isset($soundingNote) ? $soundingNote->__toString() : $soundingNoteName, $instrumentName);
  }


endif;
  ?>

  <p>Disclaimer: this is a quick proof-of-concept for a much better tool. There will be bugs (<a href="https://github.com/pjcdawkins/harmonics/issues">report issues here</a>).</p>

  <p>© 2017 <a href="https://ligetiquartet.com">Ligeti Quartet</a></p>

</body>
</html>
<?php

function getIntervalName(float $cents): string {
  $centsString = sprintf('%.2fc', $cents);
  $intervalNames = [
    316 => 'a just minor third',
    386 => 'a just major third',
    498 => 'a just fourth',
    702 => 'a just fifth',
    1200 => 'an octave',
  ];
  if (isset($intervalNames[round($cents)])) {
    return $intervalNames[round($cents)] . ' (' . $centsString . ')';
  }

  return $centsString;
}
