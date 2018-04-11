<?php


$nocksAppendFile = dirname(__FILE__) . "/../functions.php";

$functionsFile = file($nocksAppendFile);

$nocksInstallationFound = false;

foreach ($functionsFile as $index => $line) {
	if (strpos($line, 'include(\'nocks-checkout/nocks-checkout.php\');') === 0) {
		$nocksInstallationFound = true;
	}
}

if (!$nocksInstallationFound && isset($_REQUEST['install'])) {
	$functionsFile[] = PHP_EOL . "include('nocks-checkout/nocks-checkout.php');";
	file_put_contents($nocksAppendFile, implode('', $functionsFile));
	header("Location: install.php?check");
}

?>

    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Nocks Easy Install</title>
        <style type="text/css">
            body {
                font-family: Muli, sans-serif;
            }

            .padd {
                padding: 20px 20px 50px;
            }

            .button {
                display: inline-block;
                vertical-align: middle;
                margin: 0 0 1rem;
                font-family: inherit;
                padding: .95em 1em;
                -webkit-appearance: none;
                border: 1px solid transparent;
                border-radius: 0;
                -webkit-transition: background-color .25s ease-out, color .25s ease-out;
                transition: background-color .25s ease-out, color .25s ease-out;
                font-size: .9rem;
                line-height: 1;
                text-align: center;
                cursor: pointer;
                background-color: #005b7f;
                color: #fefefe;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
    <div style="text-align: center;">
        <img class="padd" style="height: 200px; width: auto;" src="nocks.png"/>
        <div>
            Nocks Checkout Easy Digital Downloads plugin
            is <strong><?php echo !$nocksInstallationFound ? 'not' : 'succesfully'; ?></strong>
            installed!
        </div>
		<?php
		if (!$nocksInstallationFound) {
			if (isset($_REQUEST['check'])) {
				echo '<div class="padd">Something went wrong with the installation,<br/>make sure you have writing rights to ' . realpath($nocksAppendFile) . '?</div>';
			} else { ?>
                <div class="padd">
                    <a class="button" href="?install">Install Now!</a>
                </div>
			<?php }
		} ?>
    </div>
    </body>
    </html>


<?php
//include('nocks-checkout/nocks-checkout.php');
//exit;

