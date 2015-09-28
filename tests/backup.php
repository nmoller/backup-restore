<?php
/**
 *
 * PHP Version 5.5
 * @category:
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author: <nmoller@crosemont.qc.ca>
 * @link http://github.com/nmoller GitHub
 * 
 */

require __DIR__.'/../src/cad/command.php';

// pour avoir le résultat:
// en ligne de commande php backup.php --path /home/nmoller --sn 383-204-FD-60-03

$t1 = \cad\backup::run();