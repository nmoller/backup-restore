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

require __DIR__.'/../src/cad/restore.php';

// pour avoir le résultat:
// en ligne de commande php restore.php --file /home/nmoller/backup_383-204-FD-60-03_2015.09.29.mbz --cat 4

\cad\restore::run();