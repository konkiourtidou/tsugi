<?php

use \Tsugi\Util\U;
use \Tsugi\Core\LTIX;

require_once("../../config.php");
require_once("expire_util.php");

if ( ! U::isCli() ) die('Must be command line');

if ( ! isset($argv[1]) ) die("user / context / tenant required\n");

$base = $argv[1];
if ( $base == 'user' ) {
    $table = 'lti_user';
    $limit = 100; // Takes about 10 seconds
    $where = '';
} else if ( $base == 'context' ) {
    $table = 'lti_context';
    $limit = 10;
    $where = '';
} else if ( $base == 'tenant' ) {
    $table = 'lti_key';
    $limit = 1;
    $where = " AND ".get_safe_key_where().' ';
} else {
    die('Invalid data value');
}

$dryrun = ! ( isset($argv[2]) && $argv[2] == 'remove');

$cfg = 'expire_'.$base.'_days';
if ( ! isset($CFG->{$cfg}) ) {
    die('Please set $CFG->'.$cfg.' before running'."\n");
}
$days = $CFG->{$cfg};

if ( $days < 20 ) die('Minimum number of days is 20 found: '.$days."\n");

LTIX::getConnection();

$count = get_expirable_records($table, $days);

$sql = "DELETE FROM {$CFG->dbprefix}{$table}\n".
    get_expirable_where($days)."\n".$where.
    "ORDER BY login_at LIMIT $limit";

echo($sql."\n");

if ( $dryrun ) {
    echo("This is a dry run, use 'php ".$argv[0]." remove' to actually remove the data.\n");
    $count = get_pii_count($days);
    echo("User records with PII and have not logged in in $days days: $count \n");
    return;
} else {
    echo("This IS NOT A DRILL!\n");
    sleep(5);
    echo("...\n");
    sleep(5);
}

$start = time();

$stmt = $PDOX->prepare($sql);
$stmt->execute();

$count = $stmt->rowCount();
echo("Rows updated: $count\n");
$delta = time() - $start;
echo("\nEllapsed time: $delta seconds\n");

