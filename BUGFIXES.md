# Bug Fixes - Bet Resolution & Visual Sync

## Issues Fixed

### 1. **Bets Clearing Prematurely**
**Problem:** All bets were being cleared from the table after EVERY roll, even when they should stay active (like hardways, place bets, pass line during point phase).

**Solution:**
- Replaced the blind `clearResolvedBets()` call with `syncBetsWithServer()`
- Visual chips now sync with the server's actual active bets after each roll
- Only resolved bets are removed from display

### 2. **Missing Bet Resolution Logic**
**Problem:** Only Pass Line, Don't Pass, and Field bets were being resolved. All other bet types (Hardways, Place Bets, Come, Don't Come, Any Seven, Any Craps) were never resolved, so they never paid out or were removed.

**Solution:** Added complete resolution logic in `GameManager::resolveBets()` for:

#### Come/Don't Come Bets
- Work like Pass Line/Don't Pass but placed during point phase
- Resolve on naturals, craps, point made, or seven out

#### Proposition Bets
- **Any Seven:** One-roll bet, wins on 7, loses otherwise
- **Any Craps:** One-roll bet, wins on 2, 3, or 12, loses otherwise

#### Hardways Bets
- **Hard 4/10:** Wins 7:1 when rolled as 2-2 or 5-5
- **Hard 6/8:** Wins 9:1 when rolled as 3-3 or 4-4
- Loses if rolled "easy way" (non-matching dice)
- Loses on seven out
- Stays active on other numbers

#### Place Bets
- **Place 4/10:** Wins 9:5 when that number is rolled
- **Place 5/9:** Wins 7:5 when that number is rolled
- **Place 6/8:** Wins 7:6 when that number is rolled
- Loses on seven out
- Stays active on other numbers

### 3. **Incorrect Number Extraction**
**Problem:** The code used `substr($bet->getType(), -1)` which only grabbed the last character. This broke for "hardway_10" and "place_10" (extracting "0" instead of "10").

**Solution:** Changed to `substr($bet->getType(), strrpos($bet->getType(), '_') + 1)` which correctly extracts everything after the last underscore.

### 4. **Visual Bet Tracking**
**Problem:** The client-side `playerBets` object wasn't staying in sync with the server, causing visual discrepancies.

**Solution:**
- Added `syncBetsWithServer()` method that fetches active bets from server
- Clears all visual chips
- Rebuilds visual display from server state
- Called after every roll completes

## Files Modified

1. **src/Game/GameManager.php**
   - Added complete bet resolution for all bet types (lines 240-353)
   - Added payout logging for debugging (lines 365-373)
   - Fixed number extraction logic

2. **public/js/game.js**
   - Added `syncBetsWithServer()` method (lines 540-569)
   - Added `clearAllVisualChips()` method (lines 571-575)
   - Replaced `clearResolvedBets()` calls with `syncBetsWithServer()`
   - Fixed regex to replace all underscores in bet names (line 533)

## Testing Recommendations

### Test Each Bet Type:

1. **Pass Line:**
   - Come out roll: 7/11 wins, 2/3/12 loses
   - Point roll: Point wins, 7 loses

2. **Field:**
   - Wins on 2,3,4,9,10,11,12 (2 and 12 pay double)
   - Loses on 5,6,7,8

3. **Hardways:**
   - Place hardway 6
   - Roll 3-3 → Should win (9:1)
   - Roll 4-2 → Should lose (easy way)
   - Roll 7 → Should lose (seven out)

4. **Place Bets:**
   - Place bet on 6
   - Roll 6 → Should win (7:6)
   - Roll 7 → Should lose
   - Roll other numbers → Bet stays

5. **Proposition Bets:**
   - Any Seven: Roll 7 → Win (4:1)
   - Any Craps: Roll 2,3,12 → Win (7:1)

### Verify Visual Sync:
1. Place multiple bets
2. Roll dice
3. Check that only resolved bets disappear from table
4. Active bets should remain visible

## Debugging

Check PHP error logs to see payout information:
```bash
tail -f /var/log/php/error.log  # or wherever your PHP logs are
```

You'll see lines like:
```
Bet resolved: Player 1, Bet hardway_6, Amount: $10.00, Status: won, Payout: $100.00
```

Check browser console for sync status:
```javascript
// You'll see sync operations logged
Sync bets error: ... // If there are issues
```
