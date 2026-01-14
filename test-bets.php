#!/usr/bin/env php
<?php

/**
 * Test script to verify bet payouts are correct
 */

require_once __DIR__ . '/autoload.php';

use Craps\Game\Bet;

echo "🎲 Testing Bet Payouts\n";
echo str_repeat("=", 50) . "\n\n";

$tests = [
    // [Bet Type, Amount, Roll Total, Expected Payout, Description]

    // Pass Line / Don't Pass / Come / Don't Come (1:1)
    ['pass_line', 10, null, 20, 'Pass Line win (1:1) = $20'],
    ['dont_pass', 10, null, 20, 'Don\'t Pass win (1:1) = $20'],
    ['come', 10, null, 20, 'Come win (1:1) = $20'],
    ['dont_come', 10, null, 20, 'Don\'t Come win (1:1) = $20'],

    // Field bets
    ['field', 10, 3, 20, 'Field win on 3 (1:1) = $20'],
    ['field', 10, 2, 30, 'Field win on 2 (2:1) = $30'],
    ['field', 10, 12, 30, 'Field win on 12 (2:1) = $30'],

    // Hardways
    ['hardway_4', 10, null, 80, 'Hard 4 win (7:1) = $80'],
    ['hardway_10', 10, null, 80, 'Hard 10 win (7:1) = $80'],
    ['hardway_6', 10, null, 100, 'Hard 6 win (9:1) = $100'],
    ['hardway_8', 10, null, 100, 'Hard 8 win (9:1) = $100'],

    // Place bets
    ['place_4', 10, null, 28, 'Place 4 win (9:5) = $28'],
    ['place_10', 10, null, 28, 'Place 10 win (9:5) = $28'],
    ['place_5', 10, null, 24, 'Place 5 win (7:5) = $24'],
    ['place_9', 10, null, 24, 'Place 9 win (7:5) = $24'],
    ['place_6', 10, null, 21.67, 'Place 6 win (7:6) = $21.67'],
    ['place_8', 10, null, 21.67, 'Place 8 win (7:6) = $21.67'],

    // Proposition bets
    ['any_seven', 10, null, 50, 'Any Seven win (4:1) = $50'],
    ['any_craps', 10, null, 80, 'Any Craps win (7:1) = $80'],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    list($betType, $amount, $total, $expected, $description) = $test;

    $actual = Bet::calculatePayout($betType, $amount, $total);
    $match = abs($actual - $expected) < 0.01; // Allow for floating point rounding

    if ($match) {
        echo "✅ PASS: $description\n";
        $passed++;
    } else {
        echo "❌ FAIL: $description\n";
        echo "   Expected: \$$expected, Got: \$$actual\n";
        $failed++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed > 0) {
    echo "\n⚠️  Some tests failed! Check Bet::calculatePayout() logic.\n";
    exit(1);
} else {
    echo "\n🎉 All tests passed!\n";
    exit(0);
}
